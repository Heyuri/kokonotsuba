<?php

namespace Kokonotsuba\ban;

use Kokonotsuba\action_log\actionLogReferences;
use Kokonotsuba\action_log\actionLoggerService;
use Kokonotsuba\action_log\actionType;
use Kokonotsuba\cookie\cookieService;
use Kokonotsuba\error\BoardException;
use Kokonotsuba\request\request;

use function Kokonotsuba\libraries\_T;
use function Puchiko\json\renderJsonErrorPage;

use const Kokonotsuba\GLOBAL_BOARD_UID;

/**
 * The one place the rest of the engine talks to about bans.
 *
 * Anything a ban can stop is a *checkpoint*: a module calls assertNotBanned() with a checkpoint
 * key before doing the thing, and that is the whole integration. Whether a given ban blocks a
 * given checkpoint is the moderator's decision at ban time, not the module's, so a module never
 * has to know what kinds of ban exist.
 *
 *   $banService->assertNotBanned(banCheckpoint::SOUDANE);
 *
 * A ban that stops someone is shown to them there and then — a JSON error for AJAX callers, the
 * full ban page for anyone else — and marked as seen on the way past, which is how the admin
 * table knows whether a ban ever landed.
 */
class banService {
	/** Ban page renderer, supplied by the adminBan module; null when that module is off. */
	private $banPageRenderer = null;

	/** Ban page URL, set alongside the renderer. */
	private string $banPageUrl = '';

	private readonly banCheckpointRegistry $checkpoints;

	/** The token this request is carrying, resolved once. */
	private ?array $visitorTokenHashes = null;

	/** The cookie read, verified and settled - including having found nothing worth honouring. */
	private ?string $resolvedToken = null;
	private bool $tokenResolved = false;

	/** Whether the token came in on the request rather than being minted during it. */
	private bool $tokenFromRequest = false;

	public function __construct(
		private readonly banRepository $banRepository,
		private readonly banAppealRepository $banAppealRepository,
		private readonly request $request,
		private readonly cookieService $cookieService,
		private readonly string $tokenCookieName,
		private readonly int $tokenCookieLifetimeDays,
		private readonly visitorTokenSigner $tokenSigner,
		/** Optional: without it a ban still stops what it stops, it just leaves no log line. */
		private readonly ?actionLoggerService $actionLoggerService = null,
	) {
		$this->checkpoints = new banCheckpointRegistry();
	}

	// ─── Checkpoints ──────────────────────────────────────────────

	public function getCheckpointRegistry(): banCheckpointRegistry {
		return $this->checkpoints;
	}

	/**
	 * Add a checkpoint of your own, from a module's initialize().
	 *
	 * It becomes another checkbox on the ban form; enforce it by passing the same key to
	 * assertNotBanned().
	 */
	public function registerCheckpoint(string $key, string $label, bool $default = false): void {
		$this->checkpoints->register($key, $label, $default);
	}

	// ─── Enforcement ──────────────────────────────────────────────

	/**
	 * Stop here if the visitor is banned from this checkpoint.
	 *
	 * Does not return when it finds one: the ban is shown and the request ends.
	 *
	 * @param banCheckpoint|string $checkpoint Checkpoint being entered.
	 * @param int|null             $boardUid   Board being acted on; defaults to the global scope only.
	 * @param string|null          $ip         Address to test; defaults to the requesting address.
	 */
	public function assertNotBanned(banCheckpoint|string $checkpoint, ?int $boardUid = null, ?string $ip = null): void {
		$key = self::checkpointKey($checkpoint);
		$now = $this->request->getRequestTime();
		$held = $this->findHeld($ip ?? (string) $this->request->userIp(), $boardUid);

		$ban = $this->firstMatching($held, fn(banEntry $ban): bool => $ban->isActive($now) && $ban->blocks($key));

		// A ban is not over until the person it stopped has been told so: once it lapses, the
		// next thing it would have blocked is interrupted one last time to say they are free
		// again, and that telling is what finally lets go of the ban.
		$ban ??= $this->firstMatching($held, fn(banEntry $ban): bool => $ban->awaitsExpiryNotice($now) && $ban->blocks($key));

		// A warning blocks nothing, but it has to be read to be a warning at all: the first
		// action after one is filed is interrupted to show it, and once shown it never
		// interrupts again.
		$ban ??= $this->firstMatching($held, fn(banEntry $ban): bool => $ban->isWarning && !$ban->hasBeenSeen());

		if ($ban === null) {
			return;
		}

		$this->presentBan($ban, $checkpoint);
	}

