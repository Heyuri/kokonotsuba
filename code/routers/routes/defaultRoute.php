<?php

// default route - live front end / redirect to static html

class defaultRoute {
	private readonly array $config;
	private readonly board $board;
	private readonly actionLogger $actionLogger;
	private readonly globalHTML $globalHTML;

	public function __construct(
		array $config,
		board $board,
		actionLogger $actionLogger,
		globalHTML $globalHTML
	) {
		$this->config = $config;
		$this->board = $board;
		$this->actionLogger = $actionLogger;
		$this->globalHTML = $globalHTML;
	}

	public function handleDefault(): void {
		header('Content-Type: text/html; charset=utf-8');

		$res = isset($_GET['res']) ? intval($_GET['res']) : 0;
		$pageParam = $_GET['pagenum'] ?? null;

		if ($res > 0) {
			$page = ($pageParam === 'all' || $pageParam === 'RE_PAGE_MAX')
				? $pageParam
				: intval($pageParam);
			$this->board->drawThread($res);
		} elseif ($pageParam !== null && intval($pageParam) > -1) {
			$this->board->drawPage(intval($pageParam));
		} else {
			if (!is_file($this->config['PHP_SELF2'])) {
				$this->actionLogger->logAction("Rebuilt pages", $this->board->getBoardUID());
				$this->board->updateBoardPathCache();
				$this->board->rebuildBoard();
			}
			header('HTTP/1.1 302 Moved Temporarily');
			header('Location: ' . $this->globalHTML->fullURL() . $this->config['PHP_SELF2'] . '?' . $_SERVER['REQUEST_TIME']);
		}
	}
}
