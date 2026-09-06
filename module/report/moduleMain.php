<?php

namespace Kokonotsuba\Modules\report;

use Kokonotsuba\board\board;
use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\error\BoardException;
use Kokonotsuba\module_classes\abstractModuleMain;
use Kokonotsuba\ban\banCheckpoint;
use Kokonotsuba\module_classes\traits\BanCheckpointTrait;
use Kokonotsuba\module_classes\traits\listeners\ModuleHeaderListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\PostListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\PostWidgetListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\TopLinksListenerTrait;
use Kokonotsuba\post\Post;

use function Kokonotsuba\libraries\_T;
use function Kokonotsuba\libraries\getCsrfHiddenInput;
use function Kokonotsuba\libraries\getOrCreateCsrfToken;
use function Kokonotsuba\libraries\html\drawPager;
use function Kokonotsuba\libraries\requirePostWithCsrf;
use function Kokonotsuba\libraries\searchBoardArrayForBoard;
use function Puchiko\json\renderJsonErrorPage;
use function Puchiko\json\renderJsonPage;
use function Puchiko\json\renderPrivateJsonPage;
use function Puchiko\strings\newLinesToBreakLines;
use function Puchiko\strings\sanitizeStr;

require_once __DIR__ . '/reportStatus.php';
require_once __DIR__ . '/reportRepository.php';
require_once __DIR__ . '/reportService.php';
require_once __DIR__ . '/reportPostPreview.php';

/**
 * User-facing half of the report system.
 *
 * Adds a [Report] entry to every post's menu (plus a plain link for people without JS), serves
 * the report form and its submission endpoint, and gives reporters a page listing what they've
 * filed and what staff did about it.
 */
class moduleMain extends abstractModuleMain {
	use BanCheckpointTrait;
	use ModuleHeaderListenerTrait;
	use PostListenerTrait;
	use PostWidgetListenerTrait;
	use TopLinksListenerTrait;

	private reportService $reportService;
	private reportPostPreview $reportPostPreview;
	private string $modulePageUrl;
	private string $myReportsUrl;
	private int $reasonMaxLength;

	/** Post|false keyed by UID, so a post reported more than once is fetched once. */
	private array $postCache = [];

	public function getName(): string {
		return 'Post reports';
	}

	public function getVersion(): string {
		return 'Koko BBS Release';
	}

	public function initialize(): void {
		$this->modulePageUrl = $this->getModulePageURL([], false);
		$this->myReportsUrl = $this->getModulePageURL(['pageName' => 'myReports'], false);
		$this->reasonMaxLength = (int) $this->getModuleConfig('REASON_MAX_LENGTH', 1000);

		$tableNames = $this->moduleContext->getTableNames();
		$reportRepository = new reportRepository(
			databaseConnection::getInstance(),
			$tableNames['REPORT_TABLE'],
			$tableNames['REPORT_READ_TABLE'],
			$tableNames['ACCOUNT_TABLE'],
			$tableNames['POST_TABLE'],
			$tableNames['BOARD_TABLE']
		);

		$this->reportService = new reportService(
			$reportRepository,
			$this->moduleContext->postDeletionService,
			$this->moduleContext->transactionManager
		);

		$this->reportPostPreview = new reportPostPreview(
			$this->moduleContext->board,
			$this->moduleContext->moduleEngine,
			$this->moduleContext->templateEngine,
			$this->moduleContext->quoteLinkService,
			$this->moduleContext->request
		);

		// Not role-protected: a post can be deleted by staff, by a thread cascade, or by its own
		// author with a password, and its reports should resolve in every case.
		$this->moduleContext->moduleEngine->addListener('PostsDeleted',
			function (array &$postUids, ?int &$accountId) {
				$this->onPostsDeleted($postUids, $accountId);
			}
		);

		$this->listenPostWidget('onRenderPostWidget');
		$this->listenPost('onRenderPost');
		$this->listenModuleHeader('onGenerateModuleHeader');

		$this->addTopLink($this->myReportsUrl, _T('report_adminbar_link'));
	}

	// ─── Frontend hooks ───────────────────────────────────────────

	/**
	 * Add [Report] to the post dropdown. The href is a working link on its own, so middle-click
	 * and JS-off both land on the form page; report.js intercepts the action for the window.
	 */
	private function onRenderPostWidget(array &$widgetArray, Post &$post): void {
		$reportUrl = $this->getReportFormUrl($post->getUid());
		$postBoard = searchBoardArrayForBoard($post->getBoardUID()) ?? $this->moduleContext->board;

		// data-param-* attribute names are lowercased by the browser, so the keys are too.
		$widgetArray[] = $this->buildWidgetEntry(
			$reportUrl,
			'reportPost',
			_T('report_widget_label'),
			'',
			[
				'posturl' => $reportUrl,
				'postuid' => $post->getUid(),
				'postnumber' => $post->getNumber(),
				'boardtitle' => $postBoard->getBoardTitle(),
			]
		);
	}

