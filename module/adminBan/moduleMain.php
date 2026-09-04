<?php

namespace Kokonotsuba\Modules\adminBan;

require_once __DIR__ . '/bannedPostPreview.php';

use Kokonotsuba\ban\banCheckpoint;
use Kokonotsuba\ban\banImagePicker;
use Kokonotsuba\ban\banDuration;
use Kokonotsuba\ban\banEntry;
use Kokonotsuba\error\BoardException;
use Kokonotsuba\module_classes\abstractModuleMain;
use Kokonotsuba\module_classes\traits\BanCheckpointTrait;
use Kokonotsuba\post\Post;
use Kokonotsuba\module_classes\traits\listeners\BelowCommentListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\PostsPrefetchListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\FormFuncsListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\ModuleHeaderListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\RegistBeginListenerTrait;

use function Kokonotsuba\libraries\_T;
use function Kokonotsuba\libraries\getCsrfHiddenInput;

use function Kokonotsuba\libraries\requirePostWithCsrf;
use function Puchiko\request\redirect;
use function Puchiko\json\renderPrivateJsonPage;
use function Puchiko\strings\sanitizeStr;

use const Kokonotsuba\GLOBAL_BOARD_UID;

/**
 * The reader-facing half of the ban system.
 *
 * Enforces the posting checkpoint, owns the "Banned?" page every other checkpoint links to, and
 * takes appeals. Everything it knows about bans it asks banService for; the module holds no ban
 * logic of its own beyond how a ban looks on the page.
 */
class moduleMain extends abstractModuleMain {
	use BanCheckpointTrait;
	use BelowCommentListenerTrait;
	use PostsPrefetchListenerTrait;
	use FormFuncsListenerTrait;
	use ModuleHeaderListenerTrait;
	use RegistBeginListenerTrait;

	private banImagePicker $banImagePicker;
	private ?bannedPostPreview $bannedPostPreview = null;
	private string $modulePageUrl = '';
	private string $statusUrl = '';
	private string $markerUrl = '';
	private string $markerCookie = '';

	/** Public ban notices by post UID, filled a thread at a time. Missing posts cache as ''. */
	private array $publicReasons = [];

	public function getName(): string {
		return 'K! Admin Ban';
	}

	public function getVersion(): string {
		return 'Kokonotsuba 2025';
	}

	public function initialize(): void {
		$staticUrl = $this->getConfig('STATIC_URL');

		// Drawn from static/image/ban/ on every render, so the notice is not always the same
		// picture - one file in there is a rotation of one, which is still the rotation.
		$this->banImagePicker = new banImagePicker((string) $this->getConfig('STATIC_PATH'), $staticUrl);
		$this->modulePageUrl = $this->getModulePageURL([], false);
		$this->statusUrl = $this->getModulePageURL(['status' => '1'], false, true);
		$this->markerCookie = trim((string) $this->getModuleConfig('BAN_MARKER_COOKIE', 'yay'));
		$this->markerUrl = trim((string) $this->getModuleConfig('BAN_MARKER_URL', ''))
			?: $staticUrl . 'html/yay.html';

		// Everything that blocks a checkpoint anywhere in the engine renders through here, so the
		// ban page looks the same whether it was posting, voting or a PM that hit it.
		$this->getBanService()->setBanPagePresenter(
			fn(banEntry $ban, banCheckpoint|string|null $checkpoint): string => $this->drawBanPage($ban, $checkpoint),
			$this->getModulePageURL([], false, true)
		);

		$this->listenRegistBegin('onRegistBegin');
		$this->listenPostsPrefetch('onPrefetchPublicReasons');
		$this->listenBelowComment('onRenderPublicBanNotice');
		$this->listenModuleHeader('onGenerateModuleHeader');
		$this->addFormFuncLink($this->modulePageUrl, _T('ban_form_func_link'));
	}

	/** Posting is a checkpoint like any other; this is where it is entered. */
	public function onRegistBegin(array &$registInfo): void {
		$this->assertNotBanned(banCheckpoint::POST, null, $registInfo['ip'] ?? null);
	}

