<?php

namespace Kokonotsuba\Modules\report;

use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\error\BoardException;
use Kokonotsuba\module_classes\abstractModuleAdmin;
use Kokonotsuba\module_classes\traits\AuditableTrait;
use Kokonotsuba\module_classes\traits\IndicatorTrait;
use Kokonotsuba\module_classes\traits\listeners\PostControlHooksTrait;
use Kokonotsuba\module_classes\traits\listeners\StaffAlertsListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\StaffNavListenerTrait;
use Kokonotsuba\post\Post;
use Kokonotsuba\userRole;

use function Kokonotsuba\libraries\_T;
use function Kokonotsuba\libraries\getCsrfHiddenInput;
use function Kokonotsuba\libraries\html\drawPager;
use function Kokonotsuba\libraries\searchBoardArrayForBoard;
use function Puchiko\json\renderJsonPage;
use function Puchiko\json\renderPrivateJsonPage;
use function Puchiko\request\redirect;
use function Puchiko\strings\newLinesToBreakLines;
use function Puchiko\strings\sanitizeStr;

require_once __DIR__ . '/reportStatus.php';
require_once __DIR__ . '/reportRepository.php';
require_once __DIR__ . '/reportService.php';
require_once __DIR__ . '/reportPolicy.php';
require_once __DIR__ . '/reportPostPreview.php';

/**
 * Moderator half of the report system.
 *
 * Serves the report queue, the per-report detail view and the reported-posts overview, applies
 * moderator decisions, and drives the unread badge in the admin nav plus the browser
 * notifications for reports filed in the last hour.
 */
class moduleAdmin extends abstractModuleAdmin {
	use AuditableTrait;
	use IndicatorTrait;
	use PostControlHooksTrait;
	use StaffAlertsListenerTrait;
	use StaffNavListenerTrait;

	/** ?status= value that widens the queue back out to every status. */
	private const STATUS_FILTER_ALL = 'all';

	private reportService $reportService;
	private reportPolicy $reportPolicy;
	private reportPostPreview $reportPostPreview;
	private string $modulePageUrl;
	private string $reportedPostsUrl;
	private int $reportsPerPage;
	private int $currentAccountId;
	private ?array $sharedTemplateValues = null;

	/** Post|false keyed by UID, so a post appearing in several report rows is fetched once. */
	private array $postCache = [];

	/** True while this module is rendering a post preview of its own; see renderPostPreview(). */
	private bool $renderingPreview = false;

	public function getRequiredRole(): userRole {
		return $this->getConfig('AuthLevels.CAN_VIEW_REPORTS', userRole::LEV_JANITOR);
	}

	public function getName(): string {
		return 'Report management';
	}

	public function getVersion(): string {
		return 'Koko BBS Release';
	}

	public function initialize(): void {
		$this->modulePageUrl = $this->getModulePageURL([], false, true);
		$this->reportedPostsUrl = $this->getModulePageURL(['pageName' => 'posts'], false, true);
		$this->reportsPerPage = max(1, (int) $this->getConfig('ADMIN_PAGE_DEF', 100));
		$this->currentAccountId = (int) ($this->moduleContext->currentUserId ?? 0);

		$databaseSettings = getDatabaseSettings();
		$reportRepository = new reportRepository(
			databaseConnection::getInstance(),
			$databaseSettings['REPORT_TABLE'],
			$databaseSettings['REPORT_READ_TABLE'],
			$databaseSettings['ACCOUNT_TABLE'],
			$databaseSettings['POST_TABLE'],
			$databaseSettings['BOARD_TABLE']
		);

		$this->reportService = new reportService(
			$reportRepository,
			$this->moduleContext->postDeletionService,
			$this->moduleContext->transactionManager
		);

		$this->reportPolicy = new reportPolicy(
			$this->getConfig('AuthLevels', []),
			$this->moduleContext->staffAccountFromSession->getRoleLevel()
		);

		$this->reportPostPreview = new reportPostPreview(
			$this->moduleContext->board,
			$this->moduleContext->moduleEngine,
			$this->initModuleTemplateEngine('modules.report.REPORT_POST_TEMPLATE', 'kokoimg'),
			$this->moduleContext->quoteLinkService,
			$this->moduleContext->request
		);

		$this->listenProtected('LinksAboveBar', function (string &$linkHtml) {
			$this->onRenderNavLink($linkHtml);
		});

		$this->registerAdminHeaderHook('onGenerateAdminHeader');
		$this->registerPostWidgetHook('onRenderPostWidget');
		$this->listenStaffAlertsProtected('onCollectStaffAlerts');
		$this->listenStaffNavProtected('onCollectStaffNavLinks');

		// ModuleAdminHeader is not dispatched on admin panel pages, and the report tables live
		// there — so the action window's assets ride the plain ModuleHeader hook, role-protected
		// so they are only emitted for staff who can see reports.
		$this->listenProtected('ModuleHeader', function (string &$moduleHeader) {
			$this->onGenerateReportAssets($moduleHeader);
		});

		$this->listenProtected('BelowComment', function (string &$belowComment, Post &$post, array &$threadPosts, bool &$adminMode) {
			$this->onRenderReportedNotice($belowComment, $post, $adminMode);
		});
	}

	// ─── Admin UI hooks ───────────────────────────────────────────

	/**
	 * "Reports" in the nav above the admin bar, with a (n) indicator when this moderator has
	 * pending reports they haven't opened yet.
	 */
	private function onRenderNavLink(string &$linkHtml): void {
		$unreadCount = $this->currentAccountId > 0
			? $this->reportService->countUnreadForAccount($this->currentAccountId)
			: 0;

		$indicatorHtml = $this->renderIndicator(
			'reportUnread',
			' (' . $unreadCount . ')',
			'reportUnreadIndicator',
			$unreadCount === 0,
			_T('report_nav_unread_title')
		);

		$linkHtml .= '<li class="adminNavLink"><a title="' . sanitizeStr(_T('report_nav_title'))
			. '" href="' . sanitizeStr($this->modulePageUrl) . '">'
			. sanitizeStr(_T('report_nav')) . $indicatorHtml . '</a></li>';
	}

	/**
	 * "Reports" in the sticky staff nav, kept at the top level rather than inside a drop-up: it
	 * is the one nav entry that regularly has something waiting behind it.
	 */
	private function onCollectStaffNavLinks(array &$entries): void {
		$entries[] = [
			'key' => 'report',
			'label' => _T('report_nav'),
			'url' => $this->modulePageUrl,
			'title' => _T('report_nav_title'),
			'group' => '',
			'count' => $this->currentAccountId > 0
				? $this->reportService->countUnreadForAccount($this->currentAccountId)
				: 0,
		];
	}

	/**
	 * "Reports" in the staff alerts widget, carrying the same unread count as the nav badge.
	 *
	 * Opening the queue marks what it lists as read, so the count clears itself by being acted
	 * on — the widget needs no read receipt of its own.
	 */
	private function onCollectStaffAlerts(array &$alerts): void {
		$alerts[] = [
			'key' => 'reports',
			'label' => _T('report_nav'),
			'count' => $this->currentAccountId > 0
				? $this->reportService->countUnreadForAccount($this->currentAccountId)
				: 0,
			'url' => $this->modulePageUrl,
			'title' => _T('report_nav_unread_title'),
		];
	}