	/**
	 * @param list<banEntry>            $bans
	 * @param callable(banEntry): bool  $predicate
	 */
	private function firstMatching(array $bans, callable $predicate): ?banEntry {
		foreach ($bans as $ban) {
			if ($predicate($ban)) {
				return $ban;
			}
		}

		return null;
	}

	private static function checkpointKey(banCheckpoint|string $checkpoint): string {
		return $checkpoint instanceof banCheckpoint ? $checkpoint->value : strtolower($checkpoint);
	}

	/** A warning filed against this visitor that they have not been shown yet. */
	public function findUnreadWarning(?int $boardUid = null, ?string $ip = null): ?banEntry {
		$ip ??= (string) $this->request->userIp();

		foreach ($this->findEnforceable($ip, $boardUid) as $ban) {
			if ($ban->isWarning && !$ban->hasBeenSeen()) {
				return $ban;
			}
		}

		return null;
	}

	/**
	 * The ban that would stop this checkpoint, or null when nothing does.
	 *
	 * Use this when you want to know without the request ending — otherwise call
	 * assertNotBanned() and let it handle the response.
	 */
	public function findBlockingBan(banCheckpoint|string $checkpoint, ?int $boardUid = null, ?string $ip = null): ?banEntry {
		$key = self::checkpointKey($checkpoint);
		$ip ??= (string) $this->request->userIp();

		return $this->firstMatching(
			$this->findEnforceable($ip, $boardUid),
			fn(banEntry $ban): bool => $ban->blocks($key)
		);
	}

	/**
	 * A lapsed ban that blocked this checkpoint and still owes its expiry notice.
	 *
	 * It stops nothing any more; it is only owed the one interruption that tells whoever it held
	 * that they are free again.
	 */
	public function findLapsedAwaitingNotice(banCheckpoint|string $checkpoint, ?int $boardUid = null, ?string $ip = null): ?banEntry {
		$key = self::checkpointKey($checkpoint);
		$now = $this->request->getRequestTime();

		return $this->firstMatching(
			$this->findHeld($ip ?? (string) $this->request->userIp(), $boardUid),
			fn(banEntry $ban): bool => $ban->awaitsExpiryNotice($now) && $ban->blocks($key)
		);
	}

	/** Whether anything at all is currently held against this address. */
	public function isBanned(?string $ip = null, ?int $boardUid = null): bool {
		return $this->findEnforceable($ip ?? (string) $this->request->userIp(), $boardUid) !== [];
	}

	/**
	 * Bans in force against this address on this board.
	 *
	 * @return list<banEntry>
	 */
	public function findEnforceable(string $ip, ?int $boardUid = null): array {
		$now = $this->request->getRequestTime();

		return array_values(array_filter(
			$this->findHeld($ip, $boardUid),
			fn(banEntry $ban): bool => $ban->isActive($now)
		));
	}

	/**
	 * Everything still holding something over this address on this board: the bans in force, plus
	 * the lapsed ones whose expiry notice has not been given yet.
	 *
	 * The repository narrows by exact address, wildcard flag and token; the wildcard patterns it
	 * hands back are compared here.
	 *
	 * @return list<banEntry>
	 */
	private function findHeld(string $ip, ?int $boardUid = null): array {
		$boardUids = [GLOBAL_BOARD_UID];

		if ($boardUid !== null && $boardUid !== GLOBAL_BOARD_UID) {
			$boardUids[] = $boardUid;
		}

		$candidates = $this->banRepository->findEnforceableFor($ip, $boardUids, $this->getVisitorTokenHashes());

		return array_values(array_filter(
			$candidates,
			fn(banEntry $ban): bool => $this->banApplies($ban, $ip)
		));
	}

	/** Everything currently held against this address, for their own ban page. */
	public function findVisible(string $ip): array {
		return array_values(array_filter(
			$this->banRepository->findVisibleFor($ip, $this->getVisitorTokenHashes()),
			fn(banEntry $ban): bool => $this->banApplies($ban, $ip)
		));
	}

