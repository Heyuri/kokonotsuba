<?php

namespace Kokonotsuba\Modules\adminBan;

use Kokonotsuba\action_log\actionType;
use Kokonotsuba\ban\banAppeal;
use Kokonotsuba\ban\banDuration;
use Kokonotsuba\ban\banEntry;
use Kokonotsuba\ban\banImage;
use Kokonotsuba\ban\banImagePicker;
use Kokonotsuba\ban\visitorTokenSigner;
use Kokonotsuba\error\BoardException;
use Kokonotsuba\module_classes\abstractModuleAdmin;
use Kokonotsuba\module_classes\traits\AuditableTrait;
use Kokonotsuba\module_classes\traits\BanCheckpointTrait;
use Kokonotsuba\module_classes\traits\IndicatorTrait;
use Kokonotsuba\module_classes\traits\listeners\PostControlHooksTrait;
use Kokonotsuba\module_classes\traits\listeners\StaffAlertsListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\StaffNavListenerTrait;
use Kokonotsuba\post\Post;
use Kokonotsuba\userRole;

use function Kokonotsuba\libraries\_T;
use function Kokonotsuba\libraries\generateModerateButton;
use function Kokonotsuba\libraries\getCsrfHiddenInput;
use function Kokonotsuba\libraries\searchBoardArrayForBoard;
use function Kokonotsuba\libraries\html\drawPager;
use function Kokonotsuba\libraries\html\generateBoardListCheckBoxHTML;
use function Kokonotsuba\libraries\html\getPageFromRequest;
use function Kokonotsuba\libraries\html\pageToOffset;
use function Puchiko\request\redirect;
use function Puchiko\strings\sanitizeStr;

use const Kokonotsuba\GLOBAL_BOARD_UID;

require_once __DIR__ . '/banPolicy.php';
require_once __DIR__ . '/bannedPostPreview.php';

/**
 * The moderator half of the ban system: the ban form, the ban table, one ban's full record, and
 * the appeal queue.
 *
 * All of it is a view over banService — this class decides what a moderator may do and how it
 * looks, never what a ban means.
 */
class moduleAdmin extends abstractModuleAdmin {
	use AuditableTrait;
	use BanCheckpointTrait;
	use IndicatorTrait;
	use PostControlHooksTrait;
	use StaffAlertsListenerTrait;
	use StaffNavListenerTrait;

	/** What the table can be narrowed to, and what the two selects offer. */
	private const FILTER_STATUSES = ['all', 'active', 'expired', 'revoked', 'appealed'];
	private const FILTER_KINDS = ['all', 'ban', 'warning', 'mute'];

	private banPolicy $banPolicy;
	private ?int $pendingAppeals = null;

	private function pendingAppeals(): int {
		if ($this->pendingAppeals === null) {
			// an appeal whose ban has since expired has nothing to ask for, so it should not be counted
			$this->getBanService()->pruneExpiredAppeals();
			$this->pendingAppeals = $this->getBanService()->countPendingAppeals();
		}

		return $this->pendingAppeals;
	}
	private ?bannedPostPreview $bannedPostPreview = null;
	private string $modulePageUrl;
	private string $appealsUrl;
	private string $defaultBanMessage;
	private int $bansPerPage;
	private int $currentAccountId;

	public function getRequiredRole(): userRole {
		return $this->getConfig('AuthLevels.CAN_BAN');
	}

	public function getName(): string {
		return 'Admin ban tools';
	}

	public function getVersion(): string {
		return 'Koko 2025';
	}

	public function initialize(): void {
		$this->modulePageUrl = $this->getModulePageURL([], false, true);
		$this->appealsUrl = $this->getModulePageURL(['pageName' => 'appeals'], false, true);
		$this->bansPerPage = max(1, (int) $this->getConfig('ADMIN_PAGE_DEF', 100));
		$this->currentAccountId = (int) ($this->moduleContext->currentUserId ?? 0);

		// The schema's own default is blank, which means "use the stock notice" rather than "no
		// notice" - so the fallback is applied here, where the static paths are known.
		$configuredMessage = trim((string) $this->getModuleConfig('DEFAULT_BAN_MESSAGE', ''));
		$this->defaultBanMessage = $configuredMessage !== ''
			? str_replace('{$BAN_IMAGE}', $this->pickBanImage()->url, $configuredMessage)
			: $this->stockBanNotice();

		$this->banPolicy = new banPolicy(
			$this->getConfig('AuthLevels', []),
			$this->moduleContext->staffAccountFromSession->getRoleLevel()
		);

		$this->registerPostControlPair('onRenderPostAdminControls');
		$this->registerLinksAboveBarHook(_T('admin_nav_ban_title'), $this->modulePageUrl, _T('admin_nav_ban'), 'bans');
		$this->registerSimplePostWidget(
			fn(Post $post) => $this->generateBanUrl($post->getIp(), $post->getUid()),
			'ban',
			_T('ban_widget_label')
		);
		$this->registerAdminHeaderHook('onGenerateBanWindowTemplate');

		// ModuleAdminHeader is not dispatched on admin panel pages, and the ban form lives
		// there, so its script rides the plain ModuleHeader hook instead, role-protected so it
		// is only emitted for staff who can ban. The stylesheet comes from moduleMain, which
		// puts it on every page either half of this module draws on.
		$this->listenProtected('ModuleHeader', function (string &$moduleHeader): void {
			$this->includeScript('ban.js?v=3', $moduleHeader);
		});

		// makes an enforced ban clickable in the action log
		$this->registerActionReference(
			'ban',
			fn(string $id): string => $this->getModulePageURL(['pageName' => 'view', 'banId' => (int)$id], false)
		);

		$this->registerAppealNavEntries();
	}

	// ─── Admin UI hooks ───────────────────────────────────────────

	private function onRenderPostAdminControls(string &$modfunc, Post &$post, bool $noScript): void {
		$modfunc .= generateModerateButton(
			$this->generateBanUrl((string) $post->getIp(), $post->getUid()),
			'B',
			_T('ban_control_title'),
			'adminBanFunction',
			$noScript
		);
	}

	/**
	 * The blank ban form ban.js clones for the post menu's [Ban] window.
	 *
	 * Kept on ModuleAdminHeader rather than joining the script above: it carries a live CSRF
	 * token, and ModuleHeader also runs while static HTML is generated, where a baked-in token
	 * would be stale for whoever reads that page later.
	 */
	private function onGenerateBanWindowTemplate(string &$moduleHeader): void {
		$moduleHeader .= $this->generateTemplate('banFormTemplate', $this->renderBanForm(0, ''));
	}

	/**
	 * "Ban appeals" in both staff navs, badged with what is waiting.
	 *
	 * Gated on CAN_VIEW_BAN_APPEALS rather than the module's own role, so an instance that lets
	 * janitors read the queue gets the link for them too.
	 */
	private function registerAppealNavEntries(): void {
		if (!$this->getModuleConfig('ENABLE_APPEALS', true)) {
			return;
		}

		$appealRole = $this->banPolicy->getViewAppealsRole();

		$this->moduleContext->moduleEngine->addRoleProtectedListener($appealRole, 'LinksAboveBar',
			function (string &$linkHtml): void {
				$pending = $this->pendingAppeals();

				$indicator = $this->renderIndicator(
					'banAppealPending',
					' (' . $pending . ')',
					'banAppealIndicator',
					$pending === 0,
					_T('ban_appeal_nav_title')
				);

				$linkHtml .= '<li class="adminNavLink"><a title="' . sanitizeStr(_T('ban_appeal_nav_title'))
					. '" href="' . sanitizeStr($this->appealsUrl) . '">'
					. sanitizeStr(_T('ban_appeal_nav')) . $indicator . '</a></li>';
			}
		);

		$this->moduleContext->moduleEngine->addRoleProtectedListener($appealRole, 'StaffNavLinks',
			function (array &$entries): void {
				$entries[] = [
					'key' => 'banAppeals',
					'label' => _T('ban_appeal_nav'),
					'url' => $this->appealsUrl,
					'title' => _T('ban_appeal_nav_title'),
					'group' => 'bans',
					'count' => $this->pendingAppeals(),
				];
			}
		);

		$this->moduleContext->moduleEngine->addRoleProtectedListener($appealRole, 'StaffAlerts',
			function (array &$alerts): void {
				$alerts[] = [
					'key' => 'banAppeals',
					'label' => _T('ban_appeal_nav'),
					'count' => $this->pendingAppeals(),
					'url' => $this->appealsUrl,
					'title' => _T('ban_appeal_nav_title'),
				];
			}
		);
	}

	private function generateBanUrl(string $ipAddress, int $postUid): string {
		return $this->getModulePageURL(['ipAddress' => $ipAddress, 'postUid' => $postUid], false, true);
	}

	// ─── Routing ──────────────────────────────────────────────────