	/**
	 * Notification poller, only on staff-rendered pages.
	 *
	 * No stylesheet link here — moduleMain already emits one from the ModuleHeader hook, which
	 * fires for these pages too.
	 */
	private function onGenerateAdminHeader(string &$moduleHeader): void {
		if (!$this->getModuleConfig('ENABLE_NOTIFICATIONS', true)) {
			return;
		}

		$this->includeScript('reportAdmin.js?v=3', $moduleHeader);

		$moduleHeader .= '<meta name="reportNotifyApi" content="'
			. sanitizeStr($this->getModulePageURL(['pageName' => 'notifications'], false, true)) . '">';
		$moduleHeader .= '<meta name="reportNotifyInterval" content="'
			. (int) $this->getModuleConfig('NOTIFICATION_POLL_SECONDS', 60) . '">';
	}

	/**
	 * The [Action] window's script and the form markup it clones.
	 *
	 * The <template> carries an empty CSRF token on purpose — this hook also runs while static
	 * HTML is generated, and a baked-in token would be stale for whoever reads that page later.
	 * reportAction.js copies a live one off a form already on the page.
	 */
	private function onGenerateReportAssets(string &$moduleHeader): void {
		$this->includeScript('reportAction.js?v=2', $moduleHeader);

		$formHtml = $this->moduleContext->adminPageRenderer->ParseBlock(
			'REPORT_ACTION_FORM',
			$this->buildActionFormValues(null, '', '<input type="hidden" name="csrf_token" value="">')
		);

		$moduleHeader .= $this->generateTemplate('reportActionFormTemplate', $formHtml);

		// The reports window clones the very same page and row blocks, rendered blank: the client
		// fills them from the postReportsApi payload, and .reportWindowBody drops the columns and
		// page chrome a window doesn't need. One set of markup serves both.
		$blank = array_merge($this->getSharedTemplateValues(), [
			'{$PAGE_TITLE}' => '', '{$QUEUE_URL}' => '', '{$QUEUE_TEXT}' => '',
			'{$HEADING_POST}' => '', '{$POST_PREVIEW}' => '', '{$POST_UID}' => 0,
			'{$HEADING_TOTALS}' => sanitizeStr(_T('report_heading_totals')),
			'{$HEADING_REPORTS}' => sanitizeStr(_T('report_heading_reports_on_post')),
			'{$STATS_TABLE}' => $this->renderStatsTable([]),
			'{$TH_DECISION}' => '', '{$CAN_CLEAR}' => '', '{$DECISION_FORM}' => '',
			// Rendered as the empty case so the clone carries the no-reports message and a hidden
			// table; the client removes one and reveals the other once the payload arrives.
			'{$HAS_REPORTS}' => '', '{$NO_REPORTS_TEXT}' => sanitizeStr(_T('report_admin_empty')),
			'{$MODULE_URL}' => sanitizeStr($this->modulePageUrl),
			'{$REPORTS}' => [],
		]);

		$moduleHeader .= $this->generateTemplate(
			'reportWindowTemplate',
			$this->moduleContext->adminPageRenderer->ParseBlock('REPORT_POST_REPORTS_PAGE', $blank)
		);

		$moduleHeader .= $this->generateTemplate(
			'reportWindowRowTemplate',
			$this->moduleContext->adminPageRenderer->ParseBlock('REPORT_POST_REPORT_ROW', array_merge($blank, [
				'{$STATUS_CLASS}' => '', '{$IS_PENDING}' => '1', '{$REPORT_ID}' => 0,
				'{$REPORTER_REASON}' => '', '{$REPORTER_IP}' => '', '{$IP_REPORTS_URL}' => '#',
				'{$DATE_REPORTED}' => '', '{$SHOW_STATUS}' => '',
				'{$PUBLIC_REASON}' => '', '{$PRIVATE_REASON}' => '',
				'{$ACTIONED_BY}' => '', '{$ACTIONED_AT}' => '',
				'{$ACTION_URL}' => '#', '{$VIEW_URL}' => '#',
			]))
		);
	}

	/**
	 * "Post has been reported" under a post on the live frontend, for staff who can act on it.
	 *
	 * The count rides along on the post itself (see the reports join in getBasePostQuery), so
	 * this costs no query per post.
	 */
	private function onRenderReportedNotice(string &$belowComment, Post $post, bool $adminMode): void {
		// Not on the module's own previews: on a report page every post is reported by
		// definition, and the link would point back at the page already being read.
		if ($this->renderingPreview) {
			return;
		}

		if (!$adminMode || $post->getPendingReportCount() < 1) {
			return;
		}

		$dataUrl = $this->getModulePageURL(
			['pageName' => 'postReportsApi', 'postUid' => $post->getUid()],
			false,
			true
		);

		// href is the real page so no-JS lands somewhere useful; reportAction.js prefers the
		// data URL and builds the window from templates instead of navigating.
		$linkHtml = '<a class="reportedPostLink" data-reports-url="' . sanitizeStr($dataUrl) . '" href="'
			. sanitizeStr($this->getPostReportsUrl($post->getUid())) . '">'
			. sanitizeStr(_T('report_post_reported_link', $post->getNumber())) . '</a>';

		$belowComment .= '<div class="reportedPostNotice">'
			. sanitizeStr(_T('report_post_reported_notice')) . ' ' . $linkHtml . '</div>';
	}

	/** "View reports" on each post, so a post's report history is reachable from the board. */
	private function onRenderPostWidget(array &$widgetArray, Post &$post): void {
		$widgetArray[] = $this->buildWidgetEntry(
			$this->getPostReportsUrl($post->getUid()),
			'viewReports',
			_T('report_widget_view_reports'),
			''
		);
	}

	// ─── Routing ──────────────────────────────────────────────────

	public function ModulePage(): void {
		$pageName = (string) $this->moduleContext->request->getParameter('pageName', 'GET', '');

		match ($pageName) {
			'notifications' => $this->handleNotificationsRequest(),
			'view' => $this->drawReportView(),
			'posts' => $this->drawReportedPosts(),
			'postReports' => $this->drawPostReports(),
			'ipReports' => $this->drawIpReports(),
			'postReportsApi' => $this->handlePostReportsApi(),
			'reportApi' => $this->handleReportApi(),
			'action' => $this->drawActionForm(),
			default => $this->drawReportQueue(),
		};
	}

	/**
	 * POST actions. abstractModuleAdmin routes POSTs here through dispatchModuleRequest(),
	 * which has already enforced the request method and CSRF token.
	 */
	protected function handleModuleRequest(): void {
		$request = $this->moduleContext->request;

		// The per-row [Dismiss all from IP] button names the report whose reporter to clear. A
		// submit button can only carry one name/value pair, so it spends it on the id and its
		// presence stands in for the action.
		if ((int) $request->getParameter('clearIpReportId', 'POST', 0) > 0) {
			$this->handleClearIp();
			return;
		}

		$action = (string) $request->getParameter('action', 'POST', '');

		match ($action) {
			'approve' => $this->handleDecision(reportStatus::APPROVED),
			'dismiss' => $this->handleDecision(reportStatus::DISMISSED),
			'clearPost' => $this->handleClearPost(),
			'clearIp' => $this->handleClearIp(),
			'markRead' => $this->handleMarkRead(),
			default => throw new BoardException(_T('report_error_unknown_action')),
		};
	}

	// ─── Actions ──────────────────────────────────────────────────