	/**
	 * A candidate row only really applies when its address pattern matches, or when it was tied
	 * to the browser this visitor is still using.
	 */
	private function banApplies(banEntry $ban, string $ip): bool {
		if ($ban->visitorTokenHash !== null && in_array($ban->visitorTokenHash, $this->getVisitorTokenHashes(), true)) {
			return true;
		}

		if (!$ban->isWildcard) {
			return $ban->ipPattern === $ip;
		}

		return ipPatternMatcher::matches($ip, $ban->ipPattern);
	}

	/**
	 * Show a ban to the person it stopped and end the request.
	 *
	 * @return never
	 */
	public function presentBan(banEntry $ban, banCheckpoint|string|null $checkpoint = null): void {
		$lapsed = $ban->awaitsExpiryNotice($this->request->getRequestTime());

		$this->logTrigger($ban, $checkpoint, $lapsed);

		$this->markSeen($ban);

		// Showing it is the notice, so the ban has now been let go of. The row in hand still
		// reads as owing one, which is what the page below renders from.
		if ($lapsed) {
			$this->markExpiryNoticeSeen($ban);
		}

		if ($this->request->isAjax()) {
			$message = $lapsed ? _T('ban_expired_notice') : $this->buildBlockedMessage($checkpoint);

			renderJsonErrorPage($message, 403);
		}

		if ($this->banPageRenderer !== null) {
			die(($this->banPageRenderer)($ban, $checkpoint));
		}

		throw new BoardException($lapsed ? _T('ban_expired_notice') : $this->buildBlockedMessage($checkpoint));
	}

	/**
	 * Note in the action log that a ban stopped somebody.
	 *
	 * Logged as the visitor rather than as staff: this is something a ban did to whoever walked
	 * into it, not something a moderator did. The ban itself is referenced, so the entry links
	 * straight to it.
	 */
	private function logTrigger(banEntry $ban, banCheckpoint|string|null $checkpoint, bool $lapsed): void {
		if ($this->actionLoggerService === null) {
			return;
		}

		$noun = $ban->isWarning ? 'Warning' : ($ban->isMute ? 'Mute' : 'Ban');
		$reference = actionLogReferences::reference('ban', $ban->id, $noun . ' #' . $ban->id);

		if ($lapsed) {
			$message = $reference . ' has lapsed, notice shown';
		} elseif ($ban->isWarning) {
			$message = $reference . ' shown';
		} else {
			$key = $checkpoint === null ? '' : self::checkpointKey($checkpoint);
			$where = $key === '' ? '' : ' from ' . $this->checkpoints->labelFor($key);

			$message = $reference . ' blocked a user' . $where;
		}

		$this->actionLoggerService->logAction($message, $ban->boardUid, actionType::BAN_TRIGGER, true);
	}

	/** "You are banned from voting. [View ban details]", as BBCode the JSON error renderer expands. */
	private function buildBlockedMessage(banCheckpoint|string|null $checkpoint): string {
		$case = $checkpoint instanceof banCheckpoint
			? $checkpoint
			: ($checkpoint === null ? null : banCheckpoint::tryFrom((string) $checkpoint));

		$message = $case !== null ? $case->blockedMessage() : _T('ban_blocked_generic');
		$url = $this->getBanPageUrl();

		return $url === '' ? $message : $message . ' [url=' . $url . ']' . _T('ban_view_details') . '[/url]';
	}

	public function getBanPageUrl(): string {
		return $this->banPageUrl;
	}

	/**
	 * Let the adminBan module own how a ban looks. Without it the engine still enforces bans, it
	 * just reports them as a plain error.
	 *
	 * @param callable(banEntry, banCheckpoint|string|null): string $renderer
	 */
	public function setBanPagePresenter(callable $renderer, string $banPageUrl): void {
		$this->banPageRenderer = $renderer;
		$this->banPageUrl = $banPageUrl;
	}

	/**
	 * Note that the banned party has now seen this ban.
	 *
	 * Whether their browser handed back a token cookie is recorded with it, which is what lets
	 * the admin table say "Seen (cookies disabled)" rather than guessing.
	 */
	public function markSeen(banEntry $ban): void {
		if ($ban->hasBeenSeen()) {
			return;
		}

		$this->banRepository->markSeen($ban->id, $this->getIncomingVisitorToken() !== null);
	}

