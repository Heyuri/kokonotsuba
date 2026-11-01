<?php

namespace Kokonotsuba\routers\routes;

use Kokonotsuba\error\BoardException;
use Kokonotsuba\front_pages\frontAdminRenderer;
use Kokonotsuba\front_pages\frontEditInput;
use Kokonotsuba\front_pages\frontPageLinks;
use Kokonotsuba\front_pages\frontPageRenderer;
use Kokonotsuba\news\newsService;
use Kokonotsuba\policy\frontPagePolicy;
use Kokonotsuba\request\request;
use Kokonotsuba\routers\route;

use function Kokonotsuba\libraries\_T;
use function Puchiko\request\redirect;

/**
 * Writes or edits a news entry (?mode=editNews[&id=N]). A refused save draws the form again with
 * what was typed; a good one goes to the entry.
 */
final class frontNewsEditRoute implements route {
	public function __construct(
		private readonly frontPageRenderer $renderer,
		private readonly frontAdminRenderer $adminRenderer,
		private readonly frontPageLinks $links,
		private readonly newsService $newsService,
		private readonly frontPagePolicy $frontPagePolicy,
		private readonly ?int $currentUserId,
		private readonly request $request,
	) {}

	public function handle(): void {
		if (!$this->frontPagePolicy->canEditNews()) {
			throw new BoardException(_T('front_admin_denied'), 403);
		}

		$id = frontEditInput::id($this->request);
		$entry = $id === null ? null : ($this->newsService->getEntry($id, true) ?? throw new BoardException(_T('news_not_found'), 404));

		if (!$this->request->isPost()) {
			$this->draw(frontAdminRenderer::newsFields($entry));
			return;
		}

		if ($entry !== null && frontEditInput::text($this->request, 'action') === 'delete') {
			$this->newsService->deleteEntry($entry->id);
			redirect($this->links->admin());
		}

		$fields = [
			'id'        => $entry?->id ?? 0,
			'subject'   => frontEditInput::text($this->request, 'subject'),
			'byline'    => frontEditInput::text($this->request, 'byline'),
			'email'     => frontEditInput::text($this->request, 'email'),
			'body'      => frontEditInput::text($this->request, 'body'),
			'published' => frontEditInput::checked($this->request, 'published'),
		];

		try {
			if ($entry === null) {
				$id = $this->newsService->createEntry($fields['subject'], $fields['body'], $fields['byline'], $fields['email'], $fields['published'], $this->currentUserId);
			} else {
				$this->newsService->updateEntry($entry->id, $fields['subject'], $fields['body'], $fields['byline'], $fields['email'], $fields['published']);
			}
		} catch (BoardException $e) {
			http_response_code(400);
			$this->draw($fields, $e->getMessage());
			return;
		}

		redirect($this->links->news($id));
	}

	private function draw(array $fields, string $error = ''): void {
		$title = _T($fields['id'] === 0 ? 'front_admin_new_news' : 'front_admin_edit_news');

		echo $this->renderer->renderStaffPage($title, $this->adminRenderer->newsForm($fields, $error));
	}
}