	/**
	 * Resolve a deleted post's reports.
	 *
	 * Deleting the post is what approving a report does, so a deletion from anywhere else grants
	 * those reports by the same logic — and leaves the queue pointing at live posts only.
	 *
	 * Approving through the queue reaches this too, via removePosts(); by then those reports are
	 * already approved inside the same transaction, so there is nothing pending left to find and
	 * it quietly does nothing.
	 */
	private function onPostsDeleted(array $postUids, ?int $accountId): void {
		$this->reportService->approveReportsForDeletedPosts(
			$postUids,
			$accountId ?: null,
			_T('report_auto_approved_reason')
		);
	}

	/**
	 * The post dropdown toggle is .js-only, so without JS there'd be no way to reach the form.
	 * Mirror the entry as a plain link that only shows when scripting is off.
	 */
	private function onRenderPost(array &$templateValues, Post &$post, array &$threadPosts, board &$board, bool &$adminMode): void {
		$reportUrl = $this->getReportFormUrl($post->getUid(), true);

		$templateValues['{$POSTINFO_EXTRA}'] .= ' <span class="reportLinkContainer no-js-only">[<a href="'
			. $reportUrl . '" title="' . sanitizeStr(_T('report_widget_title')) . '">'
			. sanitizeStr(_T('report_widget_label')) . '</a>]</span>';
	}

	/**
	 * Ship the report window's assets and its form markup.
	 *
	 * The form lives in a <template> rather than being assembled in JS so both paths render the
	 * exact same markup. It carries no CSRF token: this hook also runs while static HTML is
	 * being generated, and a token baked into a cached page would be wrong for everyone who
	 * later reads it — report.js fetches a fresh one when the window opens.
	 */
	private function onGenerateModuleHeader(string &$moduleHeader): void {
		$moduleHeader .= '<link rel="stylesheet" href="'
			. sanitizeStr($this->getConfig('STATIC_URL') . 'css/module/report.css') . '">';

		$this->includeScript('report.js?v=3', $moduleHeader);

		$moduleHeader .= '<meta name="reportModuleUrl" content="' . sanitizeStr($this->modulePageUrl) . '">';

		$formHtml = $this->moduleContext->adminPageRenderer->ParseBlock('REPORT_FORM', $this->buildReportFormValues());

		$moduleHeader .= $this->generateTemplate('reportFormTemplate', $formHtml);
	}

	// ─── Routing ──────────────────────────────────────────────────

	public function ModulePage(): void {
		$request = $this->moduleContext->request;

		if ($request->isPost()) {
			$this->handleSubmission();
			return;
		}

		$pageName = (string) $request->getParameter('pageName', 'GET', '');

		match ($pageName) {
			'token' => $this->handleTokenRequest(),
			'myReports' => $this->drawMyReports(),
			default => $this->drawReportFormPage(),
		};
	}

	/**
	 * Hand report.js a CSRF token for the window's form.
	 *
	 * The <template> in the page header can't carry one (see onGenerateModuleHeader), and the
	 * response is same-origin-only, so a cross-site page cannot read what it returns.
	 */
	private function handleTokenRequest(): void {
		renderPrivateJsonPage(['token' => getOrCreateCsrfToken()]);
	}

	// ─── Filing a report ──────────────────────────────────────────

	private function handleSubmission(): void {
		$request = $this->moduleContext->request;

		requirePostWithCsrf($request);

		$reporterIp = (string) $request->userIp();

		$this->assertNotBanned(banCheckpoint::REPORT);

		$postUid = (int) $request->getParameter('postUid', 'POST', 0);
		$post = $postUid > 0 ? $this->moduleContext->postRepository->getPostByUid($postUid) : false;

		if (!$post || $post->isDeleted()) {
			$this->fail(_T('report_error_post_not_found'), 404);
			return;
		}

		if ($this->reportService->hasOpenReport($postUid, $reporterIp)) {
			$this->fail(_T('report_error_already_reported'), 409);
			return;
		}

		$reason = trim((string) $request->getParameter('reason', 'POST', ''));

		if (mb_strlen($reason) > $this->reasonMaxLength) {
			$this->fail(_T('report_error_reason_too_long', $this->reasonMaxLength), 400);
			return;
		}

		// The reason is optional — store nothing rather than an empty string when it's blank.
		$this->reportService->fileReport(
			$postUid,
			$post->getBoardUID(),
			$reporterIp,
			$reason === '' ? null : $reason
		);

		$this->succeed($post);
	}