	/**
	 * Note that the banned party has been told this ban ran out.
	 *
	 * That telling is the last thing the ban does: after it, the row stops interrupting anybody.
	 */
	public function markExpiryNoticeSeen(banEntry $ban): void {
		if ($ban->hasSeenExpiryNotice()) {
			return;
		}

		$this->banRepository->markExpirySeen($ban->id);
	}

	// ─── Visitor tokens ───────────────────────────────────────────

	/**
	 * The token this request arrived with, or null when the browser kept none the engine minted.
	 *
	 * A value that does not verify is treated exactly as a missing one: nothing is read out of
	 * it, and issueVisitorToken() replaces it. So editing the cookie - or the copies the mirror
	 * keeps - gets a visitor a new token rather than one of their choosing.
	 */
	public function getVisitorToken(): ?string {
		if ($this->tokenResolved) {
			return $this->resolvedToken;
		}

		$this->tokenResolved = true;

		return $this->resolvedToken = $this->readVisitorToken();
	}

	/**
	 * The token the browser actually handed back, or null when it handed back none.
	 *
	 * Unlike getVisitorToken() this never counts a token minted during this request: every
	 * visitor is issued one on arrival, so a browser keeping no cookies still has a token in
	 * hand by the time anything asks. Use this wherever a missing token is meant to mean
	 * something - "cookies disabled", or an association there is no evidence for.
	 */
	public function getIncomingVisitorToken(): ?string {
		$token = $this->getVisitorToken();

		return $this->tokenFromRequest ? $token : null;
	}

	/** The cookie, verified; the one place a token is taken from a request. */
	private function readVisitorToken(): ?string {
		$raw = $this->cookieService->get($this->tokenCookieName, '');

		// `Cookie: koko[]=x` makes PHP hand back an array; casting that to string is a
		// warning and a value that verifies as nothing anyway.
		if (!is_string($raw) || $raw === '') {
			return null;
		}

		$verified = $this->tokenSigner->verify($raw);

		if ($verified === null) {
			// Anything the engine did not sign is somebody's own invention, including a token
			// from before signing existed. Both get nothing, and issueVisitorToken() replaces it.
			return null;
		}

		$this->tokenFromRequest = true;

		return $verified;
	}

	/** Issue a token to a visitor who has none. Cheap: a cookie write, no query. */
	public function issueVisitorToken(): ?string {
		$existing = $this->getVisitorToken();

		if ($existing !== null) {
			return $existing;
		}

		if (headers_sent()) {
			return null;
		}

		$signed = $this->tokenSigner->mint();

		$this->writeTokenCookie($signed);

		$this->tokenResolved = true;

		return $this->resolvedToken = $this->tokenSigner->verify($signed);
	}

	private function writeTokenCookie(string $signedValue): void {
		if (headers_sent()) {
			return;
		}

		$this->cookieService->set(
			$this->tokenCookieName,
			$signedValue,
			$this->request->getRequestTime() + ($this->tokenCookieLifetimeDays * 86400),
			'/',
			'',
			$this->request->isHttps(),
			false // readable by the mirror script, which is the point of it
		);
	}

	public function getTokenCookieName(): string {
		return $this->tokenCookieName;
	}

	/**
	 * The browser token hashes worth testing a ban against: this visitor's, and only that.
	 *
	 * Not every browser the address has been seen behind - on a shared or recycled address those
	 * belong to other people, and matching them would quietly turn one browser's ban into a ban
	 * on everybody behind that address. The address side of a ban is the ip_pattern's job.
	 *
	 * @return list<string>
	 */
	private function getVisitorTokenHashes(): array {
		if ($this->visitorTokenHashes !== null) {
			return $this->visitorTokenHashes;
		}

		$own = $this->getVisitorToken();

		return $this->visitorTokenHashes = $own === null ? [] : [$this->hashToken($own)];
	}

	/** The short label for a token, as staff see it beside an address. */
	public function hashToken(string $token): string {
		return $this->tokenSigner->tokenHash($token);
	}