	/**
	 * The token mirror.
	 *
	 * The cookie is set server-side; this copies it into localStorage and puts it back when only
	 * the cookie was cleared. Nothing is measured about the browser - it is one random value the
	 * browser agreed to hold on to.
	 */
	private function onGenerateModuleHeader(string &$moduleHeader): void {
		$moduleHeader .= '<link rel="stylesheet" href="'
			. sanitizeStr($this->getConfig('STATIC_URL') . 'css/module/ban.css') . '">';

		$moduleHeader .= '<meta name="prefsKey" content="'
			. sanitizeStr($this->getBanService()->getTokenCookieName()) . '">';

		$this->includeScript('omamori.js', $moduleHeader, false);

		if (!$this->getModuleConfig('ENABLE_JS_BAN_CHECK', true)) {
			return;
		}

		// Read by akuujin.js: the page asks after itself, which is what lets a ban show on a
		// board served as static HTML, where the server never sees the visitor load anything.
		$moduleHeader .= '<meta name="statusUrl" content="' . sanitizeStr($this->statusUrl) . '">';
		$moduleHeader .= '<meta name="markerUrl" content="' . sanitizeStr($this->markerUrl) . '">';
		$moduleHeader .= '<meta name="markerCookie" content="' . sanitizeStr($this->markerCookie) . '">';

		$this->includeScript('akuujin.js', $moduleHeader, false);
	}

	/**
	 * The "(USER WAS BANNED FOR THIS POST)" notice under a post.
	 *
	 * It is read off the ban rather than baked into the post's comment, so editing a ban's public
	 * reason changes what everyone sees. The whole thread is looked up on the first post that
	 * needs it, so a thread costs one query rather than one per post.
	 */
	private function onRenderPublicBanNotice(string &$belowComment, Post $post, array $threadPosts, bool $adminMode): void {
		$reason = $this->getPublicReason($post, $threadPosts);

		if ($reason === '') {
			return;
		}

		// Staff-authored markup, stored as written.
		$belowComment .= '<div class="banPublicNotice">' . $reason . '</div>';
	}

	/** @param array $threadPosts The posts being rendered alongside this one. */
	/** @param Post[] $posts */
	private function onPrefetchPublicReasons(array $posts): void {
		$uids = [];
		foreach ($posts as $post) {
			if ($post instanceof Post && !array_key_exists($post->getUid(), $this->publicReasons)) {
				$uids[] = $post->getUid();
			}
		}
		if ($uids === []) {
			return;
		}
		$found = $this->getBanService()->getPublicReasonsForPosts($uids);
		foreach ($uids as $uid) {
			$this->publicReasons[$uid] = $found[$uid] ?? '';
		}
	}

	private function getPublicReason(Post $post, array $threadPosts): string {
		$postUid = $post->getUid();

		if (array_key_exists($postUid, $this->publicReasons)) {
			return $this->publicReasons[$postUid];
		}

		$uids = [$postUid];

		foreach ($threadPosts as $threadPost) {
			if ($threadPost instanceof Post) {
				$uids[] = $threadPost->getUid();
			}
		}

		$uids = array_values(array_unique(array_filter($uids)));
		$found = $this->getBanService()->getPublicReasonsForPosts($uids);

		// Cache the misses too, so a thread full of unbanned posts is not looked up again.
		foreach ($uids as $uid) {
			$this->publicReasons[$uid] = $found[$uid] ?? '';
		}

		return $this->publicReasons[$postUid] ?? '';
	}

	// ─── Pages ────────────────────────────────────────────────────