	public function ModulePage(): void {
		$pageName = (string) $this->moduleContext->request->getParameter('pageName', 'GET', '');

		match ($pageName) {
			// 'edit' was its own page once. The form is part of the ban's page now, so the old
			// URL lands on it rather than breaking a bookmark.
			'view', 'edit' => $this->drawBanView(),
			'appeals' => $this->drawAppealQueue(),
			'appeal' => $this->drawAppealView(),
			default => $this->drawBanIndex(),
		};
	}

	/** POST actions. dispatchModuleRequest() has already enforced the method and CSRF token. */
	protected function handleModuleRequest(): void {
		$action = (string) $this->moduleContext->request->getParameter('adminban-action', 'POST', '');

		match ($action) {
			'add-ban' => $this->handleBanAddition(),
			'edit-ban' => $this->handleBanEdit(),
			'revoke-bans' => $this->handleRevocation(),
			'appeal-approve' => $this->handleAppealDecision(true),
			'appeal-deny' => $this->handleAppealDecision(false),
			default => throw new BoardException(_T('ban_error_unknown_action')),
		};
	}

	// ─── Actions ──────────────────────────────────────────────────

	private function handleBanAddition(): void {
		if (!$this->banPolicy->canBan()) {
			throw new BoardException(_T('ban_error_cannot_ban'));
		}

		$request = $this->moduleContext->request;

		$ipAddress = trim((string) $request->getParameter('ipAddress', 'POST', ''));

		if ($ipAddress === '') {
			throw new BoardException(_T('ban_error_no_ip'));
		}

		$reason = $this->normalizeReason((string) $request->getParameter('privmsg', 'POST', ''));
		$publicReason = $this->readPublicReason();
		$privateReason = $this->normalizeReason((string) $request->getParameter('staffmsg', 'POST', ''), '');
		$postUid = (int) $request->getParameter('postUid', 'POST', 0);
		$isGlobal = $request->hasParameter('global', 'POST');
		$isPermanent = $request->hasParameter('permanent', 'POST');
		$tieToken = $request->hasParameter('tieToken', 'POST');
		$rejectsAppeals = $request->hasParameter('rejectAppeals', 'POST');

		$checkpoints = $request->getParameter('checkpoints', 'POST', []);
		$checkpoints = is_array($checkpoints) ? array_map('strval', $checkpoints) : [];

		$duration = (string) $request->getParameter('duration', 'POST', '');
		$durationSeconds = banDuration::toSeconds($duration);

		// A ban that stops nothing is a warning, whether that is said by ticking no checkpoint or
		// by the "0" length the old form took.
		$isWarning = $checkpoints === [] || banDuration::isExplicitZero($duration);
		$expiresAt = ($isPermanent || $isWarning) ? null : $request->getRequestTime() + $durationSeconds;

		if (!$isPermanent && !$isWarning && $durationSeconds <= 0) {
			throw new BoardException(_T('ban_error_no_duration'));
		}

		/** @var Post|false $post */
		$post = $postUid > 0 ? $this->moduleContext->postRepository->getPostByUid($postUid, true) : false;

		$boardUid = $isGlobal
			? GLOBAL_BOARD_UID
			: ($post ? $post->getBoardUID() : $this->moduleContext->board->getBoardUID());

		$banId = $this->getBanService()->fileBan(
			$ipAddress,
			$boardUid,
			$checkpoints,
			$expiresAt,
			$reason,
			$this->currentAccountId > 0 ? $this->currentAccountId : null,
			$postUid,
			$tieToken,
			$isWarning,
			false,
			$publicReason,
			$privateReason,
			$rejectsAppeals
		);

		// The notice is rendered from the ban rather than written into the comment, but a board
		// served as static HTML still has to be regenerated for anyone to see it.
		if ($publicReason !== '' && $post) {
			$this->rebuildBoardForPost($post);
		}

		$this->logAction(
			$this->buildBanLogMessage($ipAddress, $expiresAt, $isPermanent, $isWarning, $isGlobal, $post, $banId),
			$boardUid,
			actionType::BAN_ISSUE
		);

		$this->redirectBack();
	}

	/**
	 * Apply an edit to an existing ban.
	 *
	 * Everything the form offers is submitted every time, so the whole set is handed to the
	 * service; a field the form does not carry is simply not in the array and stays as it was.
	 */
	private function handleBanEdit(): void {
		if (!$this->banPolicy->canBan()) {
			throw new BoardException(_T('ban_error_cannot_ban'));
		}

		$request = $this->moduleContext->request;

		$ban = $this->requireBan((int) $request->getParameter('banId', 'POST', 0));

		$checkpoints = $request->getParameter('checkpoints', 'POST', []);
		$checkpoints = is_array($checkpoints) ? array_map('strval', $checkpoints) : [];

		$isPermanent = $request->hasParameter('permanent', 'POST');
		$duration = (string) $request->getParameter('duration', 'POST', '');
		$durationSeconds = banDuration::toSeconds($duration);

		if (!$isPermanent && !$ban->isWarning && $durationSeconds <= 0) {
			// "0" files a warning on the ban form, but a ban already filed cannot become one.
			throw new BoardException(banDuration::isExplicitZero($duration)
				? _T('ban_error_zero_on_edit')
				: _T('ban_error_no_duration'));
		}

		// A duration is measured from when the ban was filed, not from now: shortening a week-old
		// ban to "1d" should end it yesterday rather than grant another day.
		$expiresAt = $isPermanent ? null : $ban->filedAt + $durationSeconds;

		$publicReason = $this->readPublicReason();

		$this->getBanService()->editBan($ban, [
			'ipPattern' => trim((string) $request->getParameter('ipAddress', 'POST', '')),
			'checkpoints' => $checkpoints,
			'expiresAt' => $expiresAt,
			'reason' => $this->normalizeReason((string) $request->getParameter('privmsg', 'POST', '')),
			'publicReason' => $publicReason,
			'privateReason' => $this->normalizeReason((string) $request->getParameter('staffmsg', 'POST', ''), ''),
			'rejectsAppeals' => $request->hasParameter('rejectAppeals', 'POST'),
			'tieVisitorToken' => $request->hasParameter('tieToken', 'POST'),
		]);

		$this->logAction(_T('ban_log_edited', $ban->ipPattern, $ban->id), $ban->boardUid, actionType::BAN_EDIT);

		if ($ban->postUid !== null && ($publicReason !== '' || $ban->publicReason !== '')) {
			$post = $this->moduleContext->postRepository->getPostByUid($ban->postUid, true);

			if ($post) {
				$this->rebuildBoardForPost($post);
			}
		}

		redirect($this->getModulePageURL(['pageName' => 'view', 'banId' => $ban->id], false, true));
		exit;
	}

	private function handleRevocation(): void {
		if (!$this->banPolicy->canRevoke()) {
			throw new BoardException(_T('ban_error_cannot_revoke'));
		}

		$banIds = $this->getSelectedIds('banIds');

		if ($banIds === []) {
			$this->redirectBack();
			return;
		}

		$revoked = $this->getBanService()->revokeBans($banIds, $this->currentAccountId > 0 ? $this->currentAccountId : null);

		foreach ($revoked as $ban) {
			$this->logAction(_T('ban_log_revoked', $ban->ipPattern, $ban->id), $ban->boardUid, actionType::BAN_REVOKE);
		}

		$this->redirectBack();
	}

	private function handleAppealDecision(bool $approve): void {
		if (!$this->banPolicy->canActionAppeals()) {
			throw new BoardException(_T('ban_appeal_error_cannot_action'));
		}

		$request = $this->moduleContext->request;
		$appealIds = $this->getSelectedIds('appealIds');

		if ($appealIds === []) {
			$this->redirectBack();
			return;
		}

		$staffNote = trim((string) $request->getParameter('staffNote', 'POST', ''));

		if ($approve) {
			// A reduced sentence rather than a lift: the appeal still succeeds, the ban just
			// stops sooner. Blank means lift it outright.
			$reduceTo = trim((string) $request->getParameter('reduceTo', 'POST', ''));
			$reduceSeconds = $reduceTo === '' ? 0 : banDuration::toSeconds($reduceTo);
			$newExpiresAt = $reduceSeconds > 0 ? $request->getRequestTime() + $reduceSeconds : null;

			$count = $this->getBanService()->approveAppeals(
				$appealIds,
				$this->currentAccountId > 0 ? $this->currentAccountId : null,
				$staffNote,
				$newExpiresAt
			);

			$this->logAction(
				$newExpiresAt === null
					? _T('ban_log_appeal_approved', $count)
					: _T('ban_log_appeal_reduced', $count, banDuration::humanize($reduceSeconds)),
				$this->moduleContext->board->getBoardUID(),
				actionType::BAN_APPEAL
			);
		} else {
			$count = $this->getBanService()->denyAppeals(
				$appealIds,
				$this->currentAccountId > 0 ? $this->currentAccountId : null,
				$staffNote
			);

			$this->logAction(_T('ban_log_appeal_denied', $count), $this->moduleContext->board->getBoardUID(), actionType::BAN_APPEAL);
		}

		$this->redirectBack();
	}

