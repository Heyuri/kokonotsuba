<?php

namespace Kokonotsuba\routers\routes;

use Kokonotsuba\error\BoardException;
use Kokonotsuba\front_pages\frontAdminRenderer;
use Kokonotsuba\front_pages\frontEditInput;
use Kokonotsuba\front_pages\frontPageLinks;
use Kokonotsuba\front_pages\frontPageRenderer;
use Kokonotsuba\front_pages\frontPageService;
use Kokonotsuba\policy\frontPagePolicy;
use Kokonotsuba\request\request;
use Kokonotsuba\routers\route;

use function Kokonotsuba\libraries\_T;
use function Puchiko\request\redirect;

/**
 * Creates or edits a front page page (?mode=editPage[&id=N]). A refused save draws the form again
 * with what was typed; a good one goes to the page.
 */
final class frontPageEditRoute implements route {
	public function __construct(
		private readonly frontPageRenderer $renderer,
		private readonly frontAdminRenderer $adminRenderer,
		private readonly frontPageLinks $links,
		private readonly frontPageService $frontPageService,
		private readonly frontPagePolicy $frontPagePolicy,
		private readonly ?int $currentUserId,
		private readonly request $request,
	) {}

	public function handle(): void {
		if (!$this->frontPagePolicy->canEditPages()) {
			throw new BoardException(_T('front_admin_denied'), 403);
		}

		$id = frontEditInput::id($this->request);
		$page = $id === null ? null : ($this->frontPageService->getPageById($id) ?? throw new BoardException(_T('front_page_not_found'), 404));

		if (!$this->request->isPost()) {
			$this->draw(frontAdminRenderer::pageFields($page));
			return;
		}

		if ($page !== null && frontEditInput::text($this->request, 'action') === 'delete') {
			$this->frontPageService->deletePage($page->id);
			redirect($this->links->admin());
		}

		$fields = [
			'id'          => $page?->id ?? 0,
			'title'       => frontEditInput::text($this->request, 'title'),
			'slug'        => frontEditInput::text($this->request, 'slug'),
			'body'        => frontEditInput::text($this->request, 'body'),
			'published'   => frontEditInput::checked($this->request, 'published'),
			'show_in_nav' => frontEditInput::checked($this->request, 'show_in_nav'),
			'nav_order'   => max(0, min(9999, (int)frontEditInput::text($this->request, 'nav_order'))),
		];

		try {
			if ($page === null) {
				$this->frontPageService->createPage($fields['slug'], $fields['title'], $fields['body'], $fields['published'], $fields['show_in_nav'], $fields['nav_order'], $this->currentUserId);
			} else {
				$this->frontPageService->updatePage($page->id, $fields['slug'], $fields['title'], $fields['body'], $fields['published'], $fields['show_in_nav'], $fields['nav_order']);
			}
		} catch (BoardException $e) {
			http_response_code(400);
			$this->draw($fields, $e->getMessage());
			return;
		}

		redirect($this->links->page(frontPageService::normalizeSlug($fields['slug'])));
	}

	private function draw(array $fields, string $error = ''): void {
		$title = _T($fields['id'] === 0 ? 'front_admin_new_page' : 'front_admin_edit_page');

		echo $this->renderer->renderStaffPage($title, $this->adminRenderer->pageForm($fields, $error));
	}
}
