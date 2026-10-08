<?php

namespace Kokonotsuba\Modules\threadCache;

use Kokonotsuba\background\backgroundBoardContext;
use Kokonotsuba\board\board;
use Kokonotsuba\cache\thread_fragment\threadFragmentCache;
use Kokonotsuba\containers\appContainer;
use Kokonotsuba\overboard;
use Kokonotsuba\renderers\boardRendererFactory;
use Kokonotsuba\renderers\threadPageRenderer;
use Kokonotsuba\thread\Thread;
use Puchiko\background\BackgroundTaskDispatcher;
use Puchiko\background\BackgroundTaskInterface;

use function Kokonotsuba\libraries\html\buildThreadAreaTemplateValues;

/**
 * Clears or redraws the thread fragment caches of the given boards, outside the request.
 *
 * 'clear' drops every fragment. 'current' redraws the fragments on disk when it starts, and only
 * those. 'all' redraws every thread: its index preview, each of its pages, and, on a listed board,
 * its overboard preview for every overboard template and preview count a board is set to.
 *
 * Drawn with no session, so what is stored is what an anonymous reader gets. Each board's
 * template engine is shared with its modules, so it is switched to the template of the view
 * being drawn, the way a request for that view would have built it.
 */
class threadCacheRegenTask implements BackgroundTaskInterface {
	public const NAME = 'thread_cache_regen';

	public const MODE_CLEAR = 'clear';
	public const MODE_CURRENT = 'current';
	public const MODE_ALL = 'all';

	private const BATCH = 100;

	private appContainer $container;
	private int $done = 0;
	private int $total = 0;
	private float $lastReport = 0.0;
	private ?array $overboardViews = null;
	private string $unit = 'threads';

	public function handle(array $args): void {
		$mode = (string)($args['mode'] ?? '');
		if (!in_array($mode, [self::MODE_CLEAR, self::MODE_CURRENT, self::MODE_ALL], true)) {
			throw new \InvalidArgumentException("Unknown mode '$mode'.");
		}

		$this->container = backgroundBoardContext::boot();
		$boards = $this->container->get('boardService')->getBoardsFromUIDs(array_map('intval', $args['boardUIDs'] ?? [])) ?? [];

		if ($mode === self::MODE_CLEAR) {
			$this->unit = 'boards';
			$this->total = count($boards);
			foreach ($boards as $board) {
				threadFragmentCache::forBoard($board)->clear();
				$this->advance(1, $board, true);
			}
			return;
		}

		// what 'current' redraws is read before anything is cleared
		$wanted = [];
		foreach ($boards as $board) {
			if ($mode === self::MODE_CURRENT) {
				$wanted[$board->getBoardUID()] = $this->wantedVariants($board);
				$this->total += count($wanted[$board->getBoardUID()]);
			} else {
				$this->total += (int)$this->container->get('threadRepository')->threadCountFromBoard($board);
			}
		}

		foreach ($boards as $board) {
			threadFragmentCache::forBoard($board)->clear();
			if (!empty($board->getConfigValue('THREAD_FRAGMENT_CACHE'))) {
				$this->redrawBoard($board, $mode === self::MODE_CURRENT ? $wanted[$board->getBoardUID()] : null);
			}
		}
		$this->report(null, true);
	}

	/**
	 * The variants on disk per thread, those a redraw can reproduce.
	 *
	 * @return array<string, list<array>> Thread uid => parsed variants.
	 */
	private function wantedVariants(board $board): array {
		$wanted = [];
		foreach (threadFragmentCache::forBoard($board)->entries() as $entry) {
			$variant = threadFragmentCache::parseVariant($entry['variant']);
			if ($variant !== null) {
				$wanted[$entry['threadUid']][] = $variant;
			}
		}

		return $wanted;
	}