	/** Report accepted: JSON for the window, a confirmation page for the plain form. */
	private function succeed(Post $post): void {
		if ($this->moduleContext->request->isAjax()) {
			renderJsonPage([
				'success' => true,
				'postNumber' => $post->getNumber(),
				'message' => _T('report_submitted', $post->getNumber()),
			]);
			return;
		}

		$this->drawUserPage('REPORT_NOTICE', [
			'{$NOTICE_TITLE}' => sanitizeStr(_T('report_submitted_title')),
			'{$NOTICE_TEXT}' => sanitizeStr(_T('report_submitted', $post->getNumber())),
			'{$MY_REPORTS_URL}' => sanitizeStr($this->myReportsUrl),
			'{$MY_REPORTS_TEXT}' => sanitizeStr(_T('report_my_reports_title')),
		]);
	}

	/** Report rejected: JSON error for the window, a board exception for the plain form. */
	private function fail(string $message, int $statusCode): void {
		if ($this->moduleContext->request->isAjax()) {
			renderJsonErrorPage($message, $statusCode);
			return;
		}

		throw new BoardException($message);
	}

	// ─── Pages ────────────────────────────────────────────────────

	/** The no-JS report form, rendered as a normal reader-facing page. */
	private function drawReportFormPage(): void {
		$postUid = (int) $this->moduleContext->request->getParameter('postUid', 'GET', 0);
		$post = $postUid > 0 ? $this->moduleContext->postRepository->getPostByUid($postUid) : false;

		if (!$post || $post->isDeleted()) {
			throw new BoardException(_T('report_error_post_not_found'));
		}

		$this->assertNotBanned(banCheckpoint::REPORT);

		$postBoard = searchBoardArrayForBoard($post->getBoardUID()) ?? $this->moduleContext->board;

		$this->reportPostPreview->preloadQuoteLinks([$post->getUid()]);

		$this->drawUserPage('REPORT_FORM', $this->buildReportFormValues(
			$post->getUid(),
			$post->getNumber(),
			$postBoard->getBoardTitle(),
			$this->reportPostPreview->render($post),
			getCsrfHiddenInput()
		));
	}

	/**
	 * Everything this IP has reported, and what became of it.
	 *
	 * Deliberately shows the public reason only, and never names the moderator — the reporter
	 * gets to know their report was handled and why, not who handled it.
	 */
	private function drawMyReports(): void {
		$request = $this->moduleContext->request;
		$reporterIp = (string) $request->userIp();

		$perPage = max(1, (int) $this->getConfig('PAGE_DEF', 15));
		$page = max(1, (int) $request->getParameter('page', 'GET', 1));
		$offset = ($page - 1) * $perPage;

		$reports = $this->reportService->getReportsByIp($reporterIp, $perPage, $offset);
		$totalReports = $this->reportService->countReportsByIp($reporterIp);

		$this->reportPostPreview->preloadQuoteLinks(
			array_map(static fn(array $report): int => (int) $report['post_uid'], $reports)
		);

		$rows = [];
		foreach ($reports as $report) {
			$status = reportStatus::fromValue($report['status']);
			$actionedAt = $report['actioned_at'] ?? null;

			$rows[] = [
				'{$REPORT_ID}' => (int) $report['report_id'],
				// No separate number/link column — the rendered post carries its own No. link.
				'{$POST_PREVIEW}' => $this->renderPostPreview((int) $report['post_uid']),
				'{$BOARD_TITLE}' => sanitizeStr((string) ($report['board_title'] ?? '')),
				'{$REASON}' => newLinesToBreakLines(sanitizeStr((string) ($report['reporter_reason'] ?? ''))),
				'{$DATE_REPORTED}' => $this->formatDate($report['date_reported'] ?? null),
				'{$STATUS_LABEL}' => sanitizeStr($status->label()),
				'{$STATUS_CLASS}' => $status->rowCssClass(),
				'{$IS_ACTIONED}' => $status->isPending() ? '' : '1',
				'{$ACTIONED_AT}' => $actionedAt === null ? '' : $this->formatDate($actionedAt),
				'{$PUBLIC_REASON}' => newLinesToBreakLines(sanitizeStr((string) ($report['public_reason'] ?? ''))),
			];
		}

		$this->drawUserPage('REPORT_MY_REPORTS', [
			'{$PAGE_TITLE}' => sanitizeStr(_T('report_my_reports_title')),
			'{$INTRO_TEXT}' => sanitizeStr(_T('report_my_reports_intro')),
			'{$HEADING_REPORTS}' => sanitizeStr(_T('report_heading_your_reports')),
			'{$TH_POST}' => sanitizeStr(_T('report_th_post')),
			'{$TH_BOARD}' => sanitizeStr(_T('report_th_board')),
			'{$TH_REASON}' => sanitizeStr(_T('report_th_your_reason')),
			'{$TH_DATE}' => sanitizeStr(_T('report_th_date')),
			'{$TH_STATUS}' => sanitizeStr(_T('report_th_status')),
			'{$TH_ACTIONED_AT}' => sanitizeStr(_T('report_th_actioned_at')),
			'{$TH_STAFF_REASON}' => sanitizeStr(_T('report_th_staff_reason')),
			'{$NO_REPORTS_TEXT}' => sanitizeStr(_T('report_my_reports_empty')),
			'{$HAS_REPORTS}' => empty($rows) ? '' : '1',
			'{$REPORTS}' => $rows,
		], drawPager($perPage, $totalReports, $this->myReportsUrl, $request));
	}

