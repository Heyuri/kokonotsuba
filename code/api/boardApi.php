<?php

class boardApi {
	public function __construct(
		private readonly boardService $boardService
	) {}

	/**
	 * Board API router — determines which board-related endpoint to handle.
	 *
	 * @param string $endpointPage The board API endpoint (e.g., 'listed', 'all', 'single', etc.)
	 * @return void
	 */
	public function invoke(string $endpointPage): void {
		try {
			switch ($endpointPage) {
				// GET /?page=board&endpoint=listed
				case 'listed':
					$this->renderListedBoardListJson();
					break;

				// GET /?page=board&endpoint=all
				// Will sort out role for later but it should be admin only
				/*case 'all':
					$this->renderBoardListJson();
					break;*/

				// GET /?page=board&endpoint=single&board_uid=##
				case 'single':
					$boardUid = isset($_GET['board_uid']) ? (int)$_GET['board_uid'] : 0;

					if ($boardUid <= 0) {
						renderJsonErrorPage(_T('error_invalid_board_id'), 400);
					}

					$this->renderBoardJson($boardUid);
					break;
				// Unknown endpoint
				default:
					renderJsonErrorPage(_T('error_invalid_board_endpoint'), 404);
					break;
			}
		} catch (\Throwable $e) {
			// Handle any unhandled exception safely
			renderJsonErrorPage(_T('error_board_api'), 500);
		}
	}

	/**
	 * Returns JSON for all boards that are marked as "listed".
	 *
	 * @return void
	 */
	private function renderListedBoardListJson(): void {
		try {
			$boards = $this->boardService->getAllListedBoards();

			if (empty($boards)) {
				renderJsonErrorPage(_T('no_boards_found'), 404);
			}

			$json = $this->buildBoardListArray($boards);

			// 10 min cache headers only
			renderCachedJsonPage($json, 600);
		} catch (\Throwable $e) {
			renderJsonErrorPage(_T('error_board_api'), 500);
		}
	}

	/**
	 * Returns JSON for all boards, regardless of listing status.
	 *
	 * @return void
	 */
	private function renderBoardListJson(): void {
		try {
			$boards = $this->boardService->getAllBoards();

			if (empty($boards)) {
				renderJsonErrorPage(_T('no_boards_found'), 404);
			}

			$json = $this->buildBoardListArray($boards);

			renderCachedJsonPage($json, 600);
		} catch (\Throwable $e) {
			renderJsonErrorPage(_T('error_board_api'), 500);
		}
	}

	/**
	 * Renders JSON for a specific board.
	 *
	 * @param int $boardUid
	 * @return void
	 */
	private function renderBoardJson(int $boardUid): void {
		try {
			$board = $this->boardService->getBoard($boardUid);

			if (!$board) {
				renderJsonErrorPage(_T('board_not_found'), 404);
			}

			$json = $this->buildBoardArray($board);

			renderCachedJsonPage($json, 600);
		} catch (\Throwable $e) {
			renderJsonErrorPage(_T('error_board_api'), 500);
		}
	}

	/**
	 * Builds a sanitized array for a single board.
	 *
	 * @param board $board
	 * @return array
	 */
	private function buildBoardArray(board $board): array {
		return [
			'board_uid'     => $board->getBoardUID(),
			'identifier'    => $board->getBoardIdentifier(),
			'title'         => $board->getBoardTitle(),
			'sub_title'     => $board->getBoardSubTitle(),
			'listed'        => (bool)$board->getBoardListed(),
		];
	}

	/**
	 * Converts an array of board objects into an array of arrays.
	 *
	 * @param array $boards
	 * @return array
	 */
	private function buildBoardListArray(array $boards): array {
		$result = [];

		foreach ($boards as $board) {
			if ($board instanceof board) {
				$result[] = $this->buildBoardArray($board);
			}
		}

		return $result;
	}
}