	/** @param array<string, list<array>>|null $wanted Null for everything. */
	private function redrawBoard(board $board, ?array $wanted): void {
		if ($wanted === []) {
			return;
		}
		$threadService = $this->container->get('threadService');
		$previewCount = (int)$board->getConfigValue('RE_DEF', 5);
		$overboardViews = $wanted === null && $board->getBoardListed() ? $this->overboardViews() : [];

		for ($offset = 0; ; $offset += self::BATCH) {
			$threads = $threadService->getThreadsFromBoard($board, self::BATCH, $offset);
			if ($threads === []) {
				break;
			}

			$index = $pages = $overboards = [];
			foreach ($threads as $thread) {
				$uid = $thread->getUid();
				if ($wanted === null) {
					$index[] = $thread;
					$pages[$uid] = null;
					foreach ($overboardViews as $key => $_) {
						$overboards[$key][] = $thread;
					}
					continue;
				}

				foreach ($wanted[$uid] ?? [] as $variant) {
					if ($variant['kind'] === 'index' && $variant['previewCount'] === $previewCount) {
						$index[$uid] = $thread;
					} elseif ($variant['kind'] === 'thread') {
						$pages[$uid][] = $variant['page'];
					} elseif ($variant['kind'] === 'overboard' && is_dir(getBackendDir() . 'templates/' . $variant['template'])) {
						$overboards[$variant['template'] . ':' . $variant['previewCount']][$uid] = $thread;
					}
				}
			}

			$this->useTemplate($board, 'TEMPLATE_FILE');
			$board->warmIndexFragments(array_values($index));

			$this->useTemplate($board, 'REPLY_TEMPLATE_FILE');
			$byUid = [];
			foreach ($threads as $thread) {
				$byUid[$thread->getUid()] = $thread;
			}
			foreach ($pages as $uid => $onlyPages) {
				$board->warmThreadFragments($byUid[$uid], $onlyPages);
			}

			foreach ($overboards as $key => $overboardThreads) {
				[$template, $count] = explode(':', $key);
				$this->warmOverboard($board, array_values($overboardThreads), $template, (int)$count);
			}

			$this->advance($wanted === null ? count($threads) : count(array_intersect_key($wanted, $byUid)), $board);
		}
	}

	/** Every overboard template and preview count some board draws its overboard with. */
	private function overboardViews(): array {
		if ($this->overboardViews === null) {
			$this->overboardViews = [];
			foreach (GLOBAL_BOARD_ARRAY as $board) {
				$config = $board->loadBoardConfig();
				$this->overboardViews[overboard::templateName($config) . ':' . (int)($config['RE_DEF'] ?? 5)] = true;
			}
		}

		return $this->overboardViews;
	}

	/**
	 * Store overboard previews of a board's threads as the overboard draws them: through the board's
	 * own engine, switched to the overboard template.
	 *
	 * @param Thread[] $threads
	 */
	private function warmOverboard(board $board, array $threads, string $template, int $previewCount): void {
		$templateEngine = $board->getBoardTemplateEngine();
		$templateEngine->setTemplateFile($template);
		$moduleEngine = $board->getModuleEngine();

		$renderer = new threadPageRenderer(
			new boardRendererFactory($templateEngine, $moduleEngine, $this->container->get('request'), $board, $this->container),
			$this->container->get('threadService'),
			$this->container->get('quoteLinkService'),
			$board,
			false,
			false,
			true,
			true
		);

		$renderer->renderThreads(
			$threads,
			[$board->getBoardUID() => $board],
			$previewCount,
			threadFragmentCache::overboardVariant($template, $previewCount),
			buildThreadAreaTemplateValues($moduleEngine, false, false, ['returnAfterDelete' => true])
		);
	}

	/** Point the board's shared engine at the template its config names for a view. */
	private function useTemplate(board $board, string $configKey): void {
		$template = str_replace('.tpl', '', (string)$board->getConfigValue($configKey, $board->getConfigValue('TEMPLATE_FILE')));
		$board->getBoardTemplateEngine()->setTemplateFile($template);
	}

	private function advance(int $count, board $board, bool $force = false): void {
		$this->done += $count;
		$this->report($board, $force);
	}

	/** Progress for the page polling the job, at most once a second. */
	private function report(?board $board, bool $force = false): void {
		$now = microtime(true);
		if (!$force && $now - $this->lastReport < 1.0) {
			return;
		}
		$this->lastReport = $now;

		BackgroundTaskDispatcher::reportProgress([
			'done' => min($this->done, $this->total),
			'total' => $this->total,
			'unit' => $this->unit,
			// plain text: the page shows it as text, and a title is stored as markup
			'board' => html_entity_decode(strip_tags($board?->getBoardTitle() ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
		]);
	}
}
