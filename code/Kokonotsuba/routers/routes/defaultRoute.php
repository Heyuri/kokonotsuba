<?php

// default route - live front end / redirect to static html

namespace Kokonotsuba\routers\routes;

use Kokonotsuba\account\staffAccountFromSession;
use Kokonotsuba\board\board;
use Kokonotsuba\error\BoardException;
use Kokonotsuba\policy\postRenderingPolicy;
use Kokonotsuba\post\deletion\deletedPostsService;
use Kokonotsuba\request\request;
use Kokonotsuba\thread\postRedirectService;
use Kokonotsuba\thread\Thread;
use Kokonotsuba\post\postRepository;
use Kokonotsuba\thread\threadRepository;

use function Kokonotsuba\libraries\_T;
use function Kokonotsuba\libraries\html\getPageForPostPosition;
use function Kokonotsuba\libraries\isActiveStaffSession;
use function Puchiko\request\redirect;

class defaultRoute {
	/**
	 * Constructor to initialize required dependencies.
	 */
	public function __construct(
		private readonly array $config,
		private board $board,
		private readonly threadRepository $threadRepository,
		private readonly postRepository $postRepository,
		private readonly postRedirectService $postRedirectService,
		private readonly postRenderingPolicy $postRenderingPolicy,
		private readonly deletedPostsService $deletedPostsService,
		private readonly staffAccountFromSession $staffAccountFromSession,
		private readonly request $request
	) {}

	/**
	 * Main entry point to handle default board access.
	 * Decides whether to show a thread, a specific page, or redirect to a cached board index.
	 */
	public function handleDefault(): void {
		header('Content-Type: text/html; charset=utf-8');

		// Check for ?res= (thread view)
		$res = intval($this->request->getParameter('res', 'GET', 0));

		// Check for ?page= (specific page number)
		$pageParam = $this->request->getParameter('page', 'GET');

		if ($res > 0) {
			// Handle thread view (with potential redirection)
			$this->handleThreadRedirect($res);

			// get recent replies mode from GET request.
			// thread mode is typically blank just for regular thread rendering
			$recentReplies = $this->request->getParameter('recentReplies', 'GET');

			// Render the last X amount of replies
			if($recentReplies) {
				// fetch the amount of replies to render
				$amountOfRepliesToRender = $this->board->getConfigValue('LAST_AMOUNT_OF_REPLIES', 50);

				// prevent values going higher than the config value
				$recentReplies = min($recentReplies, $amountOfRepliesToRender);

				// also prevent it from being negative
				$recentReplies = max($recentReplies, 1);

				// then draw the last X replies page
				$this->board->drawRecentReplies($res, $recentReplies);
			}
			else {
				// draw the regular thread page	
				$this->board->drawThread($res, $pageParam !== null ? max(1, intval($pageParam)) : 1);
			}
		} elseif ($pageParam !== null && intval($pageParam) >= 1) {
			// Handle specific board page
			$this->board->drawPage(intval($pageParam));
		} elseif (isActiveStaffSession()) {
			// Staff get the index rendered here rather than being sent to the html index
			$this->board->drawPage(1);
		} else {
			// If the static index page is missing, regenerate it
			if (!is_file($this->config['STATIC_INDEX_FILE'])) {
				$this->board->updateBoardPathCache();
				$this->board->rebuildBoard(true);
			}

			// Redirect to static index page with cache-busting timestamp
			header('HTTP/1.1 302 Moved Temporarily');
			header('Location: ' . $this->board->getBoardURL(false, true) . '?' . $this->request->getRequestTime());
		}
	}