	public function ModulePage(): void {
		$request = $this->moduleContext->request;

		if ($request->hasParameter('status', 'GET')) {
			$this->outputBanStatus();
			return;
		}

		if ($request->isPost()) {
			$this->handleAppealSubmission();
			return;
		}

		$ip = (string) $request->userIp();
		$bans = $this->getBanService()->findVisible($ip);
		$now = $request->getRequestTime();

		// Only what is still in force gets a place on the ban page proper: an expired row stops
		// nothing, so listing it beside a live ban just tells somebody they are banned for
		// something they are not. A warning counts only until it has been read - after that the
		// page should not still greet them with it either.
		$live = array_values(array_filter(
			$bans,
			fn(banEntry $ban): bool => $ban->isActive($now) && !$ban->isWarning
		));

		$unreadWarning = array_values(array_filter(
			$bans,
			fn(banEntry $ban): bool => $ban->isWarning && !$ban->hasBeenSeen()
		));

		// A ban that has run out is not finished with until whoever it held has been told so.
		// With nothing else against them, this page is that telling - it says they are free
		// again, and saying it is what lets go of the ban.
		$lapsed = array_values(array_filter(
			$bans,
			fn(banEntry $ban): bool => $ban->awaitsExpiryNotice($now)
		));

		if ($live === [] && $unreadWarning === [] && $lapsed !== []) {
			foreach ($lapsed as $ban) {
				$this->getBanService()->markExpiryNoticeSeen($ban);
			}

			echo $this->renderBanPage($lapsed, null, null, true);
			return;
		}

		$visible = array_merge($live, $unreadWarning);
		$primary = $visible[0] ?? null;

		// Opening this page counts as having seen what it showed, cookies or not.
		foreach ($visible as $ban) {
			$this->getBanService()->markSeen($ban);
		}

		echo $this->renderBanPage($visible, $primary, null);
	}

	/**
	 * "Is this browser banned?", for akuujin.js.
	 *
	 * One bit and nothing else. What a ban stops, how long it has left and why are the ban
	 * page's business, and the ban page is reached by being stopped: telling a browser any of it
	 * up front only helps somebody work out which of their addresses or browsers is still clean.
	 *
	 * Warnings are left out on purpose: a warning is meant to interrupt the next thing somebody
	 * does, once, and spending that on a request they never saw would waste it. Nothing is
	 * marked seen and nothing is written here; this only reports.
	 */
	private function outputBanStatus(): void {
		$blocking = array_filter(
			$this->getBanService()->findEnforceable(
				(string) $this->moduleContext->request->userIp(),
				$this->moduleContext->board->getBoardUID()
			),
			fn(banEntry $ban): bool => !$ban->isWarning && $ban->checkpoints !== []
		);

		renderPrivateJsonPage(['banned' => $blocking !== []]);
	}

	/**
	 * The hidden frame that leaves the marker cookie behind.
	 *
	 * It is loaded from the marker page's own origin, which is how one cookie ends up covering
	 * every board on the domain rather than the one board that framed it. Empty when the marker
	 * is switched off, which is all it takes to stop using one.
	 */
	private function buildMarkerFrame(): string {
		if ($this->markerUrl === '' || $this->markerCookie === ''
			|| !$this->getModuleConfig('ENABLE_JS_BAN_CHECK', true)) {
			return '';
		}

		$url = $this->markerUrl
			. (str_contains($this->markerUrl, '?') ? '&' : '?')
			. 'c=' . rawurlencode($this->markerCookie);

		return '<iframe src="' . sanitizeStr($url)
			. '" style="display:none" aria-hidden="true" tabindex="-1" title="ban marker"></iframe>';
	}

	/**
	 * Ban page as a complete document, for banServices's presenter - it is rendered mid-request
	 * from wherever the checkpoint was, so it cannot come back as a return value.
	 */
	private function drawBanPage(banEntry $ban, banCheckpoint|string|null $checkpoint): string {
		// A lapsed ban stops nothing: it was shown to say it is over, so the page reads as the
		// all-clear rather than as something still holding them.
		if ($ban->awaitsExpiryNotice($this->moduleContext->request->getRequestTime())) {
			return $this->renderBanPage([$ban], null, null, true);
		}

		return $this->renderBanPage([$ban], $ban, $checkpoint);
	}

