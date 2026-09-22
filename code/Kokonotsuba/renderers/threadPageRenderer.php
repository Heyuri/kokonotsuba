<?php

namespace Kokonotsuba\renderers;

use Kokonotsuba\cache\thread_fragment\threadFragmentCache;
use Kokonotsuba\interfaces\IBoard;
use Kokonotsuba\quote_link\quoteLinkService;
use Kokonotsuba\thread\Thread;
use Kokonotsuba\thread\ThreadData;
use Kokonotsuba\thread\threadService;

use function Kokonotsuba\libraries\getPostUidsFromThreadArrays;
use function Kokonotsuba\libraries\html\buildThreadNavButtons;
use function Kokonotsuba\libraries\html\getThreadTitle;
use function Kokonotsuba\libraries\searchBoardArrayForBoard;

/**
 * A page of thread previews: the board index, the static rebuild and the overboard.
 *
 * Cached fragments are looked up before the posts are fetched, so only the threads that have to be
 * drawn load theirs, and every thread drawn is stored. The threads may come from more than one
 * board, so each is drawn through its own board's renderer, while page-level work - the separators
 * and the thread hook for cached threads - goes through the viewing board's, which the request has
 * already built.
 */
final class threadPageRenderer {
	/** @var array<int, ?threadFragmentCache> */
	private array $fragmentCaches = [];

	/**
	 * @param IBoard $viewingBoard   The board whose page this is; the overboard's viewing board.
	 * @param bool   $cacheFragments Whether this view is the one every anonymous reader gets.
	 * @param bool   $crossBoard     Label each thread with the board it came from, as the overboard does.
	 */
	public function __construct(
		private readonly boardRendererFactory $rendererFactory,
		private readonly threadService $threadService,
		private readonly quoteLinkService $quoteLinkService,
		private readonly IBoard $viewingBoard,
		private readonly bool $adminMode,
		private readonly bool $includeDeleted,
		private readonly bool $cacheFragments,
		private readonly bool $crossBoard = false,
	) {}

	/**
	 * The boards a page's threads belong to, keyed by UID, from the board list loaded at bootstrap.
	 *
	 * @param Thread[] $threads
	 * @return array<int, IBoard>
	 */
	public static function boardsForThreads(array $threads): array {
		$boards = [];
		foreach ($threads as $thread) {
			$boardUid = (int)$thread->getBoardUID();
			if ($boardUid === 0 || isset($boards[$boardUid])) {
				continue;
			}
			$board = searchBoardArrayForBoard($boardUid);
			if ($board !== null) {
				$boards[$boardUid] = $board;
			}
		}

		return $boards;
	}

