<?php

namespace Kokonotsuba\Modules\threadCache;

require_once __DIR__ . '/threadCacheLib.php';
require_once __DIR__ . '/threadCacheDiskStats.php';
require_once __DIR__ . '/threadCacheRegenTask.php';

use Kokonotsuba\board\board;
use Kokonotsuba\module_classes\abstractModuleAdmin;
use Kokonotsuba\module_classes\traits\AuditableTrait;
use Kokonotsuba\module_classes\traits\BackgroundTaskTrait;
use Kokonotsuba\module_classes\traits\listeners\IncludeScriptTrait;
use Kokonotsuba\module_classes\traits\listeners\PostControlHooksTrait;
use Kokonotsuba\userRole;
use Puchiko\background\BackgroundTaskRegistry;

use function Kokonotsuba\libraries\_T;
use function Kokonotsuba\libraries\getCsrfHiddenInput;
use function Puchiko\json\sendJsonResponse;
use function Puchiko\request\redirect;
use function Puchiko\strings\formatFileSize;
use function Puchiko\strings\sanitizeStr;

use const Kokonotsuba\GLOBAL_BOARD_UID;

/**
 * Statistics on the thread fragment cache of every board, and background jobs to clear or redraw it.
 */
class moduleAdmin extends abstractModuleAdmin {
	use PostControlHooksTrait;
	use IncludeScriptTrait;
	use BackgroundTaskTrait;
	use AuditableTrait;

	private const ACTION_TYPE = 'tool.thread_cache';

	private const MOST_HIT_LIMIT = 25;

	private readonly string $modulePageUrl;

	public function getRequiredRole(): userRole {
		return $this->getConfig('AuthLevels.CAN_MANAGE_THREAD_CACHE', userRole::LEV_ADMIN);
	}

	public function getName(): string {
		return 'Thread cache';
	}

	public function getVersion(): string {
		return 'Koko 2026';
	}

	public function initialize(): void {
		$this->modulePageUrl = $this->getModulePageURL([], false, true);

		BackgroundTaskRegistry::register(threadCacheRegenTask::NAME, threadCacheRegenTask::class, __DIR__ . '/threadCacheRegenTask.php');
		$this->registerActionType(self::ACTION_TYPE, 'Thread cache');

		$this->registerLinksAboveBarHook(_T('admin_nav_thread_cache_title'), $this->modulePageUrl, _T('admin_nav_thread_cache'), 'rebuilding');
		$this->registerScript('threadCache.js');
	}

	/** CSRF token and POST are enforced by dispatchModuleRequest() before this runs. */
	protected function handleModuleRequest(): void {
		$request = $this->moduleContext->request;
		$mode = (string)$request->getParameter('threadCacheMode', 'POST', '');
		$scope = (string)$request->getParameter('boardUID', 'POST', 'all');

		$boards = $this->boardsInScope($scope);
		$modes = [threadCacheRegenTask::MODE_CLEAR, threadCacheRegenTask::MODE_CURRENT, threadCacheRegenTask::MODE_ALL];

		if (!in_array($mode, $modes, true) || $boards === []) {
			if ($request->isAjax()) {
				sendJsonResponse(['dispatched' => false, 'message' => _T('thread_cache_invalid')], 400);
			}
			redirect($this->modulePageUrl);
			return;
		}

		$boardUIDs = array_map(fn(board $board): int => $board->getBoardUID(), $boards);

		$this->dispatchBackgroundJob(
			threadCacheRegenTask::NAME,
			['mode' => $mode, 'boardUIDs' => $boardUIDs],
			_T('thread_cache_dispatched'),
			_T('thread_cache_dispatch_failed'),
			$this->getModulePageURL(['dispatched' => '1'], false, true),
			$this->modulePageUrl,
			'[threadCache]',
			function () use ($mode, $boardUIDs): void {
				$this->logAction(
					"Queued thread cache job '$mode' for " . count($boardUIDs) . ' board(s) (UIDs: ' . implode(', ', $boardUIDs) . ')',
					GLOBAL_BOARD_UID,
					self::ACTION_TYPE
				);
			}
		);
	}

	/** @return board[] */
	private function boardsInScope(string $scope): array {
		if ($scope === 'all') {
			return GLOBAL_BOARD_ARRAY;
		}

		foreach (GLOBAL_BOARD_ARRAY as $board) {
			if ((string)$board->getBoardUID() === $scope) {
				return [$board];
			}
		}

		return [];
	}

