<?php

// overboard route - shows threads from all/selected/listed boards

namespace Kokonotsuba\routers\routes;

use Kokonotsuba\board\boardRepository;
use Kokonotsuba\board\board;
use Kokonotsuba\cookie\cookieService;
use Kokonotsuba\overboard;
use Kokonotsuba\request\request;
use function Kokonotsuba\libraries\createAssocArrayFromBoardArray;
use function Kokonotsuba\libraries\html\drawOverboardFilterForm;
use function Puchiko\request\redirect;

class overboardRoute {
	public function __construct(
		private readonly array $config,
		private readonly array $visibleBoards,
		private readonly boardRepository $boardRepository,
		private board $board,
		private overboard $overboard,
		private readonly cookieService $cookieService,
		private readonly request $request,
	) {}

	public function drawOverboard(): void {
		$this->handleOverboardFilterForm();

		$blacklistCookie = $this->cookieService->get('overboard_black_list', '');
		$blacklistBoards = ($blacklistCookie !== '') 
			? json_decode($blacklistCookie, true) 
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

		// draw the overboard header
		$this->overboard->drawOverboardHead($html);

		$arrayForFilter = createAssocArrayFromBoardArray($this->visibleBoards);

		// draw filter form
		drawOverboardFilterForm($html, $this->board, $arrayForFilter, $allowedBoards);

		// draw threads
		$html .= $this->overboard->drawOverboardThreads($filters);

		// draw footer
		$html .= $this->board->getBoardFooter();

		echo $html;
	}


	private function handleOverboardFilterForm(): void {
		if (!$this->request->isPost()) {
			return;
		}

		$action = $this->request->getParameter('filterformsubmit', 'POST');

		if ($action === 'filter') {
			$selectedBoards = $this->request->getParameter('board', 'POST', '');
			$selectedBoards = is_array($selectedBoards)
				? array_map('intval', $selectedBoards)
				: [intval($selectedBoards)];

			$allBoards = $this->boardRepository->getAllListedBoardUIDs();

			// Blacklist = all - selected
			$blacklist = array_values(array_diff($allBoards, $selectedBoards));

			$this->cookieService->set('overboard_black_list', json_encode($blacklist), time() + (86400 * 30), '/');

			redirect($this->config['LIVE_INDEX_FILE'] . '?mode=overboard');
			exit;

		} elseif ($action === 'filterclear') {
			$this->cookieService->delete('overboard_black_list', '/');

			redirect($this->config['LIVE_INDEX_FILE'] . '?mode=overboard');
			exit;
		}
	}


}

