<?php

// rebuild route - handle board rebuilding

class rebuildRoute {
	public function __construct(
		private board $board,
		private readonly softErrorHandler $softErrorHandler,
		private readonly actionLoggerService $actionLoggerService,
	) {}

	public function handleRebuild(): void {
		$this->softErrorHandler->handleAuthError(\Kokonotsuba\Root\Constants\userRole::LEV_JANITOR);

		$this->actionLoggerService->logAction("Rebuilt pages", $this->board->getBoardUID());
		$this->board->updateBoardPathCache(); 
		$this->board->rebuildBoard();

		header('HTTP/1.1 302 Moved Temporarily');
		redirect($this->board->getBoardURL(false, true) . '?' . time());
	}
}