	/** Regenerate the board a post lives on, so a static render picks the change up. */
	private function rebuildBoardForPost(Post $post): void {
		searchBoardArrayForBoard($post->getBoardUID())?->rebuildBoard();
	}

	/** The built-in "(USER WAS BANNED FOR THIS POST)" notice, banhammer and all. */
	private function stockBanNotice(): string {
		$image = $this->pickBanImage();

		return '<p class="warning">(USER WAS BANNED FOR THIS POST) <img class="banIcon icon" alt="banhammer" src="'
			. $image->url . '" ' . $image->dimensionAttributes() . '></p>';
	}

	/**
	 * One of the images in static/image/ban/, or the stock hammer when there are none.
	 *
	 * Drawn once per request, so the picture is settled before the form is rendered and the ban
	 * keeps whatever the moderator was shown.
	 */
	private function pickBanImage(): banImage {
		$picker = new banImagePicker(
			(string) $this->getConfig('STATIC_PATH'),
			(string) $this->getConfig('STATIC_URL')
		);

		return $picker->random();
	}

	/**
	 * The public notice, or nothing at all when the moderator did not tick "Public ban".
	 *
	 * Staff-authored markup, kept exactly as typed: the notice is rendered raw under the post, so
	 * a moderator can put a link or an image in it.
	 */
	private function readPublicReason(): string {
		$request = $this->moduleContext->request;

		if (!$request->hasParameter('public', 'POST')) {
			return '';
		}

		return trim((string) $request->getParameter('banmsg', 'POST', ''));
	}

	private function requireBan(int $banId): banEntry {
		$ban = $banId > 0 ? $this->getBanService()->getBan($banId) : null;

		if ($ban === null) {
			throw new BoardException(_T('ban_error_not_found'));
		}

		return $ban;
	}

	private function buildBanLogMessage(
		string $ip,
		?int $expiresAt,
		bool $isPermanent,
		bool $isWarning,
		bool $isGlobal,
		Post|false $post,
		int $banId,
	): string {
		$scope = $isGlobal ? _T('ban_log_scope_global') : _T('ban_log_scope_board');

		if ($isWarning) {
			$message = _T('ban_log_warned', $ip);
		} elseif ($isPermanent) {
			$message = _T('ban_log_banned_permanent', $ip, $scope);
		} else {
			$message = _T('ban_log_banned', $ip, $scope, banDuration::humanize(
				max(0, (int) $expiresAt - $this->moduleContext->request->getRequestTime())
			));
		}

		if ($post) {
			$message .= ' ' . _T('ban_log_for_post', $post->getNumber());
		}

		return $message . ' (#' . $banId . ')';
	}

	// ─── Pages ────────────────────────────────────────────────────

	/** The ban form and the one table below it. */
	private function drawBanIndex(): void {
		$request = $this->moduleContext->request;

		// Mutes that have run out are worthless; sweep them before counting so the table and its
		// pager agree on what is actually there.
		$this->getBanService()->pruneExpiredMutes();
		$this->getBanService()->pruneExpiredAppeals();

		$filters = $this->readFilters();
		$page = getPageFromRequest($request);
		$offset = pageToOffset($page, $this->bansPerPage);

		$total = $this->getBanService()->countBans($filters);
		$bans = $this->getBanService()->listBans($filters, $this->bansPerPage, $offset);
		$now = $request->getRequestTime();

		$templateValues = array_merge($this->getSharedTemplateValues(), [
			'{$PAGE_TITLE}' => sanitizeStr(_T('ban_admin_title')),
			'{$BAN_FORM}' => $this->renderBanForm(
				(int) $request->getParameter('postUid', 'GET', 0),
				(string) $request->getParameter('ipAddress', 'GET', '')
			),
			'{$FILTER_FORM}' => $this->renderFilterForm($filters),
			'{$HEADING_BANS}' => sanitizeStr(_T('ban_admin_heading', $total)),
			'{$HAS_BANS}' => $bans === [] ? '' : '1',
			'{$NO_BANS_TEXT}' => sanitizeStr(_T('ban_admin_empty')),
			// Nothing to revoke on an empty table, and the buttons now sit at both ends of it
			'{$CAN_REVOKE}' => ($this->banPolicy->canRevoke() && $bans !== []) ? '1' : '',
			'{$REVOKE_TEXT}' => sanitizeStr(_T('ban_revoke_selected')),
			'{$ROWS}' => array_map(fn(banEntry $ban): array => $this->buildBanRow($ban, $now), $bans),
		]);

		$this->drawAdminPage(
			'ADMIN_BAN_PAGE',
			$templateValues,
			drawPager($this->bansPerPage, $total, $this->buildFilteredPageUrl(), $request)
		);
	}

	/**
	 * One ban in full. The record and the form are one table: what the ban is sits in plain
	 * rows above the fields that change it, so a ban is read and corrected in the same place.
	 */
	private function drawBanView(): void {
		$banId = (int) $this->moduleContext->request->getParameter('banId', 'GET', 0);
		$ban = $banId > 0 ? $this->getBanService()->getBan($banId) : null;

		if ($ban === null) {
			throw new BoardException(_T('ban_error_not_found'));
		}

		$now = $this->moduleContext->request->getRequestTime();
		$appeals = $this->getBanService()->getAppealsForBan($ban->id);
		$canRevoke = $this->banPolicy->canRevoke() && !$ban->isRevoked();

		$formValues = [
			'{$RECORD_ROWS}' => $this->buildRecordRows($ban, $now),
			// The revoke form sits outside this one, so its button reaches it by id.
			'{$EXTRA_BUTTONS}' => $canRevoke
				? ' <button type="submit" form="banRevokeForm" class="banRevokeButton">' . sanitizeStr(_T('ban_revoke_this')) . '</button>'
				: '',
		];

		$templateValues = array_merge($this->getSharedTemplateValues(), [
			'{$PAGE_TITLE}' => sanitizeStr(_T('ban_view_title', $ban->id, $ban->ipPattern)),
			'{$BACK_URL}' => sanitizeStr($this->modulePageUrl),
			'{$BACK_TEXT}' => sanitizeStr(_T('ban_view_back')),
			'{$HEADING_DETAILS}' => sanitizeStr(_T('ban_view_heading_details')),
			'{$BAN_ID}' => (int) $ban->id,
			'{$BAN_FORM}' => $this->renderBanForm($ban->postUid ?? 0, $ban->ipPattern, $ban, $formValues),
			'{$CAN_REVOKE}' => $canRevoke ? '1' : '',
			'{$HEADING_APPEALS}' => sanitizeStr(_T('ban_view_heading_appeals')),
			'{$HAS_APPEALS}' => $appeals === [] ? '' : '1',
			'{$NO_APPEALS_TEXT}' => sanitizeStr(_T('ban_view_no_appeals')),
			'{$APPEALS}' => array_map(fn(banAppeal $appeal): array => $this->buildAppealRow($appeal, false), $appeals),
		]);

		$this->drawAdminPage('BAN_VIEW', $templateValues);
	}

	/**
	 * The read-only rows of a ban's form: everything about the ban that is not edited through
	 * a field, laid out above the fields as BAN_RECORD_ROW entries.
	 *
	 * @return array<int, array<string, string>>
	 */
	private function buildRecordRows(banEntry $ban, int $now): array {
		$preview = $this->renderPostPreview($ban->postUid);

		$rows = [];

		if ($preview !== '') {
			$rows[] = $this->recordRow(_T('ban_view_label_preview'), '<div class="banPostPreview modPagePostContainer">' . $preview . '</div>');
		}

		$rows[] = $this->recordRow(_T('ban_th_board'), sanitizeStr($this->describeScope($ban)));
		$rows[] = $this->recordRow(_T('ban_view_label_type'), sanitizeStr($this->describeType($ban)));
		$rows[] = $this->recordRow(_T('ban_th_status'), sanitizeStr($this->describeStatus($ban, $now)), 'banStatus' . ucfirst($ban->statusKey($now)));
		$rows[] = $this->recordRow(_T('ban_th_filed_by'), sanitizeStr($ban->filedByUsername ?? _T('ban_filed_by_system')));
		$rows[] = $this->recordRow(_T('ban_th_filed_at'), $this->formatDate($ban->filedAt));
		$rows[] = $this->recordRow(_T('ban_view_label_expires'), $this->describeExpiry($ban, $now));
		$rows[] = $this->recordRow(_T('ban_th_seen'), $this->describeSeen($ban));
		$rows[] = $this->recordRow(_T('ban_view_label_token'), sanitizeStr($ban->visitorTokenHash === null
			? _T('ban_token_not_tied')
			: _T('ban_token_tied', substr($ban->visitorTokenHash, 0, visitorTokenSigner::DISPLAY_LENGTH))));
		$rows[] = $this->recordRow(_T('ban_view_label_revoked'), $ban->isRevoked()
			? sanitizeStr($ban->revokedByUsername ?? '') . ' — ' . $this->formatDate((int) $ban->revokedAt)
			: '—');

		return $rows;
	}

