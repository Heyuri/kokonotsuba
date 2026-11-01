<?php

namespace Kokonotsuba\routers\routes;

use Kokonotsuba\error\BoardException;
use Kokonotsuba\front_pages\frontLogoService;
use Kokonotsuba\front_pages\frontPageLinks;
use Kokonotsuba\policy\frontPagePolicy;
use Kokonotsuba\request\request;
use Kokonotsuba\routers\route;

use function Kokonotsuba\libraries\_T;
use function Puchiko\request\redirect;

/** Uploads (?mode=logo) or, with action=remove, removes the front page logo; back to the admin after. */
final class frontLogoRoute implements route {
	public function __construct(
		private readonly frontLogoService $logoService,
		private readonly frontPageLinks $links,
		private readonly frontPagePolicy $frontPagePolicy,
		private readonly request $request,
	) {}

	public function handle(): void {
		if (!$this->frontPagePolicy->canEditSite()) {
			throw new BoardException(_T('front_admin_denied'), 403);
		}

		if ($this->request->getParameter('action', 'POST') === 'remove') {
			$this->logoService->remove();
		} else {
			$this->logoService->upload($this->request->getFile('logo'));
		}

		redirect($this->links->admin());
	}
}