	/**
	 * Handle redirect logic when accessing a thread by post number.
	 * This includes resolving moved threads or trying to find threads by child posts.
	 */
	private function handleThreadRedirect(int $resno) {
		// Check if the thread has been moved (redirect registered)
		$movedThreadRedirect = $this->postRedirectService->resolveRedirectUrlByPostNumber($this->board, $resno);
		if ($movedThreadRedirect) {
			redirect($movedThreadRedirect);
		}

		// Staff who can't see deleted posts still get to see their own deletions, so send them to
		// the entry for anything they took down rather than letting the render 404 on them
		$ownDeletionEntry = $this->resolveOwnDeletionEntryUrl($resno);
		if ($ownDeletionEntry) {
			redirect($ownDeletionEntry);
		}

		// Try to resolve the thread UID directly from the post number
		$thread_uid = $this->threadRepository->resolveThreadUidFromResno($this->board, $resno);

		// If the thread UID is not valid, try to resolve from a child post
		if (!$this->threadRepository->isThread($thread_uid)) {
			$post_uid = $this->postRepository->resolvePostUidFromPostNumber($this->board, $resno);

			// get the post
			$post = $this->postRepository->getPostByUid($post_uid, $this->postRenderingPolicy->viewDeleted());

			// throw error if the post still isn't found
			if (!$post) {
				throw new BoardException(_T('thread_not_found'));
			}

			// Fetch the thread UID from the post's data
			$newThread = $this->threadRepository->getThreadByUid($post->getThreadUid(), $this->postRenderingPolicy->viewDeleted()) ?? false;

			// new thread uid
			$newThreadUid = $newThread->getUid();

			// If still not valid, show error
			if (!$this->threadRepository->isThread($newThreadUid)) {
				throw new BoardException(_T('thread_not_found'));
			}

			// then get replies per page config value
			$repliesPerPage = $this->board->getConfigValue('REPLIES_PER_PAGE', 200);

			// get the page of the post based on its true position within the thread
			// (objective position among visible replies, not the drift-prone stored
			// post_position column which is inaccurate after deletions)
			$viewDeleted = $this->postRenderingPolicy->viewDeleted();
			$positions = $this->threadRepository->getObjectivePositions([$post->getThreadUid()], $viewDeleted);
			$postPosition = $positions[$post->getUid()] ?? 0;
			$page = getPageForPostPosition($postPosition, $repliesPerPage);

			// Otherwise, redirect to the correct thread page and scroll to post
			$resnoNew = $this->threadRepository->resolveThreadNumberFromUID($newThreadUid); 
			$redirectString = $this->board->getBoardThreadURL($resnoNew, $resno, false, $page);
			redirect($redirectString);
		}
	}

	/**
	 * Resolve the deleted post entry a restricted staff member should be sent to for this post
	 * number, or null when the normal render should go ahead.
	 *
	 * Staff whose role is below the one needed to view deleted posts see nothing of a thread they
	 * deleted, which leaves them staring at a 404 for their own action. They are still entitled to
	 * their own deletion records, so point them at the entry instead.
	 */
	private function resolveOwnDeletionEntryUrl(int $resno): ?string {
		// staff who may view deleted posts outright get the thread rendered as usual
		if ($this->postRenderingPolicy->canViewDeletedPosts()) {
			return null;
		}

		// no session, no deletions to own
		$accountId = $this->staffAccountFromSession->getUID();
		if ($accountId === null) {
			return null;
		}

		// nowhere to send them if the board doesn't run the deleted posts viewer
		if (!$this->board->getConfigValue('ModuleList.deletedPosts', false)) {
			return null;
		}

		// look for an open deletion record of theirs covering this post
		$deletedPostId = $this->deletedPostsService->findOwnOpenDeletionIdForPostNumber(
			$resno,
			(int) $this->board->getBoardUID(),
			(int) $accountId
		);

		if ($deletedPostId === null) {
			return null;
		}

		return $this->buildDeletedPostEntryUrl($deletedPostId);
	}

	/**
	 * Build the URL of a single entry on the deleted posts module page.
	 */
	private function buildDeletedPostEntryUrl(int $deletedPostId): string {
		$urlParameters = [
			'pageName' => 'viewMore',
			'deletedPostId' => $deletedPostId,
			'moduleMode' => 'admin',
			'mode' => 'module',
			'load' => 'deletedPosts',
		];

		return $this->request->getCurrentUrlNoQuery() . '?' . http_build_query($urlParameters);
	}
}