	/**
	 * @param list<banEntry> $bans     The bans this page is showing.
	 * @param banEntry|null  $primary  The one that stopped them, if any.
	 * @param bool           $expiredNotice Whether this is the "your ban is over" page.
	 */
	private function renderBanPage(
		array $bans,
		?banEntry $primary,
		banCheckpoint|string|null $checkpoint,
		bool $expiredNotice = false
	): string {
		$now = $this->moduleContext->request->getRequestTime();
		$isBanned = $primary !== null;

		$image = $isBanned
			? $this->banImagePicker->random()
			: $this->banImagePicker->imageFor('image/notbanned.png');

		$templateValues = [
			'{$IS_BANNED}' => $isBanned ? '1' : '',
			'{$BAN_HEADING}' => $isBanned
				? ($primary->isWarning ? _T('ban_page_warned_heading') : _T('ban_page_banned_heading'))
				: _T($expiredNotice ? 'ban_page_expired_heading' : 'ban_page_clear_heading'),
			'{$BAN_IMAGE}' => sanitizeStr($image->url),
			// Measured off the file, so the text beside it is not laid out twice while it loads.
			'{$BAN_IMAGE_DIMENSIONS}' => $image->dimensionAttributes(),
			'{$HAS_ENTRIES}' => $bans === [] ? '' : '1',
			'{$BAN_IMAGE_ALT}' => sanitizeStr($isBanned ? _T('ban_page_image_alt_banned') : _T('ban_page_image_alt_clear')),
			'{$CLEAR_TEXT}' => sanitizeStr(_T($expiredNotice ? 'ban_page_expired_text' : 'ban_page_clear_text')),
			'{$BLOCKED_TEXT}' => $checkpoint === null ? '' : sanitizeStr($this->describeCheckpoint($checkpoint)),
			// The appeal form goes on the ban that is actually stopping them and nowhere else:
			// every visible ban used to carry one, so anybody holding more than one row - a
			// board ban and a global one, say - was shown the same form several times over.
			'{$ENTRIES}' => array_map(
				fn(banEntry $ban): array => $this->buildBanPageEntry($ban, $now, $primary !== null && $ban->id === $primary->id),
				$bans
			),
		];

		$contentHtml = $this->moduleContext->adminPageRenderer->ParseBlock('BAN_PAGE', $templateValues);

		// Somebody looking at this page has just been turned away, so mark the browser here and
		// then rather than leaving it to the next page they load.
		if ($isBanned) {
			$contentHtml .= $this->buildMarkerFrame();
		}

		return $this->moduleContext->adminPageRenderer->ParsePage(
			'GLOBAL_ADMIN_PAGE_CONTENT',
			['{$PAGE_CONTENT}' => $contentHtml, '{$PAGER}' => ''],
			false
		);
	}

	/**
	 * One ban as the reader sees it.
	 *
	 * @param bool $isPrimary Whether this is the ban in force, which is the one that may be appealed.
	 */
	private function buildBanPageEntry(banEntry $ban, int $now, bool $isPrimary): array {
		// The summary beside the picture is the ban itself; the post and the appeal form are
		// what somebody reads afterwards, so they are rendered from their own block underneath.
		$bannedPost = $this->buildBannedPostHtml($ban);
		$appealForm = $isPrimary ? $this->buildAppealSection($ban) : '';

		// The board's name, the dates and the sentences around them go over as separate values:
		// BAN_PAGE_ENTRY is where they are laid out, so the markup can be changed without
		// touching this. Dates are the exception - they arrive pre-wrapped from formatDate().
		$boardTitle = $ban->boardUid === GLOBAL_BOARD_UID ? '' : (string) ($ban->boardTitle ?? '');
		$hasExpiry = !$ban->isWarning && !$ban->isPermanent() && $ban->expiresAt !== null;
		$hasExpired = $hasExpiry && $ban->isExpired($now);

		return [
			'{$BAN_ID}' => (int) $ban->id,
			'{$SCOPE_LABEL}' => sanitizeStr($this->describeBanScope($ban, $boardTitle)),
			'{$BOARD_TITLE}' => sanitizeStr($boardTitle),
			'{$REASON_LABEL}' => sanitizeStr(_T($ban->isWarning ? 'ban_page_reason_label' : 'ban_page_reason_label')),
			// Ban reasons are written by staff and may carry deliberate markup, e.g. a linked rule.
			'{$REASON}' => $ban->reason !== '' ? $ban->reason : sanitizeStr(_T('ban_no_reason')),
			'{$FILED_TEXT}' => _T('ban_page_filed_on', $this->formatDate($ban->filedAt)),
			'{$IS_PERMANENT}' => $ban->isPermanent() ? '1' : '',
			'{$PERMANENT_TEXT}' => _T('ban_page_permanent'),
			'{$HAS_EXPIRY}' => $hasExpiry ? '1' : '',
			'{$EXPIRES_TEXT}' => match (true) {
				!$hasExpiry => '',
				$hasExpired => _T('ban_page_expired_on', $this->formatDate((int) $ban->expiresAt)),
				default => _T(
					'ban_page_expires_on',
					$this->formatDate((int) $ban->expiresAt),
					banDuration::humanize((int) $ban->secondsRemaining($now))
				),
			},
			'{$HAS_BELOW}' => ($bannedPost !== '' || $appealForm !== '') ? '1' : '',
			'{$BANNED_POST}' => $bannedPost,
			'{$APPEAL_FORM}' => $appealForm,
		];
	}