	/**
	 * Approve or dismiss the selected reports.
	 *
	 * Approving deletes the reported post, so it is gated on CAN_APPROVE_REPORT (and post
	 * deletion rights); dismissing is gated on CAN_DISMISS_REPORT, which by default janitors
	 * do not have.
	 */
	private function handleDecision(reportStatus $decision): void {
		$isApproval = $decision === reportStatus::APPROVED;

		if ($isApproval && !$this->reportPolicy->canApprove()) {
			throw new BoardException(_T('report_error_cannot_approve'));
		}

		if (!$isApproval && !$this->reportPolicy->canDismiss()) {
			throw new BoardException(_T('report_error_cannot_dismiss'));
		}

		$reportIds = $this->getSelectedReportIds();

		if (empty($reportIds)) {
			$this->redirectBack();
			return;
		}

		[$publicReason, $privateReason] = $this->getSubmittedReasons();

		$affected = $isApproval
			? $this->reportService->approveReports($reportIds, $this->getActorAccountId(), $publicReason, $privateReason)
			: $this->reportService->dismissReports($reportIds, $this->getActorAccountId(), $publicReason, $privateReason);

		if ($affected > 0) {
			$this->logAction(
				$isApproval
					? 'Approved ' . count($reportIds) . ' report(s), deleting ' . $affected . ' post(s)'
					: 'Dismissed ' . $affected . ' report(s)',
				$this->moduleContext->board->getBoardUID()
			);
		}

		$this->redirectBack();
	}

	/** Dismiss every pending report on one post at once. */
	private function handleClearPost(): void {
		if (!$this->reportPolicy->canClearPostReports()) {
			throw new BoardException(_T('report_error_cannot_clear'));
		}

		$postUid = (int) $this->moduleContext->request->getParameter('postUid', 'POST', 0);

		if ($postUid <= 0) {
			$this->redirectBack();
			return;
		}

		[$publicReason, $privateReason] = $this->getSubmittedReasons();

		// Give the reporters something to read when the moderator didn't type a reason.
		if ($publicReason === null) {
			$publicReason = _T('report_cleared_default_reason');
		}

		$cleared = $this->reportService->clearReportsForPost(
			$postUid,
			$this->getActorAccountId(),
			$publicReason,
			$privateReason
		);

		if ($cleared > 0) {
			$this->logAction(
				'Cleared ' . $cleared . ' report(s) on post UID ' . $postUid,
				$this->moduleContext->board->getBoardUID()
			);
		}

		$this->redirectBack();
	}

	/**
	 * Dismiss every pending report filed from one reporter's IP.
	 *
	 * The IP is identified by one of its reports rather than passed in the URL, so the address
	 * itself never has to travel in a link that staff without CAN_VIEW_IP_ADDRESSES could read.
	 */
	private function handleClearIp(): void {
		if (!$this->reportPolicy->canClearPostReports()) {
			throw new BoardException(_T('report_error_cannot_clear'));
		}

		// Named ipReportId by the IP page's bulk form, clearIpReportId by the per-row button.
		$request = $this->moduleContext->request;
		$reportId = (int) $request->getParameter('ipReportId', 'POST', 0)
			?: (int) $request->getParameter('clearIpReportId', 'POST', 0);

		$reporterIp = $this->resolveReporterIp($reportId);

		if ($reporterIp === null) {
			$this->redirectBack();
			return;
		}

		[$publicReason, $privateReason] = $this->getSubmittedReasons();

		if ($publicReason === null) {
			$publicReason = _T('report_cleared_default_reason');
		}

		$cleared = $this->reportService->clearReportsForIp(
			$reporterIp,
			$this->getActorAccountId(),
			$publicReason,
			$privateReason
		);

		if ($cleared > 0) {
			$this->logAction(
				'Cleared ' . $cleared . ' report(s) from reporter ' . $reporterIp,
				$this->moduleContext->board->getBoardUID()
			);
		}

		$this->redirectBack();
	}

	/** Called by reportAdmin.js once a notification has actually been shown. */
	private function handleMarkRead(): void {
		$reportIds = $this->getSelectedReportIds();

		if ($this->currentAccountId > 0 && !empty($reportIds)) {
			$this->reportService->markReportsRead($reportIds, $this->currentAccountId);
		}

		if ($this->moduleContext->request->isAjax()) {
			renderJsonPage(['success' => true]);
			return;
		}

		$this->redirectBack();
	}

	/**
	 * Unread pending reports from the configured window, for the browser notifications.
	 * Marking them read is left to the client so a report isn't silently swallowed when the
	 * poll fires but no notification is shown.
	 */
	private function handleNotificationsRequest(): void {
		if ($this->currentAccountId <= 0 || !$this->getModuleConfig('ENABLE_NOTIFICATIONS', true)) {
			renderPrivateJsonPage(['reports' => [], 'unreadCount' => 0]);
			return;
		}

		$windowMinutes = max(1, (int) $this->getModuleConfig('NOTIFICATION_WINDOW_MINUTES', 60));
		$reports = $this->reportService->getUnreadRecentForAccount($this->currentAccountId, $windowMinutes, 20);

		$payload = [];
		foreach ($reports as $report) {
			$payload[] = [
				'reportId' => (int) $report['report_id'],
				'postNumber' => (int) ($report['post_number'] ?? 0),
				'boardTitle' => (string) ($report['board_title'] ?? ''),
				'reason' => (string) ($report['reporter_reason'] ?? ''),
				'url' => $this->getModulePageURL(['pageName' => 'view', 'reportId' => (int) $report['report_id']], false, true),
			];
		}

		renderPrivateJsonPage([
			'reports' => $payload,
			'unreadCount' => $this->reportService->countUnreadForAccount($this->currentAccountId),
			'markReadUrl' => $this->modulePageUrl,
			'title' => _T('report_notification_title'),
		]);
	}

	/**
	 * One report, as data.
	 *
	 * Fills the [Action] window: the form template is cloned blank from the page head, so the
	 * report it is about has to arrive separately. Private, not cached — it names the reporter.
	 */
	private function handleReportApi(): void {
		$reportId = (int) $this->moduleContext->request->getParameter('reportId', 'GET', 0);
		$report = $reportId > 0 ? $this->reportService->getReportById($reportId) : null;

		if ($report === null) {
			renderPrivateJsonPage(['error' => true], 404);
			return;
		}

		// Opening the form is a moderator actually looking at the report.
		if ($this->currentAccountId > 0) {
			$this->reportService->markReportsRead([$reportId], $this->currentAccountId);
		}

		$status = reportStatus::fromValue($report['status']);
		$postNumber = (int) ($report['post_number'] ?? 0);

		renderPrivateJsonPage([
			'reportId' => $reportId,
			'postNumber' => $postNumber,
			'postUrl' => $this->getPostUrl((int) $report['board_uid'], $postNumber),
			'boardTitle' => (string) ($report['board_title'] ?? ''),
			'reason' => (string) ($report['reporter_reason'] ?? ''),
			// Server-built date markup, inserted as HTML by the client.
			'dateHtml' => $this->formatDate($report['date_reported'] ?? null),
			'statusLabel' => $status->label(),
			'isPending' => $status->isPending(),
		]);
	}

