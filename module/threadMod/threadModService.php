<?php

namespace Kokonotsuba\Modules\threadMod;

use Kokonotsuba\cookie\cookieService;
use Kokonotsuba\module_classes\moduleContext;
use Kokonotsuba\post\Post;
use Kokonotsuba\renderers\boardRendererFactory;
use Kokonotsuba\post\postRepository;
use Kokonotsuba\request\request;
use Kokonotsuba\thread\threadRepository;

/**
 * Who a visitor is to a thread, and the flags OP moderation leaves on posts.
 *
 * Ownership is live: the visitor's browser token matches the opening post's, or their post
 * password (the pwdc cookie, or one typed into a form) verifies against it. Once ownership has
 * been transferred the owner row in thread_mods replaces that, and the opening post's token and
 * password no longer count. Mods are always rows.
 *
 * An OP deletion is `_opdel:{role}:{hash}_` on the hidden post's status; a thread whose OP tools
 * staff switched off carries `_opmodoff_` on its opening post.
 */
final class threadModService {
	public const DELETED_FLAG = 'opdel';
	public const STRIP_FLAG = 'opmodoff';

	/** The UI hint cookie: thread uids this browser moderates. Never trusted server-side. */
	public const COOKIE = 'koko_threadmod';
	private const COOKIE_MAX_THREADS = 60;
	private const COOKIE_DAYS = 90;

	/** @var array<string, threadRole> role cache per thread for this request */
	private array $roles = [];

	private ?string $actorHash = null;

	public function __construct(
		private readonly threadModRepository $repository,
		private readonly postRepository $postRepository,
		private readonly threadRepository $threadRepository,
		/** @var \Closure(): string the visitor's token hash as posts record it, '' when none */
		private readonly \Closure $actorHashProvider,
		private readonly cookieService $cookieService,
		private readonly request $request,
		private readonly threadModPolicy $policy,
		private readonly bool $allowPasswordOwnership,
	) {}

	public function policy(): threadModPolicy {
		return $this->policy;
	}

	public function repository(): threadModRepository {
		return $this->repository;
	}

	/** The visitor's token hash as posts record it; '' when the browser keeps no cookie. */
	public function actorHash(): string {
		return $this->actorHash ??= (string)($this->actorHashProvider)();
	}

	public function openingPost(string $threadUid): ?Post {
		$op = $this->postRepository->getOpeningPostFromThread($threadUid);

		return $op ?: null;
	}

	/**
	 * The visitor's role in a thread.
	 *
	 * @param string|null $typedPassword A password submitted with this request, tried after the cookie.
	 */
	public function resolveRole(string $threadUid, ?Post $op = null, ?string $typedPassword = null): threadRole {
		$cacheKey = $threadUid . "\0" . ($typedPassword ?? '');

		if (isset($this->roles[$cacheKey])) {
			return $this->roles[$cacheKey];
		}

		$op ??= $this->openingPost($threadUid);

		if ($op === null) {
			return $this->roles[$cacheKey] = threadRole::NONE;
		}

		$hash = $this->actorHash();
		$ownerRow = $this->repository->findActiveOwner($threadUid);

		if ($ownerRow !== null) {
			if ($hash !== '' && hash_equals((string)$ownerRow['visitor_token_hash'], $hash)) {
				return $this->roles[$cacheKey] = threadRole::OWNER;
			}
		} elseif ($this->isOpeningPoster($op, $hash, $typedPassword)) {
			return $this->roles[$cacheKey] = threadRole::OWNER;
		}

		$row = $this->repository->findActive($threadUid, $hash);
		$role = $row === null ? threadRole::NONE : threadRole::fromStored($row['role']);

		// a mod row for a transferred-away owner is still just a mod row
		if ($role === threadRole::OWNER && $ownerRow === null) {
			$role = threadRole::MOD;
		}

		return $this->roles[$cacheKey] = $role;
	}

