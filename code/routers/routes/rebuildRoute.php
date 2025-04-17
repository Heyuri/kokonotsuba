<?php

// rebuild route - handle board rebuilding

class rebuildRoute {
	private readonly array $config;
	private readonly board $board;
	private readonly softErrorHandler $softErrorHandler;
	private readonly actionLogger $actionLogger;
	private readonly globalHTML $globalHTML;

	public function __construct(
		array $config,
		board $board,
		softErrorHandler $softErrorHandler,
		actionLogger $actionLogger,
		globalHTML $globalHTML
	) {
		$this->config = $config;
		$this->board = $board;
		$this->softErrorHandler = $softErrorHandler;
		$this->actionLogger = $actionLogger;
		$this->globalHTML = $globalHTML;
	}

	public function handleRebuild(): void {
		$this->softErrorHandler->handleAuthError($this->config['roles']['LEV_JANITOR']);

		$this->actionLogger->logAction("Rebuilt pages", $this->board->getBoardUID());
		$this->board->updateBoardPathCache(); 
		$this->board->rebuildBoard();

		header('HTTP/1.1 302 Moved Temporarily');
		redirect($this->globalHTML->fullURL() . $this->config['PHP_SELF2'] . '?' . time());
	}
}