	/**
	 * What the heading says this ban covers; the template writes the board's name after it.
	 *
	 * A ban filed in the global scope stops them everywhere, so it says so rather than naming a
	 * board. Anything else is followed by the board it was filed for, unless that board has
	 * since been removed and there is nothing left to name.
	 */
	private function describeBanScope(banEntry $ban, string $boardTitle): string {
		if ($ban->boardUid === GLOBAL_BOARD_UID) {
			return _T($ban->isWarning ? 'ban_page_scope_global_warning' : 'ban_page_scope_global');
		}

		if ($boardTitle === '') {
			return _T($ban->isWarning ? 'ban_page_type_warning' : 'ban_page_type_ban');
		}

		return _T($ban->isWarning ? 'ban_page_scope_board_warning' : 'ban_page_scope_board');
	}

	private function describeCheckpoint(banCheckpoint|string $checkpoint): string {
		$case = $checkpoint instanceof banCheckpoint ? $checkpoint : banCheckpoint::tryFrom((string) $checkpoint);

		return $case !== null ? $case->blockedMessage() : _T('ban_blocked_generic');
	}

	// ─── The post the ban was filed on ────────────────────────────

	/**
	 * The post a ban names, rendered the way its own board renders it.
	 *
	 * Rendered rather than rebuilt from its columns, so it is the whole post - attachments,
	 * quote links, and every hook a post normally carries, including this module's own public
	 * ban notice, which is a BelowComment listener and so exists only on a real render.
	 *
	 * A post is usually deleted in the same breath as the ban is filed, so it is looked up with
	 * deleted rows included: showing them what they were banned for is the whole point.
	 */
	private function buildBannedPostHtml(banEntry $ban): string {
		if ($ban->postUid === null || !$this->getModuleConfig('SHOW_BANNED_POST', true)) {
			return '';
		}

		$post = $this->moduleContext->postRepository->getPostByUid($ban->postUid, true);

		if (!$post) {
			return '';
		}

		return '<div class="banPostContainer"><h4 class="banPostHeading">'
			. sanitizeStr(_T('ban_page_post_heading'))
			. '</h4> 
					<div class="banPost">' . $this->getBannedPostPreview()->render($post) . '</div>
				</div>';
	}

	/** Built on first use: most ban pages name no post at all. */
	private function getBannedPostPreview(): bannedPostPreview {
		return $this->bannedPostPreview ??= new bannedPostPreview(
			$this->moduleContext->board,
			$this->moduleContext->moduleEngine,
			$this->moduleContext->templateEngine,
			$this->moduleContext->quoteLinkService,
			$this->moduleContext->request
		);
	}

	// ─── Appeals ──────────────────────────────────────────────────

