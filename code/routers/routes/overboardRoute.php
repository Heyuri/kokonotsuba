<?php

// overboard route - shows threads from all/selected/listed boards

class overboardRoute {
	private readonly array $config;
	private readonly boardIO $boardIO;
	private readonly board $board;
	private readonly overboard $overboard;
	private readonly globalHTML $globalHTML;

	public function __construct(
		array $config,
		boardIO $boardIO,
		board $board,
		overboard $overboard,
		globalHTML $globalHTML
	) {
		$this->config = $config;
		$this->boardIO = $boardIO;
		$this->board = $board;
		$this->overboard = $overboard;
		$this->globalHTML = $globalHTML;
	}

	public function drawOverboard(): void {
		$this->handleOverboardFilterForm();

		$blacklistBoards = (!empty($_COOKIE['overboard_black_list'])) 
			? json_decode($_COOKIE['overboard_black_list'], true) 
			: [];

		if (!is_array($blacklistBoards)) {
			$blacklistBoards = [];
		}

		$allBoards = $this->boardIO->getAllListedBoardUIDs();
		$allowedBoards = array_values(array_diff($allBoards, $blacklistBoards));

		$filters = [
			'board' => $allowedBoards,
		];

		$html = '';
		$this->overboard->drawOverboardHead($html, 0);
		$this->globalHTML->drawOverboardFilterForm($html, $this->board);
		$html .= $this->overboard->drawOverboardThreads($filters, $this->globalHTML);
		$this->globalHTML->foot($html, 0);

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

			$boardIO = boardIO::getInstance();
			$allBoards = $boardIO->getAllListedBoardUIDs();

			// Blacklist = all - selected
			$blacklist = array_values(array_diff($allBoards, $selectedBoards));

			setcookie('overboard_black_list', json_encode($blacklist), time() + (86400 * 30), "/");

			redirect($this->config['PHP_SELF'] . '?mode=overboard');
			exit;

		} elseif ($action === 'filterclear') {
			setcookie('overboard_black_list', "", time() - 3600, "/");

			redirect($this->config['PHP_SELF'] . '?mode=overboard');
			exit;
		}
	}


}