	/**
	 * The reports on one post, as data.
	 *
	 * Backs the window opened from the notice under a post: reportAction.js fills the
	 * REPORT_WINDOW templates with this rather than being handed rendered page HTML, so the
	 * markup still lives in template files and the window is not carrying a whole admin page.
	 *
	 * Private, not cached: the payload includes reporter IPs for staff cleared to see them.
	 */
	private function handlePostReportsApi(): void {
		$postUid = (int) $this->moduleContext->request->getParameter('postUid', 'GET', 0);

		if ($postUid <= 0) {
			renderPrivateJsonPage(['reports' => [], 'stats' => $this->reportService->getPostReportStats(0)]);
			return;
		}

		// Only what still needs a decision: the window is a prompt to act, not a history view.
		$reports = array_values(array_filter(
			$this->reportService->getReportsForPost($postUid),
			static fn(array $report): bool => reportStatus::fromValue($report['status'])->isPending()
		));

		// Deliberately does NOT mark anything read: the client preloads this for every reported
		// post on screen, so a fetch is no evidence a moderator looked. Opening the queue or a
		// report is what counts as reading.

		$payload = [];
		foreach ($reports as $report) {
			$status = reportStatus::fromValue($report['status']);
			$reportId = (int) $report['report_id'];

			$payload[] = [
				'reportId' => $reportId,
				'reason' => (string) ($report['reporter_reason'] ?? ''),
				'ip' => $this->maskIp((string) $report['reporter_ip']),
				// Server-built markup (date/weekday spans), inserted as HTML by the client.
				'dateHtml' => $this->formatDate($report['date_reported'] ?? null),
				'actionedAtHtml' => $this->formatDate($report['actioned_at'] ?? null),
				'statusLabel' => $status->label(),
				'statusClass' => $status->rowCssClass(),
				'isPending' => $status->isPending(),
				'actionedBy' => (string) ($report['actioned_by_username'] ?? ''),
				'publicReason' => (string) ($report['public_reason'] ?? ''),
				'privateReason' => (string) ($report['private_reason'] ?? ''),
				'ipReportsUrl' => $this->getIpReportsUrl($reportId),
				'actionUrl' => $this->getActionUrl($reportId),
				'actionDataUrl' => $this->getReportApiUrl($reportId),
				'viewUrl' => $this->getModulePageURL(['pageName' => 'view', 'reportId' => $reportId], false, true),
			];
		}

		renderPrivateJsonPage([
			'stats' => $this->reportService->getPostReportStats($postUid),
			'reports' => $payload,
		]);
	}

	// ─── Pages ────────────────────────────────────────────────────

	/** The queue: every report, newest first, filterable by status and board. */
	private function drawReportQueue(): void {
		$request = $this->moduleContext->request;

		[$statusFilter, $statusParam] = $this->getStatusFilter();
		$boardFilter = $this->getBoardFilter();
		$page = max(1, (int) $request->getParameter('page', 'GET', 1));
		$offset = ($page - 1) * $this->reportsPerPage;

		$reports = $this->reportService->getReportsPaged($this->reportsPerPage, $offset, $statusFilter, $boardFilter);
		$totalReports = $this->reportService->countReports($statusFilter, $boardFilter);

		// Seeing a report in the queue counts as having read it, which clears the nav badge.
		$this->markListedReportsRead($reports);
		$this->preloadPreviews($reports);

		// A table filtered to one status doesn't need to repeat it on every row, and nothing on
		// the awaiting-review filter has been actioned, so that column would be empty throughout.
		$showStatus = $statusFilter === null;
		$showActionedBy = $statusFilter !== reportStatus::PENDING->value;

		$rows = [];
		foreach ($reports as $report) {
			$rows[] = $this->buildReportRow($report, $showStatus, $showActionedBy);
		}

		$queueUrl = $this->buildQueueUrl($statusParam, $boardFilter);

		$contentHtml = $this->moduleContext->adminPageRenderer->ParseBlock('REPORT_ADMIN_PAGE', array_merge(
			$this->getSharedTemplateValues(),
			[
				'{$PAGE_TITLE}' => sanitizeStr(_T('report_admin_title')),
				'{$MODULE_URL}' => sanitizeStr($this->modulePageUrl),
				'{$REPORTED_POSTS_URL}' => sanitizeStr($this->reportedPostsUrl),
				'{$REPORTED_POSTS_TEXT}' => sanitizeStr(_T('report_reported_posts_title')),
				'{$FILTER_HTML}' => $this->renderStatusFilter($statusParam),
				'{$TH_DECISION}' => sanitizeStr(_T($showStatus ? 'report_th_status' : 'report_th_decision_reasons')),
				'{$SHOW_ACTIONED_BY}' => $showActionedBy ? '1' : '',
				'{$DECISION_FORM}' => $this->renderDecisionForm(),
				'{$REPORTS}' => $rows,
				'{$HAS_REPORTS}' => empty($rows) ? '' : '1',
				'{$NO_REPORTS_TEXT}' => sanitizeStr(_T('report_admin_empty')),
			]
		));

		$this->outputAdminPage($contentHtml, drawPager($this->reportsPerPage, $totalReports, $queueUrl, $request));
	}

	/** One report in full, with the post it points at and the forms to act on it. */
	private function drawReportView(): void {
		$reportId = (int) $this->moduleContext->request->getParameter('reportId', 'GET', 0);
		$report = $reportId > 0 ? $this->reportService->getReportById($reportId) : null;

		if ($report === null) {
			throw new BoardException(_T('report_error_not_found'));
		}

		if ($this->currentAccountId > 0) {
			$this->reportService->markReportsRead([$reportId], $this->currentAccountId);
		}

		$postUid = (int) $report['post_uid'];
		$status = reportStatus::fromValue($report['status']);
		$stats = $this->reportService->getPostReportStats($postUid);

		$this->reportPostPreview->preloadQuoteLinks([$postUid]);

		$contentHtml = $this->moduleContext->adminPageRenderer->ParseBlock('REPORT_VIEW', array_merge(
			$this->getSharedTemplateValues(),
			$this->getReportDetailValues($report, $status),
			$this->getStatsTemplateValues($stats),
			[
				'{$PAGE_TITLE}' => sanitizeStr(_T('report_view_title', (int) $report['report_id'])),
				'{$MODULE_URL}' => sanitizeStr($this->modulePageUrl),
				'{$TH_STATS}' => sanitizeStr(_T('report_th_stats')),
				'{$DECISION_FORM}' => $this->renderDecisionForm(),
				'{$BACK_URL}' => sanitizeStr($this->modulePageUrl),
				'{$BACK_TEXT}' => sanitizeStr(_T('report_back_to_queue')),
				'{$POST_PREVIEW}' => $this->renderPostPreview($postUid),
				'{$POST_REPORTS_URL}' => sanitizeStr($this->getPostReportsUrl($postUid)),
				'{$POST_REPORTS_TEXT}' => sanitizeStr(_T('report_view_post_stats')),
			]
		));

		$this->outputAdminPage($contentHtml);
	}