	/** The appeal form, or whatever explains why there isn't one. */
	private function buildAppealSection(banEntry $ban): string {
		if (!$this->getModuleConfig('ENABLE_APPEALS', true)) {
			return '';
		}

		$appeals = $this->getBanService()->getAppealsForBan($ban->id);
		$latest = $appeals[0] ?? null;

		$statusHtml = '';

		if ($latest !== null) {
			$statusHtml = $this->moduleContext->adminPageRenderer->ParseBlock('BAN_APPEAL_STATUS', [
				'{$STATUS_LABEL}' => sanitizeStr($latest->status->label()),
				'{$STATUS_CLASS}' => sanitizeStr($latest->status->rowCssClass()),
				'{$FILED_AT}' => $this->formatDate($latest->filedAt),
				'{$YOUR_REASON}' => sanitizeStr($latest->reason),
				'{$YOUR_REASON_LABEL}' => sanitizeStr(_T('ban_appeal_your_reason')),
				'{$FILED_LABEL}' => sanitizeStr(_T('ban_appeal_filed_label')),
				'{$HAS_NOTE}' => $latest->staffNote !== '' ? '1' : '',
				'{$STAFF_NOTE_LABEL}' => sanitizeStr(_T('ban_appeal_staff_note')),
				'{$STAFF_NOTE}' => sanitizeStr($latest->staffNote),
			]);
		}

		$blocker = $this->getBanService()->getAppealBlocker($ban, $this->getAppealCooldownHours());

		if ($blocker !== null) {
			return $statusHtml . '<p class="banAppealBlocked">' . sanitizeStr($blocker) . '</p>';
		}

		return $statusHtml . $this->moduleContext->adminPageRenderer->ParseBlock('BAN_APPEAL_FORM', [
			'{$MODULE_URL}' => sanitizeStr($this->modulePageUrl),
			'{$CSRF_TOKEN}' => getCsrfHiddenInput(),
			'{$BAN_ID}' => (int) $ban->id,
			'{$APPEAL_HEADING}' => sanitizeStr(_T('ban_appeal_heading')),
			'{$APPEAL_HINT}' => sanitizeStr(_T('ban_appeal_hint')),
			'{$APPEAL_PLACEHOLDER}' => sanitizeStr(_T('ban_appeal_placeholder_reason')),
			'{$APPEAL_SUBMIT}' => sanitizeStr(_T('ban_appeal_submit')),
			'{$MAX_LENGTH}' => (int) $this->getModuleConfig('APPEAL_MAX_LENGTH', 1000),
		]);
	}

	private function handleAppealSubmission(): void {
		$request = $this->moduleContext->request;

		requirePostWithCsrf($request);

		if (!$this->getModuleConfig('ENABLE_APPEALS', true)) {
			throw new BoardException(_T('ban_appeal_error_disabled'));
		}

		$banId = (int) $request->getParameter('banId', 'POST', 0);
		$ban = $banId > 0 ? $this->getBanService()->getBan($banId) : null;

		// Only against a ban that is actually being served to this visitor, so a guessed id
		// cannot be used to appeal somebody else's ban.
		if ($ban === null || !$this->isBanHeldAgainstViewer($ban)) {
			throw new BoardException(_T('ban_appeal_error_not_found'));
		}

		$reason = trim((string) $request->getParameter('reason', 'POST', ''));
		$maxLength = (int) $this->getModuleConfig('APPEAL_MAX_LENGTH', 1000);

		if (mb_strlen($reason) > $maxLength) {
			throw new BoardException(_T('ban_appeal_error_too_long', $maxLength));
		}

		$this->getBanService()->fileAppeal($ban, $reason, $this->getAppealCooldownHours());

		redirect($this->modulePageUrl . '&appealed=1');
		exit;
	}

	private function isBanHeldAgainstViewer(banEntry $ban): bool {
		$ip = (string) $this->moduleContext->request->userIp();

		foreach ($this->getBanService()->findVisible($ip) as $visible) {
			if ($visible->id === $ban->id) {
				return true;
			}
		}

		return false;
	}

	private function getAppealCooldownHours(): int {
		return max(0, (int) $this->getModuleConfig('APPEAL_COOLDOWN_HOURS', 24));
	}

	/** Returns the date already wrapped in postDate spans, so callers must not escape it. */
	private function formatDate(int $timestamp): string {
		return $this->moduleContext->postDateFormatter->formatFromTimestamp($timestamp);
	}
}
