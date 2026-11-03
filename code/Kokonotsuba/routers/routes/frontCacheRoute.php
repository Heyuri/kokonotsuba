<?php

namespace Kokonotsuba\routers\routes;

use Kokonotsuba\error\BoardException;
use Kokonotsuba\front_pages\frontPageCache;
use Kokonotsuba\front_pages\frontPageLinks;
use Kokonotsuba\policy\frontPagePolicy;
use Kokonotsuba\routers\route;

use function Kokonotsuba\libraries\_T;
use function Puchiko\request\redirect;

/** Forgets every cached front page view (?mode=rebuildCache); the next visitor draws them fresh. */
final class frontCacheRoute implements route {
	public function __construct(
		private readonly frontPageCache $frontPageCache,
		private readonly frontPageLinks $links,
		private readonly frontPagePolicy $frontPagePolicy,
	) {}

	public function handle(): void {
		if (!$this->frontPagePolicy->canEditSite()) {
			throw new BoardException(_T('front_admin_denied'), 403);
		}

		$this->frontPageCache->forgetAll();

		redirect($this->links->admin('frontAdminCache'));
	}
}
