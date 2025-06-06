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

		$filtersBoards = (!empty($_COOKIE['overboard_filterboards'])) 
			? json_decode($_COOKIE['overboard_filterboards'], true) 
			: null;

		$filters = [
			'board' => $filtersBoards ?? $this->boardIO->getAllListedBoardUIDs(),
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
			$filterBoardFromPOST = $_POST['board'] ?? '';
			$filterBoard = is_array($filterBoardFromPOST)
				? array_map('htmlspecialchars', $filterBoardFromPOST)
				: [htmlspecialchars($filterBoardFromPOST)];

			setcookie('overboard_filterboards', json_encode($filterBoard), time() + (86400 * 30), "/");

			redirect($this->config['PHP_SELF'] . '?mode=overboard');
			exit;

		} elseif ($action === 'filterclear') {
			setcookie('overboard_filterboards', "", time() - 3600, "/");

			redirect($this->config['PHP_SELF'] . '?mode=overboard');
			exit;
		}
	}

}