	/** @param string $valueHtml Already escaped or trusted markup. */
	private function recordRow(string $label, string $valueHtml, string $class = ''): array {
		return [
			'{$LABEL}' => sanitizeStr($label),
			'{$VALUE}' => $valueHtml,
			'{$CLASS_ATTR}' => $class === '' ? '' : ' class="' . sanitizeStr($class) . '"',
		];
	}

	/** The appeal queue, on its own page so it can be worked through in one pass. */
	private function drawAppealQueue(): void {
		if (!$this->banPolicy->canViewAppeals()) {
			throw new BoardException(_T('ban_appeal_error_cannot_view'));
		}

		$request = $this->moduleContext->request;
		$status = (string) $request->getParameter('status', 'GET', 'pending');

		if (!in_array($status, ['pending', 'actioned', 'all'], true)) {
			$status = 'pending';
		}

		$page = getPageFromRequest($request);
		$offset = pageToOffset($page, $this->bansPerPage);

		// appeals of bans that have run out are dropped, so the queue only holds what can still be lifted
		$this->getBanService()->pruneExpiredAppeals();

		$total = $this->getBanService()->countAppeals($status);
		$appeals = $this->getBanService()->listAppeals($status, $this->bansPerPage, $offset);

		$templateValues = array_merge($this->getSharedTemplateValues(), [
			'{$PAGE_TITLE}' => sanitizeStr(_T('ban_appeal_queue_title')),
			'{$BACK_URL}' => sanitizeStr($this->modulePageUrl),
			'{$BACK_TEXT}' => sanitizeStr(_T('ban_appeal_back_to_bans')),
			'{$FILTER_FORM}' => $this->renderAppealFilterForm($status),
			'{$HEADING_APPEALS}' => sanitizeStr(_T('ban_appeal_heading_queue', $total)),
			'{$HAS_APPEALS}' => $appeals === [] ? '' : '1',
			'{$NO_APPEALS_TEXT}' => sanitizeStr(_T('ban_appeal_queue_empty')),
			'{$CAN_ACTION}' => ($this->banPolicy->canActionAppeals() && $appeals !== []) ? '1' : '',
			'{$APPEALS}' => array_map(fn(banAppeal $appeal): array => $this->buildAppealRow($appeal, true), $appeals),
			'{$DECISION_FORM}' => $this->renderAppealDecisionForm(true),
			'{$DECISION_BUTTONS}' => $this->renderAppealDecisionButtons(true),
		]);

		$this->drawAdminPage('BAN_APPEAL_QUEUE', $templateValues, drawPager($this->bansPerPage, $total, $this->appealsUrl, $request));
	}

	/**
	 * One appeal on its own page, with the decision attached to it.
	 *
	 * The queue decides in bulk over whatever is ticked; this is the other half - everything
	 * about a single appeal, the ban it argues with beside it, and approve/deny right there, so
	 * a decision does not have to be made from a table row.
	 */
	private function drawAppealView(): void {
		if (!$this->banPolicy->canViewAppeals()) {
			throw new BoardException(_T('ban_appeal_error_cannot_view'));
		}

		$request = $this->moduleContext->request;
		$appealId = (int) $request->getParameter('appealId', 'GET', 0);
		$appeal = $appealId > 0 ? $this->getBanService()->getAppeal($appealId) : null;

		if ($appeal === null) {
			throw new BoardException(_T('ban_appeal_error_appeal_not_found'));
		}

		// Appeals are cascaded away with their ban, so one without the other cannot be reached.
		$ban = $this->getBanService()->getBan($appeal->banId);

		if ($ban === null) {
			throw new BoardException(_T('ban_error_not_found'));
		}

		$now = $request->getRequestTime();
		$canDecide = $appeal->status->isPending() && $this->banPolicy->canActionAppeals();

		$this->drawAdminPage('BAN_APPEAL_VIEW', array_merge(
			$this->getSharedTemplateValues(),
			$this->getBanFieldLabels(),
			$this->getAppealFieldLabels(),
			[
				'{$PAGE_TITLE}' => sanitizeStr(_T('ban_appeal_view_title', $appeal->id)),
				'{$BACK_URL}' => sanitizeStr($this->appealsUrl),
				'{$BACK_TEXT}' => sanitizeStr(_T('ban_appeal_back_to_queue')),
				'{$BAN_URL}' => sanitizeStr($this->getModulePageURL(['pageName' => 'view', 'banId' => $ban->id], false, true)),
				'{$BAN_TEXT}' => sanitizeStr(_T('ban_view_details')),
				'{$APPEAL_ID}' => (int) $appeal->id,
				'{$STATUS}' => sanitizeStr($appeal->status->label()),
				'{$STATUS_CLASS}' => sanitizeStr($appeal->status->rowCssClass()),
				'{$APPELLANT_IP}' => $this->renderIpCell($appeal->appellantIp),
				'{$FILED_AT}' => $this->formatDate($appeal->filedAt),
				// Their own words, so nothing in here is markup.
				'{$REASON}' => $this->normalizeReason(sanitizeStr($appeal->reason), _T('ban_appeal_no_reason')),
				'{$IS_ACTIONED}' => $appeal->status->isPending() ? '' : '1',
				'{$ACTIONED_BY}' => sanitizeStr($appeal->actionedByUsername ?? _T('ban_filed_by_system')),
				'{$ACTIONED_AT}' => $appeal->actionedAt === null ? '' : $this->formatDate($appeal->actionedAt),
				'{$HAS_NOTE}' => $appeal->staffNote !== '' ? '1' : '',
				'{$STAFF_NOTE}' => $this->normalizeReason(sanitizeStr($appeal->staffNote), ''),
				'{$BAN_ID}' => (int) $ban->id,
				'{$LABEL_PREVIEW}' => sanitizeStr(_T('ban_view_label_preview')),
				'{$POST_PREVIEW}' => $this->renderPostPreview($ban->postUid),
				'{$IP}' => $this->renderIpCell($ban->ipPattern),
				'{$BOARD}' => sanitizeStr($this->describeScope($ban)),
				'{$BAN_STATUS}' => sanitizeStr($this->describeStatus($ban, $now)),
				'{$BAN_STATUS_CLASS}' => sanitizeStr('banStatus' . ucfirst($ban->statusKey($now))),
				'{$DURATION}' => sanitizeStr($this->describeDuration($ban)),
				'{$EXPIRES_AT}' => $this->describeExpiry($ban, $now),
				'{$BAN_REASON}' => $ban->reason !== '' ? $ban->reason : sanitizeStr(_T('ban_no_reason')),
				'{$CAN_DECIDE}' => $canDecide ? '1' : '',
				'{$DECISION_FORM}' => $canDecide ? $this->renderAppealDecisionForm(false) : '',
			]
		));
	}

	/**
	 * The post a ban names, drawn by its own board's renderer the way reports draw theirs.
	 *
	 * Rendered in admin mode: the reader of this page is staff deciding whether the ban was
	 * right, so they get the post with everything staff normally see on it. This module's own
	 * "(USER WAS BANNED FOR THIS POST)" notice comes along with it, which is the point.
	 *
	 * @param int|null $postUid The post the ban names, or null when it names none.
	 * @return string Post HTML, or '' when there is no post to show.
	 */
	private function renderPostPreview(?int $postUid): string {
		if ($postUid === null || $postUid <= 0) {
			return '';
		}

		$post = $this->moduleContext->postRepository->getPostByUid($postUid, true);

		if (!$post) {
			return '<div class="banMissingPost">' . sanitizeStr(_T('ban_post_unavailable')) . '</div>';
		}

		return $this->getBannedPostPreview()->render($post, true);
	}

	/** Built on first use: only a ban's own page previews anything. */
	private function getBannedPostPreview(): bannedPostPreview {
		return $this->bannedPostPreview ??= new bannedPostPreview(
			$this->moduleContext->board,
			$this->moduleContext->moduleEngine,
			$this->moduleContext->templateEngine,
			$this->moduleContext->quoteLinkService,
			$this->moduleContext->request
		);
	}

	// ─── Rows and forms ───────────────────────────────────────────