	/**
	 * The label to record against something this visitor is doing right now.
	 *
	 * An empty string when the browser handed nothing back, which is a fact worth storing rather
	 * than a gap: every visitor is issued a token on arrival, so a browser that still arrives
	 * without one is refusing to keep them.
	 */
	public function hashIncomingToken(): string {
		$token = $this->getIncomingVisitorToken();

		return $token === null ? '' : $this->hashToken($token);
	}

	/**
	 * The browser to tie a ban to: the one that made the post being banned.
	 *
	 * Only a post can answer this. An address cannot - it is shared, and it is reused - and a
	 * post recorded as cookieless ('') is not a browser to tie anything to.
	 */
	private function resolveTokenHashForPost(?int $postUid): ?string {
		if ($postUid === null || $postUid <= 0) {
			return null;
		}

		$hash = $this->banRepository->findVisitorTokenHashForPost($postUid);

		return ($hash ?? '') === '' ? null : $hash;
	}

	// ─── Filing and lifting ───────────────────────────────────────

	/**
	 * File a ban or a warning.
	 *
	 * @param list<string> $checkpoints Checkpoint keys this ban blocks; empty makes it a warning.
	 * @param int|null     $expiresAt   Unix expiry, or null for a ban that never lapses.
	 * @param bool         $isMute      A short automatic ban, thrown away once it lapses.
	 * @param string       $publicReason Notice shown under the post they were banned for; blank for none.
	 * @param string       $privateReason Staff-only note, never shown to the banned party.
	 * @param bool         $rejectsAppeals Whether this ban refuses appeals outright.
	 */
	public function fileBan(
		string $ipPattern,
		int $boardUid,
		array $checkpoints,
		?int $expiresAt,
		string $reason,
		?int $filedBy,
		?int $postUid = null,
		bool $tieVisitorToken = false,
		bool $isWarning = false,
		bool $isMute = false,
		string $publicReason = '',
		string $privateReason = '',
		bool $rejectsAppeals = false,
	): int {
		$ipPattern = trim($ipPattern);

		if ($ipPattern === '') {
			throw new BoardException(_T('ban_error_no_ip'));
		}

		$checkpoints = $isWarning ? [] : $this->checkpoints->filterKnown($checkpoints);

		return $this->banRepository->insertBan([
			'board_uid' => $boardUid,
			'ip_pattern' => $ipPattern,
			'is_wildcard' => ipPatternMatcher::isWildcard($ipPattern) ? 1 : 0,
			'visitor_token_hash' => $tieVisitorToken ? $this->resolveTokenHashForPost($postUid) : null,
			'post_uid' => $postUid !== null && $postUid > 0 ? $postUid : null,
			'reason' => $reason,
			'public_reason' => $publicReason,
			'private_reason' => $privateReason,
			'checkpoints' => implode(',', $checkpoints),
			'rejects_appeals' => $rejectsAppeals ? 1 : 0,
			'is_warning' => $isWarning ? 1 : 0,
			'is_mute' => $isMute ? 1 : 0,
			'filed_at' => banRepository::now(),
			'expires_at' => $expiresAt === null ? null : date('Y-m-d H:i:s', $expiresAt),
			'filed_by' => $filedBy,
		]);
	}

	/**
	 * File the same ban against several addresses at once.
	 *
	 * @param list<string> $ipPatterns
	 * @param list<string> $checkpoints
	 * @return list<int> The new ban ids.
	 */
	public function fileBans(
		array $ipPatterns,
		int $boardUid,
		array $checkpoints,
		?int $expiresAt,
		string $reason,
		?int $filedBy,
		bool $isMute = false,
	): array {
		$ids = [];

		foreach (array_unique(array_filter($ipPatterns)) as $ipPattern) {
			$ids[] = $this->fileBan(
				$ipPattern, $boardUid, $checkpoints, $expiresAt, $reason, $filedBy, null, false, false, $isMute
			);
		}

		// Filing mutes is the moment new ones appear, so it is also the moment to sweep up the
		// ones that have run out - no cron, and the table never fills with dead mutes.
		if ($isMute) {
			$this->pruneExpiredMutes();
		}

		return $ids;
	}

	/**
	 * Delete mutes that have lapsed.
	 *
	 * @return int Rows removed.
	 */
	public function pruneExpiredMutes(): int {
		return $this->banRepository->deleteExpiredMutes();
	}

