<?php

namespace Kokonotsuba\renderers;

use Kokonotsuba\board\board;
use Kokonotsuba\containers\appContainer;
use Kokonotsuba\containers\moduleEngineContext;
use Kokonotsuba\interfaces\IBoard;
use Kokonotsuba\module_classes\moduleEngine;
use Kokonotsuba\post\helper\postDateFormatter;
use Kokonotsuba\post\Post;
use Kokonotsuba\request\request;
use Kokonotsuba\template\templateEngine;

use function Kokonotsuba\libraries\searchBoardArrayForBoard;

/**
 * Renderers for a list of posts that comes from more than one board.
 *
 * A postRenderer takes its post links, element ids, quote links and attachment URLs from the board
 * it was built against, so a page mixing boards - search results, the deleted queue, recent posts,
 * the overboard - needs one renderer per board rather than one for the page. Everything is cached
 * by board UID, so a page of results builds each board's renderer once however many posts it draws.
 */
final class boardRendererFactory {
	/** @var array<int, moduleEngine> */
	private array $moduleEngines = [];

	/** @var array<int, postRenderer> */
	private array $postRenderers = [];

	/** @var array<int, threadRenderer> */
	private array $threadRenderers = [];

	/** @var array<int, templateEngine> */
	private array $templateEngines = [];

	/** Quote links for the posts currently being drawn, keyed by post uid. */
	private array $quoteLinks = [];

	/**
	 * @param templateEngine    $templateEngine        Engine the posts are rendered with.
	 * @param moduleEngine      $pageModuleEngine      The page's own engine, used when $container is null.
	 * @param request           $request               The current request.
	 * @param IBoard            $fallbackBoard         Rendered against when a post's own board is gone.
	 * @param appContainer|null $container             Pass it to build each board its own module
	 *                                                 engine from its own ModuleList, the way that
	 *                                                 board's pages get one; null shares the page's.
	 * @param string $templateFileConfigKey            Config key naming a per-board template file
	 *                                                 (e.g. 'REPLY_TEMPLATE_FILE'); '' renders every
	 *                                                 board with the one engine passed in.
	 */
	public function __construct(
		private readonly templateEngine $templateEngine,
		private readonly moduleEngine $pageModuleEngine,
		private readonly request $request,
		private readonly IBoard $fallbackBoard,
		private readonly ?appContainer $container = null,
		private readonly string $templateFileConfigKey = '',
	) {}

	/**
	 * Hand the renderers the quote links for the posts about to be drawn.
	 *
	 * Fetched once per page, so renderers built earlier are updated too.
	 */
	public function setQuoteLinks(array $quoteLinks): void {
		$this->quoteLinks = $quoteLinks;

		foreach ($this->postRenderers as $postRenderer) {
			$postRenderer->setQuoteLinks($quoteLinks);
		}
	}

	/** The board a post was made to, falling back when it is no longer in the board array. */
	public function boardForPost(Post $post): IBoard {
		return $this->boardForUid($post->getBoardUID());
	}

	public function boardForUid(?int $boardUid): IBoard {
		return ($boardUid ? searchBoardArrayForBoard($boardUid) : null) ?? $this->fallbackBoard;
	}

	public function postRendererFor(IBoard $board): postRenderer {
		$boardUid = $board->getBoardUID();

		if (!isset($this->postRenderers[$boardUid])) {
			$this->postRenderers[$boardUid] = new postRenderer(
				$board,
				$board->loadBoardConfig(),
				$this->moduleEngineFor($board),
				$this->templateEngineFor($board),
				$this->quoteLinks,
				$this->request
			);
		}

		return $this->postRenderers[$boardUid];
	}

	public function threadRendererFor(IBoard $board): threadRenderer {
		$boardUid = $board->getBoardUID();

		if (!isset($this->threadRenderers[$boardUid])) {
			$this->threadRenderers[$boardUid] = new threadRenderer(
				$board->loadBoardConfig(),
				$this->templateEngineFor($board),
				$this->postRendererFor($board),
				$this->moduleEngineFor($board)
			);
		}

		return $this->threadRenderers[$boardUid];
	}

	/**
	 * The module engine the board's own posts are rendered through.
	 *
	 * Without a container this is the page's engine; with one, each board gets an engine holding
	 * the modules that board enables, since two boards rarely run the same ModuleList. The page's
	 * own board keeps the page's engine either way - rebuilding it would instantiate every module
	 * the request has already loaded a second time.
	 */
	public function moduleEngineFor(IBoard $board): moduleEngine {
		if ($this->container === null
			|| !$board instanceof board
			|| $board->getBoardUID() === $this->fallbackBoard->getBoardUID()) {
			return $this->pageModuleEngine;
		}

		$boardUid = $board->getBoardUID();

		if (!isset($this->moduleEngines[$boardUid])) {
			$boardConfig = $board->loadBoardConfig();

			$this->moduleEngines[$boardUid] = new moduleEngine(new moduleEngineContext(
				$boardConfig,
				$board->getConfigValue('LIVE_INDEX_FILE'),
				$board->getConfigValue('ModuleList'),
				$this->templateEngineFor($board),
				$board,
				new postDateFormatter($boardConfig['TIME_ZONE']),
				$this->container
			));
		}

		return $this->moduleEngines[$boardUid];
	}

	/**
	 * Render one post as a standalone block on its own board's terms.
	 *
	 * @param bool     $adminMode    Attach the staff-only controls and the deletion checkbox.
	 * @param bool     $renderAsOp   Render a reply through the OP block so it stands on its own.
	 * @param bool     $threadMode   As the board's index renders it (truncated), rather than in full.
	 * @param int|null $threadResno  Thread the post is linked back to; defaults to its own.
	 */
	public function renderPost(
		Post $post,
		bool $adminMode = false,
		bool $renderAsOp = true,
		bool $threadMode = false,
		array $templateValues = [],
		?int $threadResno = null
	): string {
		$board = $this->boardForPost($post);

		return $this->postRendererFor($board)->render(
			$post,
			$templateValues,
			$threadResno ?? $post->getOpNumber(),
			false,
			[$post],
			$adminMode,
			'',
			'',
			0,
			$threadMode,
			$board->getBoardURL(),
			$renderAsOp
		);
	}

	/**
	 * The engine a board's posts are rendered with: the one passed in, or a clone set to the
	 * template file that board names, when the caller asked for a per-board template.
	 */
	private function templateEngineFor(IBoard $board): templateEngine {
		if ($this->templateFileConfigKey === '') {
			return $this->templateEngine;
		}

		$boardUid = $board->getBoardUID();

		if (!isset($this->templateEngines[$boardUid])) {
			$templateEngine = clone $this->templateEngine;
			$templateFile = $board->getConfigValue($this->templateFileConfigKey);

			if ($templateFile) {
				$templateEngine->setTemplateFile($templateFile);
			}

			$this->templateEngines[$boardUid] = $templateEngine;
		}

		return $this->templateEngines[$boardUid];
	}
}