	/**
	 * One row of the ban table.
	 *
	 * Expired and revoked rows keep their place and are dimmed by the row class rather than
	 * dropped, so the table doubles as the history of what was done.
	 */
	private function buildBanRow(banEntry $ban, int $now): array {
		return [
			'{$BAN_ID}' => (int) $ban->id,
			'{$ROW_CLASS}' => sanitizeStr('banRow' . ucfirst($ban->statusKey($now))),
			'{$CAN_REVOKE}' => ($this->banPolicy->canRevoke() && !$ban->isRevoked()) ? '1' : '',
			'{$IP}' => $this->renderIpCell($ban->ipPattern),
			'{$BOARD}' => sanitizeStr($this->describeScope($ban)),
			'{$FILED_BY}' => sanitizeStr($ban->filedByUsername ?? _T('ban_filed_by_system')),
			'{$FILED_AT}' => $this->formatDate($ban->filedAt),
			'{$DURATION}' => sanitizeStr($this->describeDuration($ban)),
			'{$REASON}' => $ban->reason !== '' ? $ban->reason : sanitizeStr(_T('ban_no_reason')),
			'{$HAS_POST}' => $ban->postUid === null ? '' : '1',
			'{$POST_NUMBER}' => (int) ($ban->postNumber ?? 0),
			'{$POST_URL}' => sanitizeStr($this->buildPostUrl($ban)),
			'{$SEEN}' => $this->describeSeen($ban),
			'{$STATUS}' => sanitizeStr($this->describeStatus($ban, $now)),
			'{$HAS_APPEAL}' => $ban->pendingAppealCount > 0 ? '1' : '',
			'{$APPEAL_TEXT}' => sanitizeStr(_T('ban_row_has_appeal')),
			'{$VIEW_URL}' => sanitizeStr($this->getModulePageURL(['pageName' => 'view', 'banId' => $ban->id], false, true)),
			'{$VIEW_TEXT}' => sanitizeStr(_T('ban_view_link')),
			'{$HAS_PRIVATE_REASON}' => $ban->privateReason !== '' ? '1' : '',
			'{$PRIVATE_REASON}' => $ban->privateReason,
			'{$PRIVATE_REASON_LABEL}' => sanitizeStr(_T('ban_private_reason_tag')),
		];
	}

	private function buildAppealRow(banAppeal $appeal, bool $selectable): array {
		return [
			'{$APPEAL_ID}' => (int) $appeal->id,
			'{$ROW_CLASS}' => sanitizeStr($appeal->status->rowCssClass()),
			'{$IS_SELECTABLE}' => ($selectable && $appeal->status->isPending() && $this->banPolicy->canActionAppeals()) ? '1' : '',
			'{$BAN_ID}' => (int) $appeal->banId,
			'{$BAN_IP}' => $this->renderIpCell($appeal->banIpPattern ?? ''),
			'{$BAN_URL}' => sanitizeStr($this->getModulePageURL(['pageName' => 'view', 'banId' => $appeal->banId], false, true)),
			'{$BOARD}' => sanitizeStr($appeal->boardTitle ?? ''),
			'{$APPELLANT_IP}' => $this->renderIpCell($appeal->appellantIp),
			'{$REASON}' => sanitizeStr($appeal->reason),
			'{$BAN_REASON}' => sanitizeStr($appeal->banReason ?? ''),
			'{$BAN_REASON_LABEL}' => sanitizeStr(_T('ban_appeal_ban_reason_tag')),
			'{$FILED_AT}' => $this->formatDate($appeal->filedAt),
			'{$STATUS_LABEL}' => sanitizeStr($appeal->status->label()),
			'{$IS_ACTIONED}' => $appeal->status->isPending() ? '' : '1',
			'{$ACTIONED_BY}' => sanitizeStr($appeal->actionedByUsername ?? ''),
			'{$ACTIONED_AT}' => $appeal->actionedAt === null ? '' : $this->formatDate($appeal->actionedAt),
			'{$HAS_NOTE}' => $appeal->staffNote !== '' ? '1' : '',
			'{$STAFF_NOTE}' => sanitizeStr($appeal->staffNote),
			'{$STAFF_NOTE_LABEL}' => sanitizeStr(_T('ban_appeal_staff_note')),
			// The row's own link goes to the appeal; the ban column already links the ban.
			'{$VIEW_URL}' => sanitizeStr($this->getModulePageURL(['pageName' => 'appeal', 'appealId' => $appeal->id], false, true)),
			'{$VIEW_TEXT}' => sanitizeStr(_T('ban_view_link')),
		];
	}

	/**
	 * The ban form, rendered three times: blank into the <template> ban.js clones for the post
	 * menu, blank on the ban index, and filled in from an existing ban on that ban's own page.
	 *
	 * @param array<string, mixed> $overrides Values the caller sets over the defaults below,
	 *   such as the record rows a ban's own page lays above the fields.
	 */
	private function renderBanForm(int $postUid, string $ipAddress, ?banEntry $ban = null, array $overrides = []): string {
		$isEdit = $ban !== null;
		$registry = $this->getBanService()->getCheckpointRegistry();

		// Editing shows what the ban actually blocks; a fresh form shows the house defaults.
		$ticked = $isEdit ? $ban->checkpoints : $this->getBanService()->getDefaultCheckpoints();

		$checkpointRows = array_map(
			fn(array $entry): array => [
				'{$CHECKPOINT_KEY}' => sanitizeStr($entry['key']),
				'{$CHECKPOINT_LABEL}' => sanitizeStr($entry['label']),
				'{$CHECKED_ATTR}' => in_array($entry['key'], $ticked, true) ? ' checked' : '',
			],
			$registry->all()
		);

		$publicReason = $isEdit ? $ban->publicReason : '';

		return $this->moduleContext->adminPageRenderer->ParseBlock('ADMIN_BAN_FORM', array_merge([
			'{$MODULE_URL}' => sanitizeStr($this->modulePageUrl),
			'{$RECORD_ROWS}' => [],
			'{$EXTRA_BUTTONS}' => '',
			'{$CSRF_TOKEN}' => getCsrfHiddenInput(),
			'{$FORM_ACTION_VALUE}' => $isEdit ? 'edit-ban' : 'add-ban',
			'{$IS_EDIT}' => $isEdit ? '1' : '',
			'{$BAN_ID}' => $isEdit ? (int) $ban->id : '',
			'{$POST_UID}' => $postUid > 0 ? (int) $postUid : '',
			'{$IP}' => sanitizeStr($ipAddress),
			'{$DURATION}' => sanitizeStr($isEdit ? $this->buildEditDuration($ban) : '1d'),
			'{$REASON}' => sanitizeStr($isEdit ? $this->unformatReason($ban->reason) : ''),
			'{$PRIVATE_REASON}' => sanitizeStr($isEdit ? $this->unformatReason($ban->privateReason) : ''),
			// A ban with no public notice yet offers the house default, the way a fresh form does.
			'{$DEFAULT_BAN_MESSAGE}' => sanitizeStr($publicReason !== '' ? $publicReason : $this->defaultBanMessage),
			'{$PERMANENT_CHECKED}' => ($isEdit && $ban->isPermanent()) ? ' checked' : '',
			'{$PUBLIC_CHECKED}' => ($isEdit && $publicReason !== '') ? ' checked' : '',
			'{$TOKEN_CHECKED}' => ($isEdit && $ban->visitorTokenHash !== null) ? ' checked' : '',
			// The browser is read off the post, so with no post there is nothing to tie to.
			'{$TOKEN_DISABLED}' => $postUid > 0 ? '' : ' disabled',
			'{$REJECT_APPEALS_CHECKED}' => ($isEdit && $ban->rejectsAppeals) ? ' checked' : '',
			'{$CHECKPOINTS}' => $checkpointRows,
			'{$LABEL_PRIVATE_REASON}' => sanitizeStr(_T('ban_form_label_private_reason')),
			'{$DESC_PRIVATE_REASON}' => sanitizeStr(_T('ban_form_desc_private_reason')),
			'{$LABEL_REJECT_APPEALS}' => sanitizeStr(_T('ban_form_label_reject_appeals')),
			'{$DESC_REJECT_APPEALS}' => sanitizeStr(_T('ban_form_desc_reject_appeals')),
			// A ban's own page is headed already, so only the blank form names itself.
			'{$FORM_HEADING}' => $isEdit ? '' : sanitizeStr(_T('ban_form_heading')),
			'{$LABEL_IP}' => sanitizeStr(_T('ban_form_label_ip')),
			'{$DESC_IP}' => sanitizeStr(_T('ban_form_desc_ip')),
			'{$PLACEHOLDER_IP}' => sanitizeStr(_T('ban_form_placeholder_ip')),
			'{$LABEL_DURATION}' => sanitizeStr(_T('ban_form_label_duration')),
			'{$PLACEHOLDER_DURATION}' => sanitizeStr(_T('ban_form_placeholder_duration')),
			'{$DESC_DURATION}' => _T('ban_form_desc_duration'),
			'{$LABEL_PERMANENT}' => sanitizeStr(_T('ban_form_label_permanent')),
			'{$DESC_PERMANENT}' => sanitizeStr(_T('ban_form_desc_permanent')),
			'{$LABEL_CHECKPOINTS}' => sanitizeStr(_T('ban_form_label_checkpoints')),
			'{$DESC_CHECKPOINTS}' => sanitizeStr(_T('ban_form_desc_checkpoints')),
			'{$SELECT_ALL}' => sanitizeStr(_T('ban_form_select_all')),
			'{$LABEL_REASON}' => sanitizeStr(_T('ban_form_label_reason')),
			'{$PLACEHOLDER_REASON}' => sanitizeStr(_T('ban_form_placeholder_reason')),
			'{$PLACEHOLDER_PRIVATE_REASON}' => sanitizeStr(_T('ban_form_placeholder_private_reason')),
			'{$LABEL_PUBLIC_MESSAGE}' => sanitizeStr(_T('ban_form_label_public_message')),
			'{$PLACEHOLDER_PUBLIC_MESSAGE}' => sanitizeStr(_T('ban_form_placeholder_public_message')),
			'{$LABEL_PUBLIC}' => sanitizeStr(_T('ban_form_label_public')),
			'{$LABEL_GLOBAL}' => sanitizeStr(_T('ban_form_label_global')),
			'{$DESC_GLOBAL}' => sanitizeStr(_T('ban_form_desc_global')),
			'{$LABEL_TOKEN}' => sanitizeStr(_T('ban_form_label_token')),
			'{$DESC_TOKEN}' => sanitizeStr($postUid > 0 ? _T('ban_form_desc_token') : _T('ban_form_desc_token_no_post')),
			'{$SUBMIT_TEXT}' => sanitizeStr($isEdit ? _T('ban_form_submit_edit') : _T('ban_form_submit')),
		], $overrides));
	}

