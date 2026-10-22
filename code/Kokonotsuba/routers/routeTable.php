<?php

namespace Kokonotsuba\routers;

use Kokonotsuba\containers\appContainer;
use Kokonotsuba\routers\routes\accountRoute;
use Kokonotsuba\routers\routes\actionLogRoute;
use Kokonotsuba\routers\routes\adminRoute;
use Kokonotsuba\routers\routes\boardsRoute;
use Kokonotsuba\routers\routes\defaultRoute;
use Kokonotsuba\routers\routes\globalConfigRoute;
use Kokonotsuba\routers\routes\handleAccountActionRoute;
use Kokonotsuba\routers\routes\handleBoardRequestsRoute;
use Kokonotsuba\routers\routes\jsonApiRoute;
use Kokonotsuba\routers\routes\managePostsRoute;
use Kokonotsuba\routers\routes\moduleloadedRoute;
use Kokonotsuba\routers\routes\moduleRoute;
use Kokonotsuba\routers\routes\overboardRoute;
use Kokonotsuba\routers\routes\rebuildRoute;
use Kokonotsuba\routers\routes\registRoute;
use Kokonotsuba\routers\routes\staffAccountRoute;
use Kokonotsuba\routers\routes\statusRoute;
use Kokonotsuba\routers\routes\usrdelRoute;

/**
 * Every koko.php mode. The empty mode is the board index or thread view; a mode missing here is a 404.
 */