	/** Every post that has ever been reported, with its tallies. */
	private function drawReportedPosts(): void {
		$request = $this->moduleContext->request;

		$page = max(1, (int) $request->getParameter('page', 'GET', 1));
		$offset = ($page - 1) * $this->reportsPerPage;

		$reportedPosts = $this->reportService->getReportedPostsPaged($this->reportsPerPage, $offset);
		$totalReportedPosts = $this->reportService->countReportedPosts();

		$this->preloadPreviews($reportedPosts);

		$rows = [];
		foreach ($reportedPosts as $reportedPost) {
			$postUid = (int) $reportedPost['post_uid'];

			$rows[] = array_merge($this->getSharedTemplateValues(), [
				'{$POST_UID}' => $postUid,
				'{$POST_NUMBER}' => sanitizeStr((string) ($reportedPost['post_number'] ?? '?')),
				'{$POST_URL}' => sanitizeStr($this->getPostUrl((int) $reportedPost['board_uid'], (int) ($reportedPost['post_number'] ?? 0))),
				'{$BOARD_TITLE}' => sanitizeStr((string) ($reportedPost['board_title'] ?? '')),
				'{$REPORT_COUNT}' => (int) $reportedPost['report_count'],
				'{$PENDING_COUNT}' => (int) $reportedPost['pending_count'],
				'{$APPROVED_COUNT}' => (int) $reportedPost['approved_count'],
				'{$DISMISSED_COUNT}' => (int) $reportedPost['dismissed_count'],
				'{$LAST_REPORTED}' => $this->formatDate($reportedPost['last_reported'] ?? null),
				'{$POST_REPORTS_URL}' => sanitizeStr($this->getPostReportsUrl($postUid)),
				'{$POST_PREVIEW}' => $this->renderPostPreview($postUid),
			]);
		}

		$contentHtml = $this->moduleContext->adminPageRenderer->ParseBlock('REPORT_POSTS_PAGE', array_merge(
			$this->getSharedTemplateValues(),
			[
				'{$PAGE_TITLE}' => sanitizeStr(_T('report_reported_posts_title')),
				'{$HEADING_REPORTED_POSTS}' => sanitizeStr(_T('report_heading_reported_posts')),
				'{$TH_REPORT_COUNT}' => sanitizeStr(_T('report_th_report_count')),
				'{$TH_PENDING_COUNT}' => sanitizeStr(_T('report_th_pending_count')),
				'{$TH_APPROVED_COUNT}' => sanitizeStr(_T('report_th_approved_count')),
				'{$TH_DISMISSED_COUNT}' => sanitizeStr(_T('report_th_dismissed_count')),
				'{$TH_LAST_REPORTED}' => sanitizeStr(_T('report_th_last_reported')),
				'{$QUEUE_URL}' => sanitizeStr($this->modulePageUrl),
				'{$QUEUE_TEXT}' => sanitizeStr(_T('report_back_to_queue')),
				'{$TOTAL_REPORTED_POSTS}' => $totalReportedPosts,
				'{$TOTAL_TEXT}' => sanitizeStr(_T('report_reported_posts_total', $totalReportedPosts)),
				'{$POSTS}' => $rows,
				'{$HAS_POSTS}' => empty($rows) ? '' : '1',
				'{$NO_POSTS_TEXT}' => sanitizeStr(_T('report_reported_posts_empty')),
				'{$VIEW_TEXT}' => sanitizeStr(_T('report_view_link')),
			]
		));

		$this->outputAdminPage($contentHtml, drawPager($this->reportsPerPage, $totalReportedPosts, $this->reportedPostsUrl, $request));
	}

	/**
	 * Every report filed from one reporter's IP, reached by clicking an IP in any report table.
	 *
	 * The IP is taken from the report that was clicked rather than the URL — see
	 * getIpReportsUrl() — so it stays out of links that staff who may not see IPs can read.
	 */
	private function drawIpReports(): void {
		$request = $this->moduleContext->request;

		$reportId = (int) $request->getParameter('reportId', 'GET', 0);
		$reporterIp = $this->resolveReporterIp($reportId);

		if ($reporterIp === null) {
			throw new BoardException(_T('report_error_not_found'));
		}

		$page = max(1, (int) $request->getParameter('page', 'GET', 1));
		$offset = ($page - 1) * $this->reportsPerPage;

		$reports = $this->reportService->getReportsByIp($reporterIp, $this->reportsPerPage, $offset);
		$totalReports = $this->reportService->countReportsByIp($reporterIp);

		$this->markListedReportsRead($reports);
		$this->preloadPreviews($reports);

		$rows = [];
		foreach ($reports as $report) {
			$rows[] = $this->buildReportRow($report);
		}

		$maskedIp = $this->maskIp($reporterIp);
		$ipReportsUrl = $this->getIpReportsUrl($reportId);

		$contentHtml = $this->moduleContext->adminPageRenderer->ParseBlock('REPORT_IP_REPORTS_PAGE', array_merge(
			$this->getSharedTemplateValues(),
			$this->getIpStatsTemplateValues($reporterIp),
			[
				'{$PAGE_TITLE}' => sanitizeStr(_T('report_ip_reports_title', $maskedIp)),
				'{$HEADING_REPORTS}' => sanitizeStr(_T('report_heading_reports_from_ip')),
				'{$TH_DECISION}' => sanitizeStr(_T('report_th_status')),
				'{$MODULE_URL}' => sanitizeStr($this->modulePageUrl),
				'{$QUEUE_URL}' => sanitizeStr($this->modulePageUrl),
				'{$QUEUE_TEXT}' => sanitizeStr(_T('report_back_to_queue')),
				'{$REPORT_ID}' => $reportId,
				'{$REPORTER_IP}' => sanitizeStr($maskedIp),
				'{$DECISION_FORM}' => $this->renderDecisionForm(
					'clearIp',
					_T('report_clear_ip'),
					_T('report_clear_ip_hint')
				),
				'{$REPORTS}' => $rows,
				'{$HAS_REPORTS}' => empty($rows) ? '' : '1',
				'{$NO_REPORTS_TEXT}' => sanitizeStr(_T('report_admin_empty')),
			]
		));

		$this->outputAdminPage($contentHtml, drawPager($this->reportsPerPage, $totalReports, $ipReportsUrl, $request));
	}

	/**
	 * The approve/dismiss form for a single report.
	 *
	 * Laid out like the reader's report form — the post, who reported it and why, then the two
	 * reasons — so acting on a report reads the same way filing one does. reportAction.js opens
	 * the same markup in a window from the report tables; this page is what no-JS lands on.
	 */
	private function drawActionForm(): void {
		$reportId = (int) $this->moduleContext->request->getParameter('reportId', 'GET', 0);
		$report = $reportId > 0 ? $this->reportService->getReportById($reportId) : null;

		if ($report === null) {
			throw new BoardException(_T('report_error_not_found'));
		}

		if ($this->currentAccountId > 0) {
			$this->reportService->markReportsRead([$reportId], $this->currentAccountId);
		}

		$postUid = (int) $report['post_uid'];
		$this->reportPostPreview->preloadQuoteLinks([$postUid]);

		$contentHtml = $this->moduleContext->adminPageRenderer->ParseBlock(
			'REPORT_ACTION_FORM',
			$this->buildActionFormValues($report, $this->renderPostPreview($postUid), getCsrfHiddenInput())
		);

		$this->outputAdminPage($contentHtml);
	}

	/**
	 * Values for REPORT_ACTION_FORM.
	 *
	 * Filled in twice: populated for the page, and blank for the <template> reportAction.js
	 * clones into its window.
	 */
	private function buildActionFormValues(?array $report, string $postPreviewHtml, string $csrfInput): array {
		$postNumber = (int) ($report['post_number'] ?? 0);
		$boardUid = (int) ($report['board_uid'] ?? 0);

		return array_merge($this->getSharedTemplateValues(), [
			'{$FORM_TITLE}' => sanitizeStr(_T('report_action_title')),
			'{$MODULE_URL}' => sanitizeStr($this->modulePageUrl),
			'{$CSRF_TOKEN}' => $csrfInput,
			'{$REPORT_ID}' => (int) ($report['report_id'] ?? 0),
			'{$POST_NUMBER}' => $postNumber ?: '',
			'{$POST_URL}' => sanitizeStr($this->getPostUrl($boardUid, $postNumber)),
			'{$BOARD_TITLE}' => sanitizeStr((string) ($report['board_title'] ?? '')),
			'{$REPORTER_REASON}' => newLinesToBreakLines(sanitizeStr((string) ($report['reporter_reason'] ?? ''))),
			'{$DATE_REPORTED}' => $this->formatDate($report['date_reported'] ?? null),
			'{$POST_PREVIEW}' => $postPreviewHtml,
		]);
	}