	public function ModulePage(): void {
		// the messages are plain text, shown by threadCache.js as text
		$this->handleBackgroundPoll(fn(string $status, array $data): string => match ($status) {
			'completed' => _T('thread_cache_completed'),
			'failed' => _T('thread_cache_failed'),
			'pending' => _T('thread_cache_pending'),
			'running' => isset($data['progress'])
				? _T(
					($data['progress']['unit'] ?? '') === 'boards' ? 'thread_cache_progress_boards' : 'thread_cache_progress_threads',
					(int)$data['progress']['done'],
					(int)$data['progress']['total'],
					(string)($data['progress']['board'] ?? '')
				)
				: _T('thread_cache_pending'),
			default => '',
		});

		$hitRepository = getThreadCacheHitRepository($this->moduleContext);

		$diskStats = [];
		foreach (GLOBAL_BOARD_ARRAY as $board) {
			$diskStats[$board->getBoardUID()] = threadCacheDiskStats::forBoard($board);
		}

		$hitTotals = [];
		try {
			foreach ($hitRepository->totalsByBoardAndKind() as $row) {
				$hitTotals[(int)$row['board_uid']][] = $row;
			}
			$mostHit = $hitRepository->mostHitThreads(self::MOST_HIT_LIMIT);
		} catch (\PDOException) {
			// the module's migration has not been applied yet
			$mostHit = [];
		}

		$successMessage = $this->moduleContext->request->getParameter('dispatched', 'GET', null) === '1'
			? _T('thread_cache_dispatched')
			: '';

		$templateValues = [
			'{$CSS_URL}'            => sanitizeStr($this->getConfig('STATIC_URL') . 'css/module/threadCache.css'),
			'{$TITLE}'              => sanitizeStr(_T('thread_cache_title')),
			'{$INTRO}'              => sanitizeStr(_T('thread_cache_intro')),
			'{$SUCCESS_MESSAGE}'    => sanitizeStr($successMessage),
			'{$SUMMARY_HEADING}'    => sanitizeStr(_T('thread_cache_summary')),
			'{$BOARD_TABLE}'        => $this->renderBoardTable($diskStats, $hitTotals),
			'{$ACTIONS_HEADING}'    => sanitizeStr(_T('thread_cache_actions')),
			'{$ACTIONS_DESC}'       => sanitizeStr(_T('thread_cache_actions_desc')),
			'{$MODULE_URL}'         => sanitizeStr($this->modulePageUrl),
			'{$CSRF_TOKEN}'         => getCsrfHiddenInput(),
			'{$SCOPE_LABEL}'        => sanitizeStr(_T('thread_cache_scope_label')),
			'{$SCOPE_OPTIONS}'      => $this->renderScopeOptions(),
			'{$BTN_CLEAR}'          => sanitizeStr(_T('thread_cache_btn_clear')),
			'{$BTN_CURRENT}'        => sanitizeStr(_T('thread_cache_btn_current')),
			'{$BTN_ALL}'            => sanitizeStr(_T('thread_cache_btn_all')),
			'{$CONFIRM_CLEAR}'      => sanitizeStr(_T('thread_cache_confirm_clear')),
			'{$MOST_HIT_HEADING}'   => sanitizeStr(_T('thread_cache_most_hit')),
			'{$MOST_HIT_DESC}'      => sanitizeStr(_T('thread_cache_most_hit_desc')),
			'{$MOST_HIT_TABLE}'     => $this->renderMostHitTable($mostHit, $diskStats),
		];

		$pageHtml = $this->moduleContext->adminPageRenderer->ParseBlock('THREAD_CACHE_PAGE', $templateValues);
		echo $this->moduleContext->adminPageRenderer->ParsePage('GLOBAL_ADMIN_PAGE_CONTENT', ['{$PAGE_CONTENT}' => $pageHtml], true);
	}

	private function renderScopeOptions(): string {
		$html = '<option value="all">' . sanitizeStr(_T('thread_cache_scope_all')) . '</option>';
		foreach (GLOBAL_BOARD_ARRAY as $board) {
			$html .= '<option value="' . $board->getBoardUID() . '">' . $this->boardLabel($board) . '</option>';
		}

		return $html;
	}

	/**
	 * One row per board and a total, disk figures beside the counted hits.
	 *
	 * @param array<int, threadCacheDiskStats> $diskStats
	 * @param array<int, list<array>> $hitTotals Rows of totalsByBoardAndKind() per board.
	 */
	private function renderBoardTable(array $diskStats, array $hitTotals): string {
		$columns = ['board', 'files', 'threads', 'size', 'index', 'thread', 'overboard', 'hits', 'misses', 'hit_rate', 'oldest', 'last_hit'];
		$html = '<table class="postlists threadCacheTable"><thead><tr>';
		foreach ($columns as $column) {
			$html .= '<th>' . sanitizeStr(_T('thread_cache_col_' . $column)) . '</th>';
		}
		$html .= '</tr></thead><tbody>';

		$allHits = [];
		foreach (GLOBAL_BOARD_ARRAY as $board) {
			$uid = $board->getBoardUID();
			$rows = $hitTotals[$uid] ?? [];
			$allHits = array_merge($allHits, $rows);

			$label = $this->boardLabel($board);
			if (empty($board->getConfigValue('THREAD_FRAGMENT_CACHE'))) {
				$label .= ' <span class="threadCacheOff">(' . sanitizeStr(_T('thread_cache_off')) . ')</span>';
			}
			$html .= $this->renderBoardRow($label, $diskStats[$uid], $rows);
		}

		$html .= '</tbody><tfoot>'
			. $this->renderBoardRow(sanitizeStr(_T('thread_cache_total')), threadCacheDiskStats::sum($diskStats), $allHits)
			. '</tfoot></table>';

		return $html;
	}