	/**
	 * Change an existing ban.
	 *
	 * Only the keys present in $fields are touched, so a form that does not offer a field cannot
	 * silently blank it. The address is re-examined for wildcards, and the browser tie is re-read
	 * off the post or cleared.
	 *
	 * @param array{
	 *     ipPattern?: string, checkpoints?: list<string>, expiresAt?: int|null, reason?: string,
	 *     publicReason?: string, privateReason?: string, rejectsAppeals?: bool, tieVisitorToken?: bool
	 * } $fields
	 */
	public function editBan(banEntry $ban, array $fields): void {
		$data = [];

		if (array_key_exists('ipPattern', $fields)) {
			$ipPattern = trim((string) $fields['ipPattern']);

			if ($ipPattern === '') {
				throw new BoardException(_T('ban_error_no_ip'));
			}

			$data['ip_pattern'] = $ipPattern;
			$data['is_wildcard'] = ipPatternMatcher::isWildcard($ipPattern) ? 1 : 0;
		}

		if (array_key_exists('checkpoints', $fields)) {
			$checkpoints = $ban->isWarning ? [] : $this->checkpoints->filterKnown((array) $fields['checkpoints']);
			$data['checkpoints'] = implode(',', $checkpoints);
		}

		// A warning has no expiry to move, so an edit cannot give it one.
		if (array_key_exists('expiresAt', $fields) && !$ban->isWarning) {
			$expiresAt = $fields['expiresAt'];
			$data['expires_at'] = $expiresAt === null ? null : date('Y-m-d H:i:s', (int) $expiresAt);

			// An expiry that has not passed yet puts the ban back in force, so the notice it owes
			// when it lapses again has not been given.
			if ($expiresAt === null || (int) $expiresAt > $this->request->getRequestTime()) {
				$data['expiry_seen_at'] = null;
			}
		}

		foreach (['reason' => 'reason', 'publicReason' => 'public_reason', 'privateReason' => 'private_reason'] as $key => $column) {
			if (array_key_exists($key, $fields)) {
				$data[$column] = (string) $fields[$key];
			}
		}

		if (array_key_exists('rejectsAppeals', $fields)) {
			$data['rejects_appeals'] = $fields['rejectsAppeals'] ? 1 : 0;
		}

		if (array_key_exists('tieVisitorToken', $fields)) {
			// Read off the post the ban was filed on, so retying one says the same thing it said
			// when filed. A ban with no post has no browser to tie to.
			$data['visitor_token_hash'] = $fields['tieVisitorToken']
				? $this->resolveTokenHashForPost($ban->postUid)
				: null;
		}

		$this->banRepository->updateBan($ban->id, $data);
	}

	/**
	 * @param list<int> $banIds
	 * @return list<banEntry> The bans actually lifted.
	 */
	public function revokeBans(array $banIds, ?int $accountId): array {
		return $this->banRepository->revokeBans(array_map('intval', $banIds), $accountId);
	}

	public function getBan(int $banId): ?banEntry {
		return $this->banRepository->findById($banId);
	}

	/**
	 * @param array $filters The ban table's filter set; banRepository::buildFilters() lists them.
	 * @return list<banEntry>
	 */
	public function listBans(array $filters, int $limit, int $offset): array {
		return $this->banRepository->listBans($filters, $limit, $offset);
	}

	/** @param array $filters As listBans() takes them. */
	public function countBans(array $filters): int {
		return $this->banRepository->countBans($filters);
	}

	/**
	 * Whether one of the bans held against this visitor was filed on the given post.
	 *
	 * The ban page shows people the post they were banned for, so the file server has to let
	 * them at its attachments even after the post has been deleted and its files hidden.
	 */
	public function viewerWasBannedForPost(int $postUid, ?string $ip = null): bool {
		if ($postUid <= 0) {
			return false;
		}

		foreach ($this->findVisible($ip ?? (string) $this->request->userIp()) as $ban) {
			if ($ban->postUid === $postUid) {
				return true;
			}
		}

		return false;
	}

	/** Default checkpoint keys for a fresh ban form. */
	public function getDefaultCheckpoints(): array {
		return $this->checkpoints->defaultKeys();
	}