	/** One post's report history: the post, its tallies, and every report filed against it. */
	private function drawPostReports(): void {
		$postUid = (int) $this->moduleContext->request->getParameter('postUid', 'GET', 0);

		if ($postUid <= 0) {
			throw new BoardException(_T('report_error_post_not_found'));
		}

		$reports = $this->reportService->getReportsForPost($postUid);
		$stats = $this->reportService->getPostReportStats($postUid);

		$this->markListedReportsRead($reports);
		$this->reportPostPreview->preloadQuoteLinks([$postUid]);

		// The reports already carry the post number; only fall back to a lookup when the post
		// has no reports left to read it off.
		$postNumber = $reports[0]['post_number']
			?? $this->moduleContext->postRepository->resolvePostNumberFromUID($postUid);

		$rows = [];
		foreach ($reports as $report) {
			$rows[] = $this->buildReportRow($report);
		}

		$contentHtml = $this->moduleContext->adminPageRenderer->ParseBlock('REPORT_POST_REPORTS_PAGE', array_merge(
			$this->getSharedTemplateValues(),
			$this->getStatsTemplateValues($stats),
			[
				'{$PAGE_TITLE}' => sanitizeStr(_T('report_post_reports_title', (string) ($postNumber ?? '?'))),
				'{$HEADING_REPORTS}' => sanitizeStr(_T('report_heading_reports_on_post')),
				'{$TH_DECISION}' => sanitizeStr(_T('report_th_status')),
				'{$MODULE_URL}' => sanitizeStr($this->modulePageUrl),
				'{$QUEUE_URL}' => sanitizeStr($this->modulePageUrl),
				'{$QUEUE_TEXT}' => sanitizeStr(_T('report_back_to_queue')),
				'{$POST_UID}' => $postUid,
				'{$POST_PREVIEW}' => $this->renderPostPreview($postUid),
				'{$DECISION_FORM}' => $this->renderDecisionForm(
					'clearPost',
					_T('report_clear_post'),
					_T('report_clear_post_hint')
				),
				'{$REPORTS}' => $rows,
				'{$HAS_REPORTS}' => empty($rows) ? '' : '1',
				'{$NO_REPORTS_TEXT}' => sanitizeStr(_T('report_admin_empty')),
			]
		));

		$this->outputAdminPage($contentHtml);
	}

	// ─── Template value builders ──────────────────────────────────

	/**
	 * Capability flags and labels every report template needs.
	 *
	 * FOREACH rows are parsed against their own value array only — they inherit nothing from
	 * the page around them — so these get merged into every row as well as the page, which is
	 * why the result is memoised.
	 */
	private function getSharedTemplateValues(): array {
		if ($this->sharedTemplateValues !== null) {
			return $this->sharedTemplateValues;
		}

		return $this->sharedTemplateValues = [
			'{$CSRF_TOKEN}' => getCsrfHiddenInput(),
			'{$CAN_APPROVE}' => $this->reportPolicy->canApprove() ? '1' : '',
			'{$CAN_DISMISS}' => $this->reportPolicy->canDismiss() ? '1' : '',
			'{$CAN_CLEAR}' => $this->reportPolicy->canClearPostReports() ? '1' : '',
			'{$CAN_VIEW_IP}' => $this->reportPolicy->canViewIpAddresses() ? '1' : '',
			'{$APPROVE_TEXT}' => sanitizeStr(_T('report_approve')),
			'{$APPROVE_HINT}' => sanitizeStr(_T('report_approve_hint')),
			'{$DISMISS_TEXT}' => sanitizeStr(_T('report_dismiss')),
			'{$DISMISS_HINT}' => sanitizeStr(_T('report_dismiss_hint')),
			'{$VIEW_TEXT}' => sanitizeStr(_T('report_view_link')),
			'{$IP_REPORTS_TEXT}' => sanitizeStr(_T('report_ip_reports_link')),
			'{$ACTION_TEXT}' => sanitizeStr(_T('report_action_link')),
			'{$CLEAR_IP_TEXT}' => sanitizeStr(_T('report_clear_ip_row')),
			'{$CLEAR_IP_HINT}' => sanitizeStr(_T('report_clear_ip_hint')),
			'{$TH_REPORTER_REASON}' => sanitizeStr(_T('report_th_reporter_reason')),
			'{$TH_POST_NUMBER}' => sanitizeStr(_T('report_th_post_number')),
			'{$SHOW_STATUS}' => '1',
			'{$SHOW_ACTIONED_BY}' => '1',
			'{$HEADING_TOTALS}' => sanitizeStr(_T('report_heading_totals')),
			'{$HEADING_DETAILS}' => sanitizeStr(_T('report_heading_details')),
			'{$HEADING_POST}' => sanitizeStr(_T('report_heading_post')),
			'{$HEADING_REPORTS}' => sanitizeStr(_T('report_heading_reports')),
			'{$PUBLIC_REASON_LABEL}' => sanitizeStr(_T('report_public_reason_label')),
			'{$PUBLIC_REASON_HINT}' => sanitizeStr(_T('report_public_reason_hint')),
			'{$PRIVATE_REASON_LABEL}' => sanitizeStr(_T('report_private_reason_label')),
			'{$PRIVATE_REASON_HINT}' => sanitizeStr(_T('report_private_reason_hint')),
			'{$TH_SELECT}' => '',
			'{$SELECT_DISABLED_TITLE}' => sanitizeStr(_T('report_select_disabled_title')),
			'{$TH_POST}' => sanitizeStr(_T('report_th_post')),
			'{$TH_PREVIEW}' => sanitizeStr(_T('report_th_preview')),
			'{$TH_BOARD}' => sanitizeStr(_T('report_th_board')),
			'{$TH_REASON}' => sanitizeStr(_T('report_th_reporter_reason')),
			'{$TH_IP}' => sanitizeStr(_T('report_th_ip')),
			'{$TH_DATE}' => sanitizeStr(_T('report_th_date')),
			'{$TH_STATUS}' => sanitizeStr(_T('report_th_status')),
			'{$TH_ACTIONED_BY}' => sanitizeStr(_T('report_th_actioned_by')),
			'{$TH_ACTIONS}' => sanitizeStr(_T('report_th_actions')),
		];
	}

	/** One row of the report table. */
	/**
	 * One row of a report table.
	 *
	 * @param bool $showStatus     Whether the decision column names the status. False on a table
	 *                             already filtered to one status, where the label would just
	 *                             repeat the filter — the reasons get the column instead.
	 * @param bool $showActionedBy Whether to render the actioned-by column at all. False on the
	 *                             awaiting-review filter, where nothing has been actioned yet so
	 *                             every cell would be blank.
	 */
	private function buildReportRow(array $report, bool $showStatus = true, bool $showActionedBy = true): array {
		$status = reportStatus::fromValue($report['status']);
		$postUid = (int) $report['post_uid'];
		$reportId = (int) $report['report_id'];

		return array_merge($this->getSharedTemplateValues(), [
			'{$REPORT_ID}' => $reportId,
			'{$POST_UID}' => $postUid,
			'{$POST_NUMBER}' => sanitizeStr((string) ($report['post_number'] ?? '?')),
			'{$POST_URL}' => sanitizeStr($this->getPostUrl((int) $report['board_uid'], (int) ($report['post_number'] ?? 0))),
			'{$BOARD_TITLE}' => sanitizeStr((string) ($report['board_title'] ?? '')),
			'{$REPORTER_REASON}' => newLinesToBreakLines(sanitizeStr((string) ($report['reporter_reason'] ?? ''))),
			'{$REPORTER_IP}' => sanitizeStr($this->maskIp((string) $report['reporter_ip'])),
			'{$IP_REPORTS_URL}' => sanitizeStr($this->getIpReportsUrl((int) $report['report_id'])),
			'{$DATE_REPORTED}' => $this->formatDate($report['date_reported'] ?? null),
			'{$STATUS_LABEL}' => sanitizeStr($status->label()),
			'{$STATUS_CLASS}' => $status->rowCssClass(),
			'{$IS_PENDING}' => $status->isPending() ? '1' : '',
			'{$SHOW_STATUS}' => $showStatus ? '1' : '',
			'{$SHOW_ACTIONED_BY}' => $showActionedBy ? '1' : '',
			'{$ACTION_URL}' => sanitizeStr($this->getActionUrl($reportId)),
			'{$ACTION_DATA_URL}' => sanitizeStr($this->getReportApiUrl($reportId)),
			'{$ACTIONED_BY}' => sanitizeStr((string) ($report['actioned_by_username'] ?? '')),
			'{$ACTIONED_AT}' => $this->formatDate($report['actioned_at'] ?? null),
			'{$PUBLIC_REASON}' => newLinesToBreakLines(sanitizeStr((string) ($report['public_reason'] ?? ''))),
			'{$PRIVATE_REASON}' => newLinesToBreakLines(sanitizeStr((string) ($report['private_reason'] ?? ''))),
			'{$VIEW_URL}' => sanitizeStr($this->getModulePageURL(['pageName' => 'view', 'reportId' => $reportId], false, true)),
			'{$POST_REPORTS_URL}' => sanitizeStr($this->getPostReportsUrl($postUid)),
			'{$POST_REPORTS_TEXT}' => sanitizeStr(_T('report_view_post_stats')),
			'{$POST_PREVIEW}' => $this->renderPostPreview($postUid),
		]);
	}

