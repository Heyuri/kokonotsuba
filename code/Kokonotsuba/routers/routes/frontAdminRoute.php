<?php

namespace Kokonotsuba\routers\routes;

use Kokonotsuba\account\staffAccountFromSession;
use Kokonotsuba\board\boardService;
use Kokonotsuba\error\BoardException;
use Kokonotsuba\front_pages\frontAdminRenderer;
use Kokonotsuba\front_pages\frontPageRenderer;
use Kokonotsuba\front_pages\frontPageService;
use Kokonotsuba\news\newsService;
use Kokonotsuba\policy\frontPagePolicy;
use Kokonotsuba\request\request;
use Kokonotsuba\routers\route;

use function Kokonotsuba\libraries\_T;
use function Puchiko\request\redirect;

/**
 * The front page's [Admin]: the news and page lists for staff who may edit them. A visitor is sent
 * to the first listed board's login, as the front page has none of its own.
 */
final class frontAdminRoute implements route {
	private const NEWS_PER_PAGE = 30;

	public function __construct(
		private readonly frontPageRenderer $renderer,
		private readonly frontAdminRenderer $adminRenderer,
		private readonly newsService $newsService,
		private readonly frontPageService $frontPageService,
		private readonly frontPagePolicy $frontPagePolicy,
		private readonly staffAccountFromSession $staffAccountFromSession,
		private readonly boardService $boardService,
		private readonly array $siteConfig,
		private readonly request $request,
	) {}

	public function handle(): void {
		$canNews = $this->frontPagePolicy->canEditNews();
		$canPages = $this->frontPagePolicy->canEditPages();

		if (!$canNews && !$canPages) {
			if (!$this->staffAccountFromSession->getRoleLevel()->isStaff()) {
				$this->redirectToLogin();
			}
			throw new BoardException(_T('front_admin_denied'), 403);
		}

		$page = $this->request->getParameter('page', 'GET');
		$page = is_string($page) && ctype_digit($page) ? max(1, (int)$page) : 1;

		$html = $this->adminRenderer->dashboard(
			$canNews ? $this->newsService->listPage($page, self::NEWS_PER_PAGE, true) : null,
			$canPages ? $this->frontPageService->getAllPages() : null,
			$this->frontPagePolicy->canEditSite() ? (string)($this->siteConfig['SITE']['LOGO'] ?? '') : null,
		);

		echo $this->renderer->renderStaffPage(_T('front_admin_manage'), $html);
	}

	private function redirectToLogin(): never {
		$boardUrl = $this->boardService->getFirstListedBoardUrl();
		if ($boardUrl === '') {
			throw new BoardException(_T('front_admin_denied'), 403);
		}

		redirect($boardUrl . '?mode=admin');
		exit;
	}
}
