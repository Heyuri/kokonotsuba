<?php

namespace Kokonotsuba\routers;

use Kokonotsuba\containers\appContainer;
use Kokonotsuba\routers\routes\frontAdminRoute;
use Kokonotsuba\routers\routes\frontLogoRoute;
use Kokonotsuba\routers\routes\frontNewsEditRoute;
use Kokonotsuba\routers\routes\frontPageEditRoute;
use Kokonotsuba\routers\routes\frontPageRoute;

/**
 * Every front page mode. The empty mode is news, a news entry, a page or a module's page; admin,
 * editNews, editPage and logo are the staff screens. A mode missing here is a 404.
 */
final class frontPageRouteTable {
	/**
	 * @return array<string, routeEntry>
	 */
	public static function routes(): array {
		return [
			'' => new routeEntry(fn(appContainer $c) => new frontPageRoute(
				$c->get('frontPageRenderer'),
				$c->get('frontPageCache'),
				$c->get('newsService'),
				$c->get('frontPageService'),
				$c->get('frontPagePolicy'),
				$c->get('siteConfig'),
				$c->get('staffAccountFromSession'),
				$c->get('request'),
				$c->get('frontModuleEngine'),
			)),
			'admin' => new routeEntry(fn(appContainer $c) => new frontAdminRoute(
				$c->get('frontPageRenderer'),
				$c->get('frontAdminRenderer'),
				$c->get('newsService'),
				$c->get('frontPageService'),
				$c->get('frontPagePolicy'),
				$c->get('staffAccountFromSession'),
				$c->get('boardService'),
				$c->get('siteConfig'),
				$c->get('request'),
			)),
			'logo' => new routeEntry(fn(appContainer $c) => new frontLogoRoute(
				$c->get('frontLogoService'),
				$c->get('frontPageLinks'),
				$c->get('frontPagePolicy'),
				$c->get('request'),
			), csrfRule::POST_ONLY),
			'editNews' => new routeEntry(fn(appContainer $c) => new frontNewsEditRoute(
				$c->get('frontPageRenderer'),
				$c->get('frontAdminRenderer'),
				$c->get('frontPageLinks'),
				$c->get('newsService'),
				$c->get('frontPagePolicy'),
				$c->get('currentUserId'),
				$c->get('request'),
			), csrfRule::ON_POST),
			'editPage' => new routeEntry(fn(appContainer $c) => new frontPageEditRoute(
				$c->get('frontPageRenderer'),
				$c->get('frontAdminRenderer'),
				$c->get('frontPageLinks'),
				$c->get('frontPageService'),
				$c->get('frontPagePolicy'),
				$c->get('currentUserId'),
				$c->get('request'),
			), csrfRule::ON_POST),
		];
	}
}