	/** The scalar fields of a single report, for the detail view. */
	private function getReportDetailValues(array $report, reportStatus $status): array {
		return [
			'{$REPORT_ID}' => (int) $report['report_id'],
			'{$POST_UID}' => (int) $report['post_uid'],
			'{$POST_NUMBER}' => sanitizeStr((string) ($report['post_number'] ?? '?')),
			'{$POST_URL}' => sanitizeStr($this->getPostUrl((int) $report['board_uid'], (int) ($report['post_number'] ?? 0))),
			'{$BOARD_TITLE}' => sanitizeStr((string) ($report['board_title'] ?? '')),
			'{$REPORTER_REASON}' => newLinesToBreakLines(sanitizeStr((string) ($report['reporter_reason'] ?? ''))),
			'{$REPORTER_IP}' => sanitizeStr($this->maskIp((string) $report['reporter_ip'])),
			'{$IP_REPORTS_URL}' => sanitizeStr($this->getIpReportsUrl((int) $report['report_id'])),
			'{$DATE_REPORTED}' => $this->formatDate($report['date_reported'] ?? null),
			'{$STATUS_LABEL}' => sanitizeStr($status->label()),
			'{$STATUS_CLASS}' => $status->rowCssClass(),
			'{$IS_PENDING}' => $status->isPending() ? '1' : '',
			'{$ACTIONED_BY}' => sanitizeStr((string) ($report['actioned_by_username'] ?? '')),
			'{$ACTIONED_AT}' => $this->formatDate($report['actioned_at'] ?? null),
			'{$PUBLIC_REASON}' => newLinesToBreakLines(sanitizeStr((string) ($report['public_reason'] ?? ''))),
			'{$PRIVATE_REASON}' => newLinesToBreakLines(sanitizeStr((string) ($report['private_reason'] ?? ''))),
		];
	}

	/**
	 * Report tallies, rendered as a small postlists table rather than a sentence so the
	 * breakdown reads down the page instead of running together on one line.
	 */
	private function getStatsTemplateValues(array $stats): array {
		$counts = [
			'{$REPORT_COUNT}' => $stats['report_count'] ?? 0,
			'{$PENDING_COUNT}' => $stats['pending_count'] ?? 0,
			'{$APPROVED_COUNT}' => $stats['approved_count'] ?? 0,
			'{$DISMISSED_COUNT}' => $stats['dismissed_count'] ?? 0,
		];

		return $counts + ['{$STATS_TABLE}' => $this->renderStatsTable($stats)];
	}

	/** The tallies table on its own, so the reports window can clone a blank one. */
	private function renderStatsTable(array $stats): string {
		return $this->moduleContext->adminPageRenderer->ParseBlock('REPORT_STATS_TABLE', [
			'{$REPORT_COUNT}' => $stats['report_count'] ?? '',
			'{$PENDING_COUNT}' => $stats['pending_count'] ?? '',
			'{$APPROVED_COUNT}' => $stats['approved_count'] ?? '',
			'{$DISMISSED_COUNT}' => $stats['dismissed_count'] ?? '',
			'{$TH_STAT}' => sanitizeStr(_T('report_th_stat')),
			'{$TH_COUNT}' => sanitizeStr(_T('report_th_count')),
			'{$LABEL_TOTAL}' => sanitizeStr(_T('report_stat_total')),
			'{$LABEL_PENDING}' => sanitizeStr(_T('report_status_pending')),
			'{$LABEL_APPROVED}' => sanitizeStr(_T('report_status_approved')),
			'{$LABEL_DISMISSED}' => sanitizeStr(_T('report_status_dismissed')),
		]);
	}

	/** Stats table for everything filed from one reporter's IP. */
	private function getIpStatsTemplateValues(string $reporterIp): array {
		return $this->getStatsTemplateValues($this->reportService->getIpReportStats($reporterIp));
	}

	/**
	 * The one action block every report page uses: the two reason fields plus the buttons that
	 * act on them.
	 *
	 * Approving, dismissing and clearing all take the same public/private reason, so they share
	 * a single form and differ only in which submit button was pressed — rather than the page
	 * carrying two near-identical reason forms in different places.
	 *
	 * Rendered as its own block and injected as a placeholder rather than nested inside the
	 * page templates' conditionals — the template compiler lifts FOREACH and IF separately, so
	 * an IF written inside another IF's branch is not something to rely on.
	 *
	 * @param string|null $clearAction Action name for the bulk-dismiss button ('clearPost' or
	 *                                 'clearIp'), or null on pages with nothing to clear.
	 * @param string      $clearText   Label for that button.
	 * @param string      $clearHint   Tooltip for that button.
	 */
	private function renderDecisionForm(
		?string $clearAction = null,
		string $clearText = '',
		string $clearHint = ''
	): string {
		$showClear = $clearAction !== null && $this->reportPolicy->canClearPostReports();

		return $this->moduleContext->adminPageRenderer->ParseBlock('REPORT_DECISION_FORM', array_merge(
			$this->getSharedTemplateValues(),
			[
				'{$DECISION_HEADING}' => sanitizeStr(_T('report_decision_heading')),
				'{$DECISION_LEGEND}' => sanitizeStr(_T('report_decision_legend')),
				'{$SHOW_CLEAR}' => $showClear ? '1' : '',
				'{$CLEAR_ACTION}' => sanitizeStr((string) $clearAction),
				'{$CLEAR_TEXT}' => sanitizeStr($clearText),
				'{$CLEAR_HINT}' => sanitizeStr($clearHint),
			]
		));
	}

	/** Status filter links above the queue. */
	private function renderStatusFilter(string $activeStatus): string {
		// Awaiting review leads because it is the default view; All is the way out of it.
		$options = [
			['status' => (string) reportStatus::PENDING->value, 'label' => _T('report_filter_pending')],
			['status' => (string) reportStatus::APPROVED->value, 'label' => _T('report_filter_approved')],
			['status' => (string) reportStatus::DISMISSED->value, 'label' => _T('report_filter_dismissed')],
			['status' => self::STATUS_FILTER_ALL, 'label' => _T('report_filter_all')],
		];

		$links = [];
		foreach ($options as $option) {
			$url = $this->getModulePageURL(['status' => $option['status']], false, true);
			$activeClass = $activeStatus === $option['status'] ? ' reportFilterActive' : '';

			$links[] = '<a class="reportFilterLink' . $activeClass . '" href="' . sanitizeStr($url) . '">'
				. sanitizeStr($option['label']) . '</a>';
		}

		return '<div class="reportFilters">' . implode(' ', $links) . '</div>';
	}