	/**
	 * Draw the threads in page order, reusing cached blocks and storing the ones drawn.
	 *
	 * @param Thread[]            $threads   In the order they appear on the page.
	 * @param array<int, IBoard>  $boards    The threads' boards by UID; a thread whose board is
	 *                                       missing is dropped rather than drawn against another's.
	 * @param string              $variant   The fragment variant this page stores under.
	 */
	public function renderThreads(array $threads, array $boards, int $previewCount, string $variant, array $templateValues): string {
		$threads = array_values($threads);

		// cached blocks are looked up first, so only the threads that have to be drawn load their posts
		$cachedBlocks = [];
		$threadsToDraw = [];
		foreach ($threads as $i => $thread) {
			$board = $boards[(int)$thread->getBoardUID()] ?? null;
			$block = $board === null
				? null
				: $this->fragmentCacheFor($board)?->get($thread->getUid(), $variant, threadFragmentCache::stampFor($thread));

			if ($block === null) {
				$threadsToDraw[$i] = $thread;
			} else {
				$cachedBlocks[$i] = $block;
			}
		}

		$previews = $this->threadService->buildThreadPreviews(array_values($threadsToDraw), $previewCount, $this->includeDeleted);
		$previewByIndex = array_combine(array_keys($threadsToDraw), $previews);

		// every thread in page order, for the navigation arrows; cached ones carry no posts
		$threadsInPage = [];
		foreach ($threads as $i => $thread) {
			$threadsInPage[$i] = $previewByIndex[$i] ?? new ThreadData($thread, [], null, $thread->getPostCount());
		}

		// one set of quote links for the threads being drawn, shared by every board's renderer
		$postUidsToDraw = getPostUidsFromThreadArrays($previews);
		$this->rendererFactory->setQuoteLinks(
			$postUidsToDraw === [] ? [] : $this->quoteLinkService->getQuoteLinksByPostUids($postUidsToDraw, $this->includeDeleted)
		);

		$this->prefetchPosts($previews, $boards);

		$pageRenderer = $this->rendererFactory->threadRendererFor($this->viewingBoard);

		$html = '';
		foreach ($threadsInPage as $i => $threadData) {
			$thread = $threadData->getThread();
			$board = $boards[(int)$thread->getBoardUID()] ?? null;
			if ($board === null) {
				continue;
			}

			if (isset($cachedBlocks[$i])) {
				$block = $cachedBlocks[$i];
				$pageRenderer->notifyCachedThread($thread);
			} else {
				if ($threadData->getPosts() === []) {
					continue;
				}
				$block = $this->drawThreadBlock($threadData, $board, $templateValues);
				$this->fragmentCacheFor($board)?->put($thread->getUid(), $variant, threadFragmentCache::stampFor($thread), $block);
			}

			// what depends on the thread's place on this page is added around the block
			$html .= str_replace(threadFragmentCache::NAV_SENTINEL, buildThreadNavButtons($threadsInPage, $i), $block)
				. $pageRenderer->renderThreadSeparator($i);
		}

		return $html;
	}

	/**
	 * Let each board's listeners see the posts of its own threads before they are drawn.
	 *
	 * @param ThreadData[]       $previews
	 * @param array<int, IBoard> $boards
	 */
	private function prefetchPosts(array $previews, array $boards): void {
		$postsByBoard = [];
		foreach ($previews as $preview) {
			$postsByBoard[(int)$preview->getThread()->getBoardUID()][] = $preview->getPosts();
		}

		foreach ($postsByBoard as $boardUid => $postLists) {
			$board = $boards[$boardUid] ?? null;
			if ($board === null) {
				continue;
			}

			$boardPosts = array_merge(...$postLists);
			// the renderer factory's engine for the board is the one whose listeners draw these
			// posts, so it is the one whose listeners must see them coming
			$this->rendererFactory->moduleEngineFor($board)->dispatch('PostsPrefetch', [&$boardPosts]);
		}
	}

	/** The thread's own markup, with a sentinel where the page's navigation arrows go. */
	private function drawThreadBlock(ThreadData $threadData, IBoard $board, array $templateValues): string {
		$boardTitleHtml = $crossLink = '';

		// a thread drawn away from its own board carries the board it came from, and its links
		// point back there
		if ($this->crossBoard) {
			$crossLink = $board->getBoardURL();
			$boardTitleHtml = getThreadTitle($crossLink, $board->getBoardTitle());
		}

		return $this->rendererFactory->threadRendererFor($board)->renderThreadBlock(
			false,
			$threadData->getThread(),
			$threadData->getPosts(),
			(int)$threadData->getHiddenReplyCount(),
			false,
			$this->adminMode,
			$boardTitleHtml,
			$crossLink,
			$templateValues,
			0,
			1,
			null,
			threadFragmentCache::NAV_SENTINEL
		);
	}

	/**
	 * The store a board's fragments go in; null when this view is not what every reader sees, or
	 * the board has the cache switched off. One instance per board, since a miss noted by get()
	 * is what put() checks against.
	 */
	private function fragmentCacheFor(IBoard $board): ?threadFragmentCache {
		if (!$this->cacheFragments) {
			return null;
		}

		$boardUid = (int)$board->getBoardUID();

		if (!array_key_exists($boardUid, $this->fragmentCaches)) {
			$this->fragmentCaches[$boardUid] = empty($board->getConfigValue('THREAD_FRAGMENT_CACHE'))
				? null
				: threadFragmentCache::forBoard($board);
		}

		return $this->fragmentCaches[$boardUid];
	}
}
