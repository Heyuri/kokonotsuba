<?php

// overboard route - shows threads from all/selected/listed boards

class overboardRoute {
	public function __construct(
		private readonly array $config,
		private readonly array $visibleBoards,
		private readonly boardRepository $boardRepository,
		private board $board,
		private overboard $overboard,
	) {}

	public function drawOverboard(): void {
		$this->handleOverboardFilterForm();

		$blacklistBoards = (!empty($_COOKIE['overboard_black_list'])) 
			? json_decode($_COOKIE['overboard_black_list'], true) 
			: [];

		if (!is_array($blacklistBoards)) {
			$blacklistBoards = [];
		}

		$allBoards = $this->boardRepository->getAllListedBoardUIDs();
		$allowedBoards = array_values(array_diff($allBoards, $blacklistBoards));

		$filters = [
			'board' => $allowedBoards,
		];

		$html = '';

		$this->overboard->drawOverboardHead($html);

		drawOverboardFilterForm($html, $this->board, $this->visibleBoards, $allowedBoards);

		$html .= $this->overboard->drawOverboardThreads($filters);

		$html .= $this->board->getBoardFooter();

		echo $html;
	}


	private function handleOverboardFilterForm(): void {
		if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
			return;
		}

		$action = $_POST['filterformsubmit'] ?? null;

		if ($action === 'filter') {
			$selectedBoards = $_POST['board'] ?? '';
			$selectedBoards = is_array($selectedBoards)
				? array_map('intval', $selectedBoards)
				: [intval($selectedBoards)];

			$allBoards = $this->boardRepository->getAllListedBoardUIDs();

			// Blacklist = all - selected
			$blacklist = array_values(array_diff($allBoards, $selectedBoards));

			setcookie('overboard_black_list', json_encode($blacklist), time() + (86400 * 30), "/");

			redirect($this->config['LIVE_INDEX_FILE'] . '?mode=overboard');
			exit;

		} elseif ($action === 'filterclear') {
			setcookie('overboard_black_list', "", time() - 3600, "/");

			redirect($this->config['LIVE_INDEX_FILE'] . '?mode=overboard');
			exit;
		}
	}


}