	/**
	 * The ban's length as a duration string the form can take back.
	 *
	 * Rendered in hours so an odd length survives a round trip through the form without being
	 * rounded into a different sentence.
	 */
	private function buildEditDuration(banEntry $ban): string {
		if ($ban->expiresAt === null) {
			return '';
		}

		$hours = max(1, (int) round(($ban->expiresAt - $ban->filedAt) / 3600));

		return $hours . 'h';
	}

	/** Turn the stored <br /> back into newlines so a textarea round-trips cleanly. */
	private function unformatReason(string $reason): string {
		return preg_replace('/<br\s*\/?>/i', "\n", $reason) ?? $reason;
	}

	/**
	 * The filter form over the ban table.
	 *
	 * Laid out like the other staff filter forms - a collapsed details box over a two-column
	 * table - so the ban table is narrowed the same way the action log and manage-posts are.
	 * It opens itself when something is being filtered, so a narrowed table never looks whole.
	 */
	private function renderFilterForm(array $filters): string {
		return $this->moduleContext->adminPageRenderer->ParseBlock('BAN_FILTER_FORM', [
			'{$MODULE_URL}' => sanitizeStr($this->modulePageUrl),
			'{$OPEN_ATTR}' => $this->hasActiveFilters($filters) ? 'open' : '',
			'{$ADDRESS_ROWS}' => $this->renderAddressFilterRows($filters),
			'{$CHECKPOINT_BOXES}' => $this->renderCheckpointFilterBoxes($filters['checkpoints'] ?? []),
			'{$BOARD_BOXES}' => $this->renderBoardFilterBoxes($filters),
			'{$STATUS_OPTIONS}' => $this->renderFilterOptions(
				self::FILTER_STATUSES,
				'ban_filter_status_',
				(string) ($filters['status'] ?? 'all')
			),
			'{$KIND_OPTIONS}' => $this->renderFilterOptions(
				self::FILTER_KINDS,
				'ban_filter_kind_',
				(string) ($filters['kind'] ?? 'all')
			),
			'{$GENERAL}' => sanitizeStr((string) ($filters['general'] ?? '')),
			'{$REASON}' => sanitizeStr((string) ($filters['reason'] ?? '')),
			'{$STAFF}' => sanitizeStr((string) ($filters['staff'] ?? '')),
			'{$BAN_ID}' => (string) ($filters['banId'] ?? ''),
			'{$POST_NUMBER}' => (string) ($filters['postNumber'] ?? ''),
			'{$DATE_AFTER}' => sanitizeStr((string) ($filters['dateAfter'] ?? '')),
			'{$DATE_BEFORE}' => sanitizeStr((string) ($filters['dateBefore'] ?? '')),
			'{$HAS_IP}' => ($filters['ip'] ?? '') !== '' ? '1' : '',
			'{$IP_FILTER_TEXT}' => sanitizeStr(_T('ban_filter_showing_ip', (string) ($filters['ip'] ?? ''))),
			'{$CLEAR_IP_URL}' => sanitizeStr($this->modulePageUrl),
			'{$CLEAR_IP_TEXT}' => sanitizeStr(_T('ban_filter_clear_ip')),
			'{$SUMMARY_TEXT}' => sanitizeStr(_T('ban_filter_summary')),
			'{$LABEL_GENERAL}' => sanitizeStr(_T('ban_filter_label_general')),
			'{$PLACEHOLDER_GENERAL}' => sanitizeStr(_T('ban_filter_placeholder_general')),
			'{$LABEL_REASON}' => sanitizeStr(_T('ban_filter_label_reason')),
			'{$LABEL_STAFF}' => sanitizeStr(_T('ban_filter_label_staff')),
			'{$LABEL_BAN_ID}' => sanitizeStr(_T('ban_filter_label_ban_id')),
			'{$LABEL_POST}' => sanitizeStr(_T('ban_filter_label_post')),
			'{$LABEL_DATE_AFTER}' => sanitizeStr(_T('ban_filter_label_date_after')),
			'{$LABEL_DATE_BEFORE}' => sanitizeStr(_T('ban_filter_label_date_before')),
			'{$LABEL_BOARD}' => sanitizeStr(_T('ban_filter_label_board')),
			'{$LABEL_STATUS}' => sanitizeStr(_T('ban_filter_label_status')),
			'{$LABEL_KIND}' => sanitizeStr(_T('ban_filter_label_kind')),
			'{$LABEL_CHECKPOINTS}' => sanitizeStr(_T('ban_filter_label_checkpoints')),
			'{$SUBMIT_TEXT}' => sanitizeStr(_T('ban_filter_submit')),
			'{$RESET_TEXT}' => sanitizeStr(_T('ban_filter_reset')),
		]);
	}

	/**
	 * The address fields, for staff cleared to see addresses at all.
	 *
	 * The address is matched exactly - filtering for 192.0.2.1 must not drag in 192.0.2.10 -
	 * which is also what clicking an address in the table asks for.
	 */
	private function renderAddressFilterRows(array $filters): string {
		if (!$this->banPolicy->canViewIpAddresses()) {
			return '';
		}

		$rows = [
			['banFilterIp', 'ip', _T('ban_filter_label_ip'), (string) ($filters['ip'] ?? ''), _T('ban_filter_placeholder_ip')],
			['banFilterToken', 'token', _T('ban_filter_label_token'), (string) ($filters['token'] ?? ''), _T('ban_filter_placeholder_token')],
		];

		$html = '';

		foreach ($rows as [$id, $name, $label, $value, $placeholder]) {
			$html .= '<tr>
				<td class="postblock"><label for="' . $id . '">' . sanitizeStr($label) . '</label></td>
				<td><input class="inputtext" id="' . $id . '" name="' . $name . '" value="' . sanitizeStr($value)
					. '" placeholder="' . sanitizeStr($placeholder) . '"></td>
			</tr>';
		}

		return $html;
	}

	/** One checkbox per checkpoint, so the table can be narrowed to what a ban actually blocks. */
	private function renderCheckpointFilterBoxes(array $selected): string {
		$html = '';

		foreach ($this->getBanService()->getCheckpointRegistry()->all() as $entry) {
			$checked = in_array($entry['key'], $selected, true) || $selected === [];

			$html .= '<li><label><input type="checkbox" name="checkpoints[]" value="'
				. sanitizeStr($entry['key']) . '"' . ($checked ? ' checked' : '') . '>'
				. sanitizeStr($entry['label']) . '</label></li>';
		}

		return $html;
	}

	/** Board checkboxes, the global scope among them: a ban is filed on one or on all of them. */
	private function renderBoardFilterBoxes(array $filters): string {
		$boards = [['board_uid' => GLOBAL_BOARD_UID, 'board_title' => _T('ban_scope_global')]];

		foreach (GLOBAL_BOARD_ARRAY as $board) {
			$boards[] = ['board_uid' => $board->getBoardUID(), 'board_title' => sanitizeStr($board->getBoardTitle())];
		}

		$selected = $filters['boards'] ?? [];

		// the one-scope shorthand a link from elsewhere may have arrived with
		if ($selected === [] && ($filters['boardUid'] ?? null) !== null) {
			$selected = [(int) $filters['boardUid']];
		}

		return generateBoardListCheckBoxHTML($selected, $boards);
	}

