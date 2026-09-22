<?php

// overboard route - shows threads from all/selected/listed boards

namespace Kokonotsuba\routers\routes;

use Kokonotsuba\board\boardRepository;
use Kokonotsuba\board\board;
use Kokonotsuba\board\overboardBoardFilter;
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

		$allowedBoards = $this->readFilter()->allowedBoards();

		$filters = [
			'board' => $allowedBoards,
		];

		$html = '';

		// draw threads before the header - modules that gather markup while rendering
		// threads (such as thread styling) write it into the head, so the head has to
		// be built once the threads are done
		$threadsHtml = $this->overboard->drawOverboardThreads($filters);

		// draw the overboard header
		$this->overboard->drawOverboardHead($html);

		$arrayForFilter = createAssocArrayFromBoardArray($this->visibleBoards);

		// draw filter form
		drawOverboardFilterForm($html, $this->board, $arrayForFilter, $allowedBoards);

		// add another hr
		$html .= '<hr>';

		// draw threads
		$html .= $threadsHtml;

		// draw footer
		$html .= $this->board->getBoardFooter();

		echo $html;
	}


	/** The reader's board selection, from the cookie and the boards currently listed. */
	private function readFilter(): overboardBoardFilter {
		return overboardBoardFilter::fromCookie(
			(string)$this->cookieService->get(overboardBoardFilter::COOKIE_NAME, ''),
			$this->boardRepository->getAllListedBoardUIDs()
		);
	}

	private function handleOverboardFilterForm(): void {
		if (!$this->request->isPost()) {
			return;
		}

		$action = $this->request->getParameter('filterformsubmit', 'POST');

		if ($action === 'filter') {
			$selectedBoards = $this->request->getParameter('board', 'POST', '');
			$selectedBoards = is_array($selectedBoards) ? $selectedBoards : [$selectedBoards];

			$filter = overboardBoardFilter::fromSelection(
				$selectedBoards,
				$this->boardRepository->getAllListedBoardUIDs()
			);

			$this->cookieService->set(
				overboardBoardFilter::COOKIE_NAME,
				$filter->toCookieValue(),
				time() + overboardBoardFilter::COOKIE_LIFETIME,
				'/'
			);

			redirect($this->config['LIVE_INDEX_FILE'] . '?mode=overboard');
			exit;

		} elseif ($action === 'filterclear') {
			$this->cookieService->delete(overboardBoardFilter::COOKIE_NAME, '/');

			redirect($this->config['LIVE_INDEX_FILE'] . '?mode=overboard');
			exit;
		}
	}


}

