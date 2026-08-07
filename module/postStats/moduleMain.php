<?php

namespace Kokonotsuba\Modules\postStats;

require_once __DIR__ . '/postStatsDates.php';
require_once __DIR__ . '/postStatsBuildQueue.php';
require_once __DIR__ . '/postStatsRepository.php';
require_once __DIR__ . '/postStatsService.php';
require_once __DIR__ . '/postStatsRenderer.php';

use Kokonotsuba\board\board;
use Kokonotsuba\module_classes\abstractModuleMain;
use Kokonotsuba\module_classes\traits\listeners\ModuleHeaderListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\TopLinksListenerTrait;
use Puchiko\background\BackgroundTaskRegistry;

use function Kokonotsuba\libraries\_T;

class moduleMain extends abstractModuleMain {
	use TopLinksListenerTrait;
	use ModuleHeaderListenerTrait;

	private readonly string $modulePageUrl;
	private readonly bool $showSiteWide;
	private readonly bool $showBoardTable;
	private readonly int $defaultRangeDays;
	private readonly int $maxBars;

	public function getName(): string {
		return 'Post statistics';
	}

	public function getVersion(): string {
		return 'Koko BBS Release 1';
	}

	public function initialize(): void {
		$this->modulePageUrl = $this->getModulePageURL([], false);
		$this->showSiteWide = (bool)$this->getModuleConfig('SHOW_SITE_WIDE', true);
		$this->showBoardTable = (bool)$this->getModuleConfig('SHOW_BOARD_TABLE', true);
		$this->defaultRangeDays = (int)$this->getModuleConfig('DEFAULT_RANGE_DAYS', 30);
		$this->maxBars = max(10, (int)$this->getModuleConfig('MAX_BARS', 120));

		$this->addTopLink($this->modulePageUrl, _T('poststats_link'));
		$this->listenModuleHeader('onGenerateModuleHeader');

		BackgroundTaskRegistry::register(
			postStatsBackgroundQueue::TASK_NAME,
			postStatsTask::class,
			__DIR__ . '/postStatsTask.php'
		);
	}

	/** The stylesheet is only of use on the stats page, so it is not put on every board page. */
	private function onGenerateModuleHeader(string &$moduleHeader): void {
		if (!$this->isStatsPageRequest()) {
			return;
		}

		$moduleHeader .= $this->moduleContext->templateEngine->ParseBlock('POSTSTATS_HEAD', [
			'{$CSS_URL}' => htmlspecialchars($this->getConfig('STATIC_URL') . 'css/module/postStats.css'),
		]);
	}

	private function isStatsPageRequest(): bool {
		$request = $this->moduleContext->request;

		return $request->getParameter('mode', default: '') === 'module'
			&& $request->getParameter('load', default: '') === $this->moduleName;
	}

	public function ModulePage(): void {
		$board = $this->moduleContext->board;
		$range = $this->resolveRange();
		$rangeDays = postStatsRenderer::RANGES[$range]['days'];

		$service = $this->buildService();
		$renderer = new postStatsRenderer($this->moduleContext->templateEngine, $this->maxBars);

		$boardSection = $this->renderScope(
			$renderer,
			$service->getBoardStats($board->getBoardUID()),
			'board',
			$this->describeBoard($board),
			_T('poststats_chart_board', $this->describeBoard($board)),
			$range,
			$rangeDays,
			true
		);

		$page = $this->moduleContext->templateEngine->ParseBlock('POSTSTATS_PAGE', [
			'{$RETURN_URL}' => htmlspecialchars($this->getConfig('STATIC_INDEX_FILE') . '?' . time()),
			'{$RETURN_TEXT}' => htmlspecialchars(_T('return')),
			'{$PAGE_TITLE}' => htmlspecialchars(_T('poststats_title')),
			'{$NOTE}' => htmlspecialchars(_T('poststats_note')),
			'{$BOARD_SECTION}' => $boardSection,
			'{$SITE_SECTION}' => $this->showSiteWide
				? $this->renderSiteWideSection($service, $renderer, $range, $rangeDays)
				: '',
		]);

		echo $board->getBoardHead(_T('poststats_title')) . $page . $board->getBoardFooter();
	}

	private function renderSiteWideSection(postStatsService $service, postStatsRenderer $renderer, string $range, int $rangeDays): string {
		$boards = $this->moduleContext->boardService->getAllListedBoards() ?? [];
		$boardUids = array_map(fn($board) => $board->getBoardUID(), $boards);

		if (!$boardUids) {
			return '';
		}

		$siteStats = $service->getSiteStats($boardUids);

		$html = $this->renderScope(
			$renderer,
			$siteStats,
			'sitewide',
			_T('poststats_sitewide'),
			_T('poststats_chart_site'),
			$range,
			$rangeDays,
			false,
			$boards
		);

		if (!$siteStats['generating'] && $this->showBoardTable) {
			$html .= $renderer->renderBoardTable($siteStats['boards'], $boards, $siteStats['today']);
		}

		return $html;
	}

	/**
	 * One heading's worth of page: tiles, zoom links and chart — or a notice, if the first build
	 * for this scope is still running in the background.
	 *
	 * Passing $boards draws the chart stacked, one colored segment per board, with a legend.
	 * Without them it is a single-series chart, which needs no legend to say what it plots.
	 */
	private function renderScope(
		postStatsRenderer $renderer,
		array $stats,
		string $anchor,
		string $heading,
		string $caption,
		string $range,
		int $rangeDays,
		bool $showLastNumber,
		?array $boards = null
	): string {
		if ($stats['generating']) {
			return $renderer->renderSection($anchor, $heading, $renderer->renderNotice(
				'postStatsGenerating',
				_T('poststats_generating')
			));
		}

		$series = $renderer->buildSeries($stats['days'], $stats['firstDay'], $stats['today'], $rangeDays);

		if ($boards !== null) {
			$chart = $renderer->renderStackedChart(
				$renderer->bucketStack($series, $stats['dayList'], $stats['series'], $stats['today']),
				$renderer->assignSeries($stats['ranked'], $boards),
				$caption
			);
		} else {
			$chart = $renderer->renderChart($renderer->bucketSeries($series, $stats['today']), $caption);
		}

		$body = $renderer->renderTiles($stats, $series, _T(postStatsRenderer::RANGES[$range]['label']), $showLastNumber)
			. $renderer->renderRangeLinks($this->getModulePageURL(), $range, $anchor)
			. $chart;

		return $renderer->renderSection($anchor, $heading, $body);
	}

	private function buildService(): postStatsService {
		$dbSettings = $this->moduleContext->dbSettings;
		$cacheDirectory = \getBackendGlobalDir() . 'post-stats/';

		$repository = new postStatsRepository(
			$this->moduleContext->databaseConnection,
			$dbSettings['POST_TABLE'],
			$dbSettings['POST_NUMBER_TABLE']
		);

		return new postStatsService($repository, $cacheDirectory, new postStatsBackgroundQueue($cacheDirectory));
	}

	private function resolveRange(): string {
		$requested = (string)$this->moduleContext->request->getParameter('range', 'GET', '');

		if (isset(postStatsRenderer::RANGES[$requested])) {
			return $requested;
		}

		foreach (postStatsRenderer::RANGES as $key => $range) {
			if ($range['days'] === $this->defaultRangeDays) {
				return $key;
			}
		}

		return 'all';
	}

	private function describeBoard(board $board): string {
		return $board->getBoardTitle();
	}
}