final class routeTable {
	/**
	 * @return array<string, routeEntry>
	 */
	public static function routes(): array {
		return [
			'' => new routeEntry(fn(appContainer $c) => new defaultRoute(
				$c->get('config'),
				$c->get('board'),
				$c->get('threadRepository'),
				$c->get('postRepository'),
				$c->get('postRedirectService'),
				$c->get('postRenderingPolicy'),
				$c->get('deletedPostsService'),
				$c->get('staffAccountFromSession'),
				$c->get('request')
			)),

			'regist' => new routeEntry(fn(appContainer $c) => new registRoute(
				$c->get('board'),
				$c->get('config'),
				$c->get('postValidator'),
				$c->get('staffAccountFromSession'),
				$c->get('transactionManager'),
				$c->get('moduleEngine'),
				$c->get('actionLoggerService'),
				$c->get('cookieService'),
				$c->get('postRepository'),
				$c->get('postService'),
				$c->get('fileService'),
				$c->get('threadRepository'),
				$c->get('threadService'),
				$c->get('quoteLinkService'),
				$c->get('request'),
				$c->get('banService')
			), profile: 'posting'),

			'status' => new routeEntry(fn(appContainer $c) => new statusRoute(
				$c->get('board'),
				$c->get('config'),
				$c->get('templateEngine'),
				$c->get('moduleEngine'),
				$c->get('threadRepository'),
				$c->get('postRepository')
			)),

			'admin' => new routeEntry(fn(appContainer $c) => new adminRoute(
				$c->get('board'),
				$c->get('adminLoginController'),
				$c->get('adminPageRenderer'),
				$c->get('loginAttemptService'),
				$c->get('request'),
			)),

			'module' => new routeEntry(fn(appContainer $c) => new moduleRoute(
				$c->get('moduleEngine'),
				$c->get('softErrorHandler'),
				$c->get('request')
			)),

			'moduleloaded' => new routeEntry(fn(appContainer $c) => new moduleloadedRoute(
				$c->get('config'),
				$c->get('board'),
				$c->get('moduleEngine'),
				$c->get('staffAccountFromSession'),
			)),

			'account' => new routeEntry(fn(appContainer $c) => new accountRoute(
				$c->get('config'),
				$c->get('staffAccountFromSession'),
				$c->get('softErrorHandler'),
				$c->get('accountRepository'),
				$c->get('adminTemplateEngine'),
				$c->get('adminPageRenderer'),
				$c->get('request')
			)),

			'viewStaffAccount' => new routeEntry(fn(appContainer $c) => new staffAccountRoute(
				$c->get('config'),
				$c->get('softErrorHandler'),
				$c->get('accountRepository'),
				$c->get('adminTemplateEngine'),
				$c->get('adminPageRenderer'),
				$c->get('request')
			)),

			'boards' => new routeEntry(fn(appContainer $c) => new boardsRoute(
				$c->get('config'),
				$c->get('staffAccountFromSession'),
				$c->get('softErrorHandler'),
				$c->get('adminTemplateEngine'),
				$c->get('adminPageRenderer'),
				$c->get('boardService'),
				$c->get('board'),
				$c->get('request'),
				$c->get('configService')
			)),

			'globalConfig' => new routeEntry(fn(appContainer $c) => new globalConfigRoute(
				$c->get('config'),
				$c->get('softErrorHandler'),
				$c->get('adminTemplateEngine'),
				$c->get('adminPageRenderer'),
				$c->get('boardService'),
				$c->get('request'),
				$c->get('configService')
			), csrf: csrfRule::ON_POST),

			'overboard' => new routeEntry(fn(appContainer $c) => new overboardRoute(
				$c->get('config'),
				$c->get('visibleBoards'),
				$c->get('boardRepository'),
				$c->get('board'),
				$c->get('overboard'),
				$c->get('cookieService'),
				$c->get('request')
			)),

			'handleAccountAction' => new routeEntry(fn(appContainer $c) => new handleAccountActionRoute(
				$c->get('config'),
				$c->get('board'),
				$c->get('accountService'),
				$c->get('actionLoggerService'),
				$c->get('softErrorHandler'),
				$c->get('staffAccountFromSession'),
				$c->get('request')
			), csrf: csrfRule::ON_POST),

			'handleBoardRequests' => new routeEntry(fn(appContainer $c) => new handleBoardRequestsRoute(
				$c->get('databaseConnection'),
				$c->get('config'),
				$c->get('softErrorHandler'),
				$c->get('boardService'),
				$c->get('boardPathService'),
				$c->get('transactionManager'),
				$c->get('postRepository'),
				$c->get('threadRepository'),
				$c->get('fileService'),
				$c->get('quoteLinkRepository'),
				$c->get('request'),
				$c->get('configService')
			), csrf: csrfRule::POST_ONLY),

			'usrdel' => new routeEntry(fn(appContainer $c) => new usrdelRoute(
				$c->get('config'),
				$c->get('actionLoggerService'),
				$c->get('postService'),
				$c->get('deletedPostsService'),
				$c->get('softErrorHandler'),
				$c->get('cookieService'),
				$c->get('postPolicy'),
				$c->get('currentUserId'),
				$c->get('request')
			)),

			'rebuild' => new routeEntry(fn(appContainer $c) => new rebuildRoute(
				$c->get('board'),
				$c->get('softErrorHandler'),
				$c->get('actionLoggerService'),
			), csrf: csrfRule::POST_ONLY, profile: 'rebuild'),

			'actionLog' => new routeEntry(fn(appContainer $c) => new actionLogRoute(
				$c->get('board'),
				$c->get('config'),
				$c->get('actionLoggerService'),
				$c->get('softErrorHandler'),
				$c->get('adminPageRenderer'),
				$c->get('boardList'),
				$c->get('postDateFormatter'),
				$c->get('request'),
				$c->get('postRepository'),
			)),

			'managePosts' => new routeEntry(fn(appContainer $c) => new managePostsRoute(
				$c->get('board'),
				$c->get('config'),
				$c->get('moduleEngine'),
				$c->get('staffAccountFromSession'),
				$c->get('postRepository'),
				$c->get('postService'),
				$c->get('softErrorHandler'),
				$c->get('actionLoggerService'),
				$c->get('adminPageRenderer'),
				$c->get('boardList'),
				$c->get('deletedPostsService'),
				$c->get('postRenderingPolicy'),
				$c->get('currentUserId'),
				$c->get('request')
			), csrf: csrfRule::ON_POST),

			'api' => new routeEntry(fn(appContainer $c) => new jsonApiRoute(
				[
					'boardApi' => $c->get('boardApi'),
					'threadApi' => $c->get('threadApi'),
				],
				$c->get('request')
			)),
		];
	}
}