	/** @param list<array> $hitRows Rows of totalsByBoardAndKind(), summed here. */
	private function renderBoardRow(string $labelHtml, threadCacheDiskStats $disk, array $hitRows): string {
		$hits = $misses = 0;
		$lastHit = null;
		$kindHits = array_fill_keys(threadCacheDiskStats::KINDS, [0, 0]);
		foreach ($hitRows as $row) {
			$hits += (int)$row['hits'];
			$misses += (int)$row['misses'];
			$kind = isset($kindHits[$row['kind']]) ? $row['kind'] : 'other';
			$kindHits[$kind][0] += (int)$row['hits'];
			$kindHits[$kind][1] += (int)$row['misses'];
			if ($row['last_hit_at'] !== null && ($lastHit === null || $row['last_hit_at'] > $lastHit)) {
				$lastHit = $row['last_hit_at'];
			}
		}

		$kindCell = fn(string $kind): string => '<td>' . number_format($disk->kinds[$kind])
			. ' <span class="threadCacheRate" title="' . sanitizeStr(_T('thread_cache_col_hit_rate')) . '">'
			. self::hitRate($kindHits[$kind][0], $kindHits[$kind][1]) . '</span></td>';

		return '<tr>'
			. '<td>' . $labelHtml . '</td>'
			. '<td>' . number_format($disk->files) . '</td>'
			. '<td>' . number_format($disk->threadCount()) . '</td>'
			. '<td>' . sanitizeStr(formatFileSize($disk->bytes)) . '</td>'
			. $kindCell('index')
			. $kindCell('thread')
			. $kindCell('overboard')
			. '<td>' . number_format($hits) . '</td>'
			. '<td>' . number_format($misses) . '</td>'
			. '<td>' . self::hitRate($hits, $misses) . '</td>'
			. '<td>' . ($disk->oldest === null ? '-' : $this->moduleContext->postDateFormatter->formatFromTimestamp($disk->oldest)) . '</td>'
			. '<td>' . ($lastHit === null ? '-' : $this->moduleContext->postDateFormatter->formatFromDateString($lastHit)) . '</td>'
			. '</tr>';
	}

	/**
	 * @param list<array> $rows Rows of mostHitThreads().
	 * @param array<int, threadCacheDiskStats> $diskStats
	 */
	private function renderMostHitTable(array $rows, array $diskStats): string {
		if ($rows === []) {
			return '<p class="threadCacheEmpty">' . sanitizeStr(_T('thread_cache_no_hits')) . '</p>';
		}

		$boards = [];
		foreach (GLOBAL_BOARD_ARRAY as $board) {
			$boards[$board->getBoardUID()] = $board;
		}

		$html = '<table class="postlists threadCacheTable"><thead><tr>';
		foreach (['board', 'thread_no', 'hits', 'misses', 'hit_rate', 'last_hit', 'cached'] as $column) {
			$html .= '<th>' . sanitizeStr(_T('thread_cache_col_' . $column)) . '</th>';
		}
		$html .= '</tr></thead><tbody>';

		foreach ($rows as $row) {
			$boardUid = (int)$row['board_uid'];
			$board = $boards[$boardUid] ?? null;
			$number = (int)$row['post_op_number'];
			$threadCell = $board === null
				? 'No.' . $number
				: '<a href="' . sanitizeStr($board->getBoardThreadURL($number)) . '">No.' . $number . '</a>';
			$cached = isset($diskStats[$boardUid]->threadUids[$row['thread_uid']]);

			$html .= '<tr>'
				. '<td>' . ($board === null ? $boardUid : $this->boardLabel($board)) . '</td>'
				. '<td>' . $threadCell . '</td>'
				. '<td>' . number_format((int)$row['hits']) . '</td>'
				. '<td>' . number_format((int)$row['misses']) . '</td>'
				. '<td>' . self::hitRate((int)$row['hits'], (int)$row['misses']) . '</td>'
				. '<td>' . ($row['last_hit_at'] === null ? '-' : $this->moduleContext->postDateFormatter->formatFromDateString($row['last_hit_at'])) . '</td>'
				. '<td>' . sanitizeStr(_T($cached ? 'thread_cache_yes' : 'thread_cache_no')) . '</td>'
				. '</tr>';
		}

		return $html . '</tbody></table>';
	}

	/** Markup: the title is configuration stored as HTML, and is emitted as it is everywhere else. */
	private function boardLabel(board $board): string {
		return '/' . sanitizeStr($board->getBoardIdentifier()) . '/ ' . $board->getBoardTitle();
	}

	private static function hitRate(int $hits, int $misses): string {
		$total = $hits + $misses;

		return $total === 0 ? '-' : number_format($hits * 100 / $total, 1) . '%';
	}
}