	/** @param list<string> $values Option values, each labelled by $prefix . $value. */
	private function renderFilterOptions(array $values, string $prefix, string $selected): string {
		$options = '';

		foreach ($values as $value) {
			$options .= '<option value="' . $value . '"' . ($selected === $value ? ' selected' : '') . '>'
				. sanitizeStr(_T($prefix . $value)) . '</option>';
		}

		return $options;
	}

	private function renderAppealFilterForm(string $status): string {
		$options = '';

		foreach (['pending', 'actioned', 'all'] as $value) {
			$options .= '<option value="' . $value . '"' . ($status === $value ? ' selected' : '') . '>'
				. sanitizeStr(_T('ban_appeal_filter_' . $value)) . '</option>';
		}

		return $this->moduleContext->adminPageRenderer->ParseBlock('BAN_APPEAL_FILTER_FORM', [
			'{$MODULE_URL}' => sanitizeStr($this->appealsUrl),
			'{$STATUS_OPTIONS}' => $options,
			'{$LABEL_STATUS}' => sanitizeStr(_T('ban_filter_label_status')),
			'{$SUBMIT_TEXT}' => sanitizeStr(_T('ban_filter_submit')),
		]);
	}

	/**
	 * The note-and-duration form both decision paths share.
	 *
	 * @param bool $forSelection Whether it decides whatever is ticked in the queue, rather than
	 *                           the one appeal whose page it is on - only the wording differs.
	 */
	private function renderAppealDecisionForm(bool $forSelection): string {
		// The field ids are unique per rendering, so the queue's form and an appeal page's form
		// never hand the same id to two labels.
		$idPrefix = $forSelection ? 'banAppealBulk' : 'banAppealOne';

		return $this->moduleContext->adminPageRenderer->ParseBlock('BAN_APPEAL_DECISION_FORM', [
			'{$ID_PREFIX}' => $idPrefix,
			'{$DECISION_BUTTONS}' => $this->renderAppealDecisionButtons($forSelection),
			'{$DECISION_HEADING}' => sanitizeStr(_T($forSelection ? 'ban_appeal_heading_decide_selected' : 'ban_appeal_heading_decide')),
			'{$DECISION_LEGEND}' => sanitizeStr(_T('ban_appeal_desc_decision')),
			'{$LABEL_NOTE}' => sanitizeStr(_T('ban_appeal_label_note')),
			'{$DESC_NOTE}' => sanitizeStr(_T('ban_appeal_desc_note')),
			'{$PLACEHOLDER_NOTE}' => sanitizeStr(_T('ban_appeal_placeholder_note')),
			'{$LABEL_REDUCE}' => sanitizeStr(_T('ban_appeal_label_reduce')),
			'{$DESC_REDUCE}' => sanitizeStr(_T('ban_appeal_desc_reduce')),
			'{$PLACEHOLDER_REDUCE}' => sanitizeStr(_T('ban_appeal_placeholder_reduce')),
		]);
	}

	/**
	 * The approve/deny buttons on their own.
	 *
	 * The queue repeats them above its table as well as below it, so deciding on a long page
	 * does not mean scrolling back to the bottom. Only the buttons are repeated - the note and
	 * the reduced sentence they submit are one set of fields, in the form under the table.
	 */
	private function renderAppealDecisionButtons(bool $forSelection): string {
		return $this->moduleContext->adminPageRenderer->ParseBlock('BAN_APPEAL_DECISION_BUTTONS', [
			'{$APPROVE_TEXT}' => sanitizeStr(_T($forSelection ? 'ban_appeal_approve' : 'ban_appeal_approve_one')),
			'{$DENY_TEXT}' => sanitizeStr(_T($forSelection ? 'ban_appeal_deny' : 'ban_appeal_deny_one')),
		]);
	}

	/** @return array<string, string> Row labels for the single-appeal detail tables. */
	private function getAppealFieldLabels(): array {
		return [
			'{$LABEL_APPEAL_STATUS}' => sanitizeStr(_T('ban_appeal_label_status')),
			'{$LABEL_APPELLANT}' => sanitizeStr(_T('ban_appeal_th_appellant')),
			'{$LABEL_APPEAL_FILED}' => sanitizeStr(_T('ban_appeal_th_filed')),
			'{$LABEL_APPEAL_REASON}' => sanitizeStr(_T('ban_appeal_label_reason')),
			'{$LABEL_DECIDED_BY}' => sanitizeStr(_T('ban_appeal_label_decided_by')),
			'{$LABEL_STAFF_NOTE}' => sanitizeStr(_T('ban_appeal_label_note')),
			'{$HEADING_APPEAL}' => sanitizeStr(_T('ban_appeal_heading_appeal')),
			'{$HEADING_BAN}' => sanitizeStr(_T('ban_appeal_heading_ban')),
		];
	}

	// ─── Descriptions ─────────────────────────────────────────────

	private function describeScope(banEntry $ban): string {
		return $ban->boardUid === GLOBAL_BOARD_UID
			? _T('ban_scope_global')
			: ($ban->boardTitle ?? _T('ban_scope_unknown'));
	}

	private function describeType(banEntry $ban): string {
		if ($ban->isWarning) {
			return _T('ban_type_warning');
		}

		return $ban->isMute ? _T('ban_type_mute') : _T('ban_type_ban');
	}

	/** "Permanent", "7d", "Mute (20min)", or the warning marker. */
	private function describeDuration(banEntry $ban): string {
		if ($ban->isWarning) {
			return _T('ban_type_warning');
		}

		if ($ban->isPermanent()) {
			return _T('ban_duration_permanent');
		}

		$length = banDuration::humanize((int) $ban->expiresAt - $ban->filedAt);

		return $ban->isMute ? _T('ban_duration_mute', $length) : $length;
	}

	/** HTML: carries the formatted date, so it is not escaped again at the call site. */
	private function describeExpiry(banEntry $ban, int $now): string {
		if ($ban->isWarning) {
			return _T('ban_expires_never_warning');
		}

		if ($ban->isPermanent()) {
			return _T('ban_duration_permanent');
		}

		$formatted = $this->formatDate((int) $ban->expiresAt);

		return $ban->isExpired($now)
			? $formatted
			: _T('ban_expires_in', $formatted, banDuration::humanize((int) $ban->secondsRemaining($now)));
	}

	private function describeStatus(banEntry $ban, int $now): string {
		return match ($ban->statusKey($now)) {
			'revoked' => $ban->revokedByUsername !== null
				? _T('ban_status_revoked_by', $ban->revokedByUsername)
				: _T('ban_status_revoked'),
			'expired' => _T('ban_status_expired'),
			'warning' => _T('ban_type_warning'),
			'mute' => _T('ban_status_muted'),
			default => _T('ban_status_active'),
		};
	}

	/**
	 * "Seen", "Seen (cookies disabled)" or "Not seen".
	 *
	 * A ban is marked seen the moment it stops somebody or they open the ban page, so a ban that
	 * never says "Seen" is one that never actually landed.
	 *
	 * HTML, for the same reason as {@see describeExpiry}.
	 */
	private function describeSeen(banEntry $ban): string {
		if (!$ban->hasBeenSeen()) {
			return _T('ban_seen_never');
		}

		return $ban->seenWithCookies === false
			? _T('ban_seen_no_cookies', $this->formatDate((int) $ban->seenAt))
			: _T('ban_seen_at', $this->formatDate((int) $ban->seenAt));
	}

	// ─── Helpers ──────────────────────────────────────────────────

	/** @return array{boardUid: int|null, status: string, search: string, ip: string} */
	/**
	 * The ban table's filter set, read off the query string.
	 *
	 * An empty checkbox list means every board and every checkpoint, which is what an unfiltered
	 * first visit sends. Addresses are dropped entirely for staff who may not see them, so
	 * neither the address fields nor the general search can be used to probe for one.
	 */
	private function readFilters(): array {
		$request = $this->moduleContext->request;
		$canViewIp = $this->banPolicy->canViewIpAddresses();

		$status = $this->readText('status');

		if (!in_array($status, self::FILTER_STATUSES, true)) {
			$status = 'all';
		}

		$kind = $this->readText('kind');

		if (!in_array($kind, self::FILTER_KINDS, true)) {
			$kind = 'all';
		}

		// the old single-box form called the catch-all "search", and links to it are still about
		$general = $this->readText('general') ?: $this->readText('search');

		$boardUid = $this->readText('boardUid');

		return [
			'banId' => $this->readPositiveInt('banId'),
			'boards' => $this->readBoardFilter(),
			'boardUid' => $boardUid === '' ? null : (int) $boardUid,
			'status' => $status,
			'kind' => $kind,
			'general' => $general,
			'reason' => $this->readText('reason'),
			'staff' => $this->readText('staff'),
			'postNumber' => $this->readPositiveInt('postNumber'),
			'checkpoints' => $this->readCheckpointFilter(),
			'dateAfter' => $this->readText('dateAfter'),
			'dateBefore' => $this->readText('dateBefore'),
			'ip' => $canViewIp ? $this->readText('ip') : '',
			'token' => $canViewIp ? $this->readText('token') : '',
			'searchAddresses' => $canViewIp,
		];
	}