	// ─── Helpers ──────────────────────────────────────────────────

	/** Report ids from a checkbox list or a single-report form. */
	private function getSelectedReportIds(): array {
		$request = $this->moduleContext->request;

		$selected = $request->getParameter('reportIds', 'POST', []);
		$reportIds = is_array($selected) ? array_map('intval', $selected) : [];

		$singleReportId = (int) $request->getParameter('reportId', 'POST', 0);
		if ($singleReportId > 0) {
			$reportIds[] = $singleReportId;
		}

		return array_values(array_unique(array_filter($reportIds, static fn(int $id): bool => $id > 0)));
	}

	/**
	 * The two optional decision reasons.
	 *
	 * @return array{0: string|null, 1: string|null} Public reason, then staff-only reason.
	 */
	private function getSubmittedReasons(): array {
		$request = $this->moduleContext->request;

		$publicReason = trim((string) $request->getParameter('publicReason', 'POST', ''));
		$privateReason = trim((string) $request->getParameter('privateReason', 'POST', ''));

		return [
			$publicReason === '' ? null : $publicReason,
			$privateReason === '' ? null : $privateReason,
		];
	}

	/** Mark the pending reports in a result set as read for the current moderator. */
	private function markListedReportsRead(array $reports): void {
		if ($this->currentAccountId <= 0) {
			return;
		}

		$pendingIds = [];
		foreach ($reports as $report) {
			if (reportStatus::fromValue($report['status'])->isPending()) {
				$pendingIds[] = (int) $report['report_id'];
			}
		}

		$this->reportService->markReportsRead($pendingIds, $this->currentAccountId);
	}

	/**
	 * Which reports the queue lists.
	 *
	 * Defaults to the ones still awaiting review: that is the work, and an unfiltered list is
	 * mostly history that buries it. "?status=all" opts back into every status.
	 *
	 * @return array{0: ?int, 1: string} The reportStatus value to filter on (null for every
	 *         status), and the parameter to put back into links so the pager and the filter
	 *         links keep the current view.
	 */
	private function getStatusFilter(): array {
		$requestedStatus = trim((string) $this->moduleContext->request->getParameter('status', 'GET', ''));

		if ($requestedStatus === self::STATUS_FILTER_ALL) {
			return [null, self::STATUS_FILTER_ALL];
		}

		// An absent or unrecognised value lands on the default rather than silently widening
		// the queue to everything.
		$status = $requestedStatus === ''
			? reportStatus::PENDING
			: (reportStatus::tryFrom((int) $requestedStatus) ?? reportStatus::PENDING);

		return [$status->value, (string) $status->value];
	}

	private function getBoardFilter(): ?int {
		$boardUid = (int) $this->moduleContext->request->getParameter('boardUid', 'GET', 0);

		return $boardUid > 0 ? $boardUid : null;
	}

	/** Queue URL carrying the active filters, so the pager doesn't drop them. */
	private function buildQueueUrl(string $statusParam, ?int $boardFilter): string {
		// Always carried, even when it matches the default — the pager builds its links from
		// this URL and page 2 must not quietly fall back to a different filter.
		$parameters = ['status' => $statusParam];

		if ($boardFilter !== null) {
			$parameters['boardUid'] = $boardFilter;
		}

		return $this->getModulePageURL($parameters, false, true);
	}

	/** The single-report approve/dismiss form. */
	/** The data behind the [Action] window. */
	private function getReportApiUrl(int $reportId): string {
		return $this->getModulePageURL(['pageName' => 'reportApi', 'reportId' => $reportId], false, true);
	}

	private function getActionUrl(int $reportId): string {
		return $this->getModulePageURL(['pageName' => 'action', 'reportId' => $reportId], false, true);
	}

	private function getPostReportsUrl(int $postUid): string {
		return $this->getModulePageURL(['pageName' => 'postReports', 'postUid' => $postUid], false, true);
	}

	/**
	 * Link to every report from the same reporter as the given report.
	 *
	 * Keyed on the report rather than the address: staff below CAN_VIEW_IP_ADDRESSES only ever
	 * see a hash of the IP, and putting the real one in the href would hand it back to them.
	 */
	private function getIpReportsUrl(int $reportId): string {
		return $this->getModulePageURL(['pageName' => 'ipReports', 'reportId' => $reportId], false, true);
	}

	/** The reporter IP behind a report id, or null when the report doesn't exist. */
	private function resolveReporterIp(int $reportId): ?string {
		if ($reportId <= 0) {
			return null;
		}

		$report = $this->reportService->getReportById($reportId);

		if ($report === null || ($report['reporter_ip'] ?? '') === '') {
			return null;
		}

		return (string) $report['reporter_ip'];
	}

	private function getPostUrl(int $boardUid, int $postNumber): string {
		$postBoard = searchBoardArrayForBoard($boardUid);

		if ($postBoard === null || $postNumber === 0) {
			return '';
		}

		return $postBoard->getBoardThreadURL($postNumber);
	}

	/**
	 * Render the reported post. Deleted posts are still shown — an approved report's whole
	 * point is that the post is gone, and staff need to see what they acted on.
	 */
	private function renderPostPreview(int $postUid): string {
		$post = $this->postCache[$postUid] ??= $this->moduleContext->postRepository->getPostByUid($postUid, true);

		if (!$post) {
			return '<div class="reportMissingPost">' . sanitizeStr(_T('report_post_unavailable')) . '</div>';
		}

		// Flagged for the duration so the BelowComment listener above can tell this render apart
		// from a post being drawn on the board. finally, because a render that throws must not
		// leave the flag set for the rest of the request.
		$this->renderingPreview = true;

		try {
			return $this->reportPostPreview->render($post, true);
		} finally {
			$this->renderingPreview = false;
		}
	}

	/**
	 * Fetch the quote links for every post about to be previewed in one query instead of one
	 * per row.
	 *
	 * @param array  $rows      Report or reported-post rows.
	 * @param string $uidColumn Column holding the post UID in those rows.
	 */
	private function preloadPreviews(array $rows, string $uidColumn = 'post_uid'): void {
		$this->reportPostPreview->preloadQuoteLinks(
			array_map(static fn(array $row): int => (int) $row[$uidColumn], $rows)
		);
	}

	/**
	 * The account to record against a moderator decision.
	 *
	 * actioned_by is a foreign key onto the accounts table, so an absent session has to become
	 * NULL rather than the 0 that casting a missing user id produces.
	 */
	private function getActorAccountId(): ?int {
		return $this->currentAccountId > 0 ? $this->currentAccountId : null;
	}

	/** Full IP for staff cleared to see them, a short hash for everyone else. */
	private function maskIp(string $ip): string {
		if ($ip === '') {
			return '';
		}

		return $this->reportPolicy->canViewIpAddresses() ? $ip : substr(md5($ip), 0, 8);
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

	private function outputAdminPage(string $contentHtml, string $pagerHtml = ''): void {
		echo $this->moduleContext->adminPageRenderer->ParsePage('GLOBAL_ADMIN_PAGE_CONTENT', [
			'{$PAGE_CONTENT}' => $contentHtml,
			'{$PAGER}' => $pagerHtml,
		], true);
	}

	private function redirectBack(): void {
		redirect($this->moduleContext->request->getReferer($this->modulePageUrl));
		exit;
	}
}