	/** Token first, then the post password, as the thread's OP would hold both. */
	private function isOpeningPoster(Post $op, string $hash, ?string $typedPassword): bool {
		$opHash = $op->getVisitorTokenHash();

		if ($hash !== '' && $opHash !== '' && hash_equals($opHash, $hash)) {
			return true;
		}

		if (!$this->allowPasswordOwnership) {
			return false;
		}

		$storedHash = $op->getPassword();

		if ($storedHash === '') {
			return false;
		}

		foreach ([$typedPassword, $this->cookieService->get('pwdc', '')] as $candidate) {
			if (is_string($candidate) && $candidate !== '' && password_verify($candidate, $storedHash)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Roles across many threads at once, for the status poll. Password ownership is left out:
	 * the poll is for threads the browser already posted in, and the cookie is tried per action.
	 *
	 * @param string[] $threadUids
	 * @return array<string, threadRole> only the threads where the visitor holds a role
	 */
	public function rolesForThreads(array $threadUids): array {
		$hash = $this->actorHash();
		$threadUids = array_values(array_unique(array_filter(array_map('strval', $threadUids))));

		if ($hash === '' || !$threadUids) {
			return [];
		}

		$roles = $this->repository->activeRolesForToken($hash, $threadUids);
		$transferred = $this->repository->threadsWithOwnerRow($threadUids);

		foreach ($this->threadRepository->getFirstPostsFromThreads($threadUids) as $threadUid => $op) {
			$threadUid = (string)$threadUid;

			if ($this->isStripped($op)) {
				unset($roles[$threadUid]);
				continue;
			}

			if (isset($transferred[$threadUid])) {
				continue;
			}

			$opHash = $op->getVisitorTokenHash();

			if ($opHash !== '' && hash_equals($opHash, $hash)) {
				$roles[$threadUid] = threadRole::OWNER;
			}
		}

		return $roles;
	}

	// ─── Flags on posts ───────────────────────────────────────────

	public function isStripped(Post $op): bool {
		return (bool)$op->getFlags()->value(self::STRIP_FLAG);
	}

	public function setStripped(Post $op, bool $on): void {
		$flags = $op->getFlags();
		$on ? $flags->add(self::STRIP_FLAG) : $flags->remove(self::STRIP_FLAG);
		$this->postRepository->setPostStatus($op->getUid(), $flags->toString());
	}

	/**
	 * Who hid a post, or null when it is not OP-deleted.
	 *
	 * @return array{role: threadRole, hash: string}|null
	 */
	public static function deletionOf(Post $post): ?array {
		$value = $post->getFlags()->value(self::DELETED_FLAG);

		if ($value === false || $value === true) {
			return null;
		}

		$parts = is_array($value) ? $value : explode(':', (string)$value);

		return [
			'role' => threadRole::fromKey((string)($parts[0] ?? '')),
			'hash' => (string)($parts[1] ?? ''),
		];
	}

	public static function isOpDeleted(Post $post): bool {
		return self::deletionOf($post) !== null;
	}

	public function markDeleted(Post $post, threadRole $by, string $hash): void {
		$flags = $post->getFlags();
		$flags->update(self::DELETED_FLAG, $by->key() . ':' . $hash);
		$this->postRepository->setPostStatus($post->getUid(), $flags->toString());
	}

	public function clearDeleted(Post $post): void {
		$flags = $post->getFlags();
		$flags->remove(self::DELETED_FLAG);
		$this->postRepository->setPostStatus($post->getUid(), $flags->toString());
	}

	/**
	 * Clear every OP deletion in a thread.
	 *
	 * @return int[] The post uids that were cleared.
	 */
	public function clearAllDeleted(string $threadUid): array {
		$uids = $this->repository->opDeletedPostUids($threadUid);

		if (!$uids) {
			return [];
		}

		$statuses = [];
		foreach ($this->postRepository->getPostsByUids($uids, true) ?: [] as $post) {
			$flags = $post->getFlags();
			$flags->remove(self::DELETED_FLAG);
			$statuses[$post->getUid()] = $flags->toString();
		}

		$this->postRepository->setPostStatuses($statuses);

		return array_keys($statuses);
	}

	public function deletionCount(string $threadUid): int {
		return $this->repository->countOpDeletions($threadUid);
	}

	/**
	 * The role a post's author holds in its thread, as far as the post itself tells: a thread
	 * capcode it was signed with, or a browser token matching the current owner.
	 */
	public function authorRole(Post $post, string $ownerHash): threadRole {
		$fromCapcode = threadModPolicy::roleOfCapcode($post->getCapcode());

		if ($fromCapcode->isOwner()) {
			return $fromCapcode;
		}

		$hash = $post->getVisitorTokenHash();

		if ($ownerHash !== '' && $hash !== '' && hash_equals($ownerHash, $hash)) {
			return threadRole::OWNER;
		}

		return $fromCapcode;
	}

	/** The token hash ownership currently rests on: the transfer row's, else the OP post's. */
	public function ownerHash(string $threadUid, Post $op): string {
		$ownerRow = $this->repository->findActiveOwner($threadUid);

		return $ownerRow !== null ? (string)$ownerRow['visitor_token_hash'] : $op->getVisitorTokenHash();
	}

	// ─── Grants ───────────────────────────────────────────────────

	public function grantMod(string $threadUid, Post $fromPost, string $byHash): void {
		$this->repository->grant($threadUid, $fromPost->getVisitorTokenHash(), threadRole::MOD, $fromPost->getUid(), $byHash);
	}

	public function revokeMod(string $threadUid, string $targetHash, ?string $byHash, ?int $byAccount): bool {
		return $this->repository->revoke($threadUid, $targetHash, $byHash, $byAccount) > 0;
	}

	/** Hand the thread over. The previous owner keeps a seat as a mod. */
	public function transferOwnership(string $threadUid, Post $toPost, string $fromHash): void {
		$this->repository->demoteOwner($threadUid);

		if ($fromHash !== '') {
			$this->repository->grant($threadUid, $fromHash, threadRole::MOD, null, $fromHash);
		}

		$this->repository->grant($threadUid, $toPost->getVisitorTokenHash(), threadRole::OWNER, $toPost->getUid(), $fromHash);
	}

	// ─── UI hint cookie ───────────────────────────────────────────

	/**
	 * What the cookie says this browser moderates: thread uid => role. A hint for threadMod.js
	 * only; the server never reads a role out of it.
	 *
	 * @return array<string, threadRole>
	 */
	public function rememberedThreads(): array {
		$raw = $this->cookieService->get(self::COOKIE, '');

		if (!is_string($raw) || $raw === '') {
			return [];
		}

		$roles = [];

		foreach (explode(',', $raw) as $entry) {
			if (preg_match('/^([a-f0-9]{1,64}):([om])$/', $entry, $m) === 1) {
				$roles[$m[1]] = $m[2] === 'o' ? threadRole::OWNER : threadRole::MOD;
			}
		}

		return $roles;
	}

	/**
	 * Note the threads this browser moderates, so threadMod.js shows the tools without asking.
	 * Newest last; the oldest fall off the front.
	 *
	 * @param array<string, threadRole> $roles thread uid => role
	 */
	public function rememberThreads(array $roles): void {
		$list = $this->rememberedThreads();

		foreach ($roles as $threadUid => $role) {
			$threadUid = (string)$threadUid;

			if (preg_match('/^[a-f0-9]{1,64}$/', $threadUid) !== 1 || !$role->isAtLeastMod()) {
				continue;
			}

			unset($list[$threadUid]);
			$list[$threadUid] = $role;
		}

		$this->writeCookie(array_slice($list, -self::COOKIE_MAX_THREADS, null, true));
	}

	public function forgetThread(string $threadUid): void {
		$list = $this->rememberedThreads();
		unset($list[$threadUid]);
		$this->writeCookie($list);
	}

	/** @param array<string, threadRole> $list */
	private function writeCookie(array $list): void {
		if (headers_sent()) {
			return;
		}

		$entries = [];
		foreach ($list as $threadUid => $role) {
			$entries[] = $threadUid . ':' . ($role->isOwner() ? 'o' : 'm');
		}

		$this->cookieService->set(
			self::COOKIE,
			implode(',', $entries),
			$this->request->getRequestTime() + self::COOKIE_DAYS * 86400,
			'/',
			'',
			$this->request->isHttps(),
			false
		);
	}

	/**
	 * A post drawn as the page the window is on would draw it, so the script can swap it in place:
	 * the reply template on a thread page, the index template elsewhere.
	 */
	public static function renderPostForWindow(moduleContext $context, Post $post, bool $viewerIsStaff, bool $threadView): string {
		$factory = new boardRendererFactory(
			$context->templateEngine,
			$context->moduleEngine,
			$context->request,
			$context->board,
			null,
			$threadView ? 'REPLY_TEMPLATE_FILE' : 'TEMPLATE_FILE'
		);
		$factory->setQuoteLinks($context->quoteLinkService->getQuoteLinksByPostUids([$post->getUid()]));

		return $factory->renderPost($post, $viewerIsStaff, false, !$threadView, [], $post->getOpNumber() ?: $post->getNumber());
	}
}