	/**
	 * The ticked checkpoints, or nothing when they are all ticked.
	 *
	 * Ticking every box is what the form sends when nobody has narrowed anything, and it is not
	 * the same question as "blocks one of these": a warning blocks nothing, so an all-ticked
	 * filter would quietly drop every warning out of the table.
	 *
	 * @return list<string>
	 */
	private function readCheckpointFilter(): array {
		$registry = $this->getBanService()->getCheckpointRegistry();
		$selected = $registry->filterKnown($this->readStringList('checkpoints'));

		return count($selected) === count($registry->all()) ? [] : $selected;
	}

	/**
	 * The ticked boards, or nothing when every one of them is ticked - which is the same
	 * question, asked without an IN list as long as the site has boards.
	 *
	 * @return list<int>
	 */
	private function readBoardFilter(): array {
		$selected = array_values(array_unique($this->readIntList('board')));

		// the global scope is a box of its own, so it counts towards "everything"
		return count($selected) >= count(GLOBAL_BOARD_ARRAY) + 1 ? [] : $selected;
	}

	/** One text field off the query string; an array where a string belongs reads as blank. */
	private function readText(string $name): string {
		$value = $this->moduleContext->request->getParameter($name, 'GET', '');

		return is_scalar($value) ? trim((string) $value) : '';
	}

	/** A numeric field, or null when it is blank or nonsense. */
	private function readPositiveInt(string $name): ?int {
		$value = (int) $this->readText($name);

		return $value > 0 ? $value : null;
	}

	/** @return list<int> */
	private function readIntList(string $name): array {
		return array_values(array_map('intval', $this->readStringList($name)));
	}

	/** @return list<string> */
	private function readStringList(string $name): array {
		$value = $this->moduleContext->request->getParameter($name, 'GET', []);

		return is_array($value) ? array_values(array_filter(array_map('strval', $value), 'strlen')) : [];
	}

	/** Whether anything at all is being filtered on, which is what opens the form on arrival. */
	private function hasActiveFilters(array $filters): bool {
		foreach (['banId', 'general', 'reason', 'staff', 'postNumber', 'dateAfter', 'dateBefore', 'ip', 'token'] as $key) {
			if (($filters[$key] ?? '') !== '' && ($filters[$key] ?? null) !== null) {
				return true;
			}
		}

		return ($filters['status'] ?? 'all') !== 'all'
			|| ($filters['kind'] ?? 'all') !== 'all'
			|| ($filters['boards'] ?? []) !== []
			|| ($filters['boardUid'] ?? null) !== null
			|| ($filters['checkpoints'] ?? []) !== [];
	}

	/** The current query string minus the page, so paging keeps the filter it is paging. */
	private function buildFilteredPageUrl(): string {
		$params = $this->moduleContext->request->allGet();

		unset($params['page'], $params['mode'], $params['load'], $params['moduleMode']);

		return $this->getModulePageURL($params, false, true);
	}

	/** Every ban filed against one address, which is what clicking an IP in the table opens. */
	private function buildIpFilterUrl(string $ipPattern): string {
		return $this->getModulePageURL(['ip' => $ipPattern], false, true);
	}

	/**
	 * The IP cell: a link to every other ban on the same address, unless this viewer is not
	 * allowed to see addresses at all.
	 */
	private function renderIpCell(string $ipPattern): string {
		if (!$this->banPolicy->canViewIpAddresses()) {
			return sanitizeStr(_T('ban_ip_hidden'));
		}

		return '<a class="banIpLink" href="' . sanitizeStr($this->buildIpFilterUrl($ipPattern))
			. '" title="' . sanitizeStr(_T('ban_ip_filter_title')) . '">'
			. sanitizeStr($ipPattern) . '</a>';
	}

	/**
	 * Ticked ids from a checkbox column.
	 *
	 * @return list<int>
	 */
	private function getSelectedIds(string $field): array {
		$selected = $this->moduleContext->request->getParameter($field, 'POST', []);

		if (!is_array($selected)) {
			$selected = [$selected];
		}

		return array_values(array_filter(array_map('intval', $selected), fn(int $id): bool => $id > 0));
	}

	/**
	 * Newlines become breaks; the rest is staff-authored markup, kept as written.
	 *
	 * @param string|null $ifBlank What an empty field means - the stock "no reason given" for the
	 *                             reason the user reads, and genuinely nothing for optional notes.
	 */
	private function normalizeReason(string $reason, ?string $ifBlank = null): string {
		$reason = trim($reason);

		if ($reason === '') {
			return $ifBlank ?? _T('ban_no_reason');
		}

		return str_replace(["\r\n", "\n", "\r"], '<br />', $reason);
	}

	private function buildPostUrl(banEntry $ban): string {
		if ($ban->postUid === null) {
			return '';
		}

		return $this->getModulePageURL(['pageName' => 'view', 'banId' => $ban->id], false, true) . '#post' . $ban->postUid;
	}

	/** Returns the date already wrapped in postDate spans, so callers must not escape it. */
	private function formatDate(int $timestamp): string {
		return $this->moduleContext->postDateFormatter->formatFromTimestamp($timestamp);
	}

	/** @return array<string, string> Column headings and chrome every ban page shares. */
	private function getSharedTemplateValues(): array {
		return [
			'{$MODULE_URL}' => sanitizeStr($this->modulePageUrl),
			'{$APPEALS_URL}' => sanitizeStr($this->appealsUrl),
			'{$APPEALS_TEXT}' => sanitizeStr(_T('ban_appeal_nav')),
			'{$CSRF_TOKEN}' => getCsrfHiddenInput(),
			'{$TH_IP}' => sanitizeStr(_T('ban_th_ip')),
			'{$TH_BOARD}' => sanitizeStr(_T('ban_th_board')),
			'{$TH_FILED_BY}' => sanitizeStr(_T('ban_th_filed_by')),
			'{$TH_FILED_AT}' => sanitizeStr(_T('ban_th_filed_at')),
			'{$TH_DURATION}' => sanitizeStr(_T('ban_th_duration')),
			'{$TH_REASON}' => sanitizeStr(_T('ban_th_reason')),
			'{$TH_POST}' => sanitizeStr(_T('ban_th_post')),
			'{$TH_SEEN}' => sanitizeStr(_T('ban_th_seen')),
			'{$TH_STATUS}' => sanitizeStr(_T('ban_th_status')),
			'{$TH_BAN}' => sanitizeStr(_T('ban_appeal_th_ban')),
			'{$TH_APPELLANT}' => sanitizeStr(_T('ban_appeal_th_appellant')),
			'{$TH_FILED}' => sanitizeStr(_T('ban_appeal_th_filed')),
			'{$TH_ACTIONS}' => sanitizeStr(_T('ban_appeal_th_actions')),
		];
	}

	/** @return array<string, string> Row labels for the single-ban detail table. */
	private function getBanFieldLabels(): array {
		return [
			'{$LABEL_IP}' => sanitizeStr(_T('ban_th_ip')),
			'{$LABEL_BOARD}' => sanitizeStr(_T('ban_th_board')),
			'{$LABEL_TYPE}' => sanitizeStr(_T('ban_view_label_type')),
			'{$LABEL_STATUS}' => sanitizeStr(_T('ban_th_status')),
			'{$LABEL_FILED_BY}' => sanitizeStr(_T('ban_th_filed_by')),
			'{$LABEL_FILED_AT}' => sanitizeStr(_T('ban_th_filed_at')),
			'{$LABEL_EXPIRES}' => sanitizeStr(_T('ban_view_label_expires')),
			'{$LABEL_DURATION}' => sanitizeStr(_T('ban_th_duration')),
			'{$LABEL_REASON}' => sanitizeStr(_T('ban_th_reason')),
			'{$LABEL_PUBLIC_REASON}' => sanitizeStr(_T('ban_view_label_public_reason')),
			'{$LABEL_PRIVATE_REASON}' => sanitizeStr(_T('ban_view_label_private_reason')),
			'{$LABEL_APPEALS}' => sanitizeStr(_T('ban_view_label_appeals')),
			'{$LABEL_SEEN}' => sanitizeStr(_T('ban_th_seen')),
			'{$LABEL_TOKEN}' => sanitizeStr(_T('ban_view_label_token')),
			'{$LABEL_POST}' => sanitizeStr(_T('ban_th_post')),
			'{$LABEL_REVOKED}' => sanitizeStr(_T('ban_view_label_revoked')),
		];
	}

	private function drawAdminPage(string $templateBlock, array $templateValues, string $pagerHtml = ''): void {
		$contentHtml = $this->moduleContext->adminPageRenderer->ParseBlock($templateBlock, $templateValues);

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
