<?php

// rebuild route - handle board rebuilding

namespace Kokonotsuba\routers\routes;

use Kokonotsuba\board\board;
use Kokonotsuba\error\softErrorHandler;
use Kokonotsuba\action_log\actionLoggerService;
use Kokonotsuba\userRole;
use function Puchiko\request\redirect;

class rebuildRoute {
	public function __construct(
		private board $board,
		private readonly softErrorHandler $softErrorHandler,
		private readonly actionLoggerService $actionLoggerService,
	) {}

	public function handleRebuild(): void {
		$this->softErrorHandler->handleAuthError(userRole::LEV_JANITOR);

		$this->actionLoggerService->logAction("Rebuilt pages", $this->board->getBoardUID());
		$this->board->updateBoardPathCache(); 
		$this->board->rebuildBoard();

		header('HTTP/1.1 302 Moved Temporarily');
		redirect($this->board->getBoardURL(false, true) . '?' . time());
	}
}