	/**
	 * Public ban notices for a batch of posts, keyed by post UID.
	 *
	 * @param list<int> $postUids
	 * @return array<int, string>
	 */
	public function getPublicReasonsForPosts(array $postUids): array {
		return $this->banRepository->findPublicReasonsForPosts($postUids);
	}

	// ─── Appeals ──────────────────────────────────────────────────

	/**
	 * Why this ban cannot be appealed right now, or null when it can.
	 *
	 * One appeal may be open at a time; a denied one can be tried again once the cooldown has
	 * passed. Warnings, expired bans and lifted bans have nothing left to argue with.
	 */
	public function getAppealBlocker(banEntry $ban, int $cooldownHours): ?string {
		$now = $this->request->getRequestTime();

		if ($ban->rejectsAppeals) {
			return _T('ban_appeal_error_refused');
		}

		if (!$ban->isAppealable($now)) {
			return _T('ban_appeal_error_not_appealable');
		}

		if ($this->banAppealRepository->findPendingForBan($ban->id) !== null) {
			return _T('ban_appeal_error_pending');
		}

		$lastDenied = $this->banAppealRepository->getLastDeniedAt($ban->id);

		if ($lastDenied !== null && $cooldownHours > 0) {
			$readyAt = $lastDenied + ($cooldownHours * 3600);

			if ($readyAt > $now) {
				return _T('ban_appeal_error_cooldown', banDuration::humanize($readyAt - $now));
			}
		}

		return null;
	}

	public function fileAppeal(banEntry $ban, string $reason, int $cooldownHours): int {
		$blocker = $this->getAppealBlocker($ban, $cooldownHours);

		if ($blocker !== null) {
			throw new BoardException($blocker);
		}

		$reason = trim($reason);

		if ($reason === '') {
			throw new BoardException(_T('ban_appeal_error_empty'));
		}

		return $this->banAppealRepository->insertAppeal($ban->id, (string) $this->request->userIp(), $reason);
	}

	/**
	 * Approve appeals: the bans they argue with are lifted, or given a shorter sentence when the
	 * moderator supplied one.
	 *
	 * @param list<int> $appealIds
	 * @param int|null  $newExpiresAt Unix expiry to reduce the bans to; null lifts them outright.
	 * @return int Appeals closed.
	 */
	public function approveAppeals(array $appealIds, ?int $accountId, string $staffNote, ?int $newExpiresAt = null): int {
		$appealIds = array_map('intval', $appealIds);
		$banIds = [];

		foreach ($appealIds as $appealId) {
			$appeal = $this->banAppealRepository->findById($appealId);

			if ($appeal !== null && $appeal->status->isPending()) {
				$banIds[] = $appeal->banId;
			}
		}

		if ($newExpiresAt === null) {
			$this->revokeBans($banIds, $accountId);
		} else {
			foreach ($banIds as $banId) {
				$this->banRepository->setExpiry($banId, $newExpiresAt);
			}
		}

		return $this->banAppealRepository->decideAppeals($appealIds, banAppealStatus::APPROVED, $accountId, $staffNote);
	}

	/** @param list<int> $appealIds */
	public function denyAppeals(array $appealIds, ?int $accountId, string $staffNote): int {
		return $this->banAppealRepository->decideAppeals(
			array_map('intval', $appealIds),
			banAppealStatus::DENIED,
			$accountId,
			$staffNote
		);
	}

	/** @return list<banAppeal> */
	public function listAppeals(string $status, int $limit, int $offset): array {
		return $this->banAppealRepository->listAppeals($status, $limit, $offset);
	}

	public function countAppeals(string $status): int {
		return $this->banAppealRepository->countAppeals($status);
	}

	public function countPendingAppeals(): int {
		return $this->banAppealRepository->countPending();
	}

	/**
	 * Remove the pending appeals of bans that have expired. Like mute pruning this runs from the
	 * staff pages that show appeals, since there is no cron.
	 *
	 * @return int Appeals removed.
	 */
	public function pruneExpiredAppeals(): int {
		return $this->banAppealRepository->deletePendingForLapsedBans();
	}

	/** @return list<banAppeal> */
	public function getAppealsForBan(int $banId): array {
		return $this->banAppealRepository->findForBan($banId);
	}

	public function getAppeal(int $appealId): ?banAppeal {
		return $this->banAppealRepository->findById($appealId);
	}
}