	// ─── Helpers ──────────────────────────────────────────────────

	/**
	 * Template values for REPORT_FORM. Filled in twice: blank for the <template> report.js
	 * clones, and populated for the no-JS page.
	 */
	private function buildReportFormValues(
		int $postUid = 0,
		int $postNumber = 0,
		string $boardTitle = '',
		string $postPreviewHtml = '',
		?string $csrfInput = null
	): array {
		return [
			'{$MODULE_URL}' => sanitizeStr($this->modulePageUrl),
			'{$CSRF_TOKEN}' => $csrfInput ?? '<input type="hidden" name="csrf_token" value="">',
			'{$POST_UID}' => $postUid,
			'{$POST_NUMBER}' => $postNumber ?: '',
			'{$BOARD_TITLE}' => sanitizeStr($boardTitle),
			'{$POST_PREVIEW}' => $postPreviewHtml,
			'{$FORM_TITLE}' => sanitizeStr(_T('report_form_title')),
			'{$TH_PREVIEW}' => sanitizeStr(_T('report_th_preview')),
			'{$TH_POST_NUMBER}' => sanitizeStr(_T('report_th_post_number')),
			'{$TH_BOARD}' => sanitizeStr(_T('report_th_board')),
			'{$TH_REASON}' => sanitizeStr(_T('report_th_reason')),
			'{$REASON_HINT}' => sanitizeStr(_T('report_reason_hint')),
			'{$REASON_MAX_LENGTH}' => $this->reasonMaxLength,
			'{$SUBMIT_TEXT}' => sanitizeStr(_T('report_submit')),
		];
	}

	/**
	 * Render the reported post for the reporter's own list.
	 *
	 * Deleted posts are deliberately NOT rendered here: approving a report deletes the post, and
	 * this page is shown to an ordinary reader, so pulling the content back out for them would
	 * undo the deletion in the only way that matters. Staff pages pass true and do see them.
	 */
	private function renderPostPreview(int $postUid): string {
		$post = $this->postCache[$postUid] ??= $this->moduleContext->postRepository->getPostByUid($postUid);

		if (!$post) {
			return '<div class="reportMissingPost">' . sanitizeStr(_T('report_post_unavailable')) . '</div>';
		}

		return $this->reportPostPreview->render($post);
	}

	/** URL of the report form for a post, on the current board's live frontend. */
	private function getReportFormUrl(int $postUid, bool $forHtml = false): string {
		return $this->getModulePageURL(['postUid' => $postUid], $forHtml);
	}

	/**
	 * Format a timestamp the way post dates are formatted elsewhere.
	 *
	 * Returns HTML — the formatter wraps the date, weekday and time in their own spans — so the
	 * result must NOT be passed through sanitizeStr() or the markup shows up as text. Nothing
	 * user-supplied reaches it: the input is a database datetime and the only other content is
	 * the translated weekday name.
	 */
	private function formatDate(?string $dateString): string {
		if ($dateString === null || $dateString === '') {
			return '';
		}

		return $this->moduleContext->postDateFormatter->formatFromDateString($dateString);
	}

	/**
	 * Render a reader-facing page.
	 *
	 * The module's own block is parsed first and dropped into GLOBAL_ADMIN_PAGE_CONTENT, the
	 * same way privateMessage renders its user-side pages. Passing false keeps the staff nav
	 * out and gives the page the reader Return link instead.
	 *
	 * @param string $templateBlock  Name of this module's block.
	 * @param array  $templateValues Values for that block.
	 * @param string $pagerHtml      Pager markup, or an empty string for unpaged pages.
	 */
	private function drawUserPage(string $templateBlock, array $templateValues, string $pagerHtml = ''): void {
		$contentHtml = $this->moduleContext->adminPageRenderer->ParseBlock($templateBlock, $templateValues);

		echo $this->moduleContext->adminPageRenderer->ParsePage('GLOBAL_ADMIN_PAGE_CONTENT', [
			'{$PAGE_CONTENT}' => $contentHtml,
			'{$PAGER}' => $pagerHtml,
		], false);
	}
}
