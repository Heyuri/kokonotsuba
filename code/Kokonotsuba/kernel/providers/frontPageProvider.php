<?php

namespace Kokonotsuba\kernel\providers;

use Kokonotsuba\containers\appContainer;
use Kokonotsuba\error\softErrorHandler;
use Kokonotsuba\front_pages\frontAdminRenderer;
use Kokonotsuba\front_pages\frontLogoService;
use Kokonotsuba\front_pages\frontPageLinks;
use Kokonotsuba\front_pages\frontPageRenderer;
use Kokonotsuba\front_pages\frontBoardList;
use Kokonotsuba\front_pages\frontPostStats;
use Kokonotsuba\module_classes\frontModuleEngine;
use Kokonotsuba\module_classes\moduleStylesheets;
use Kokonotsuba\post\helper\postDateFormatter;
use Kokonotsuba\template\templateEngine;


/**
 * The front page layer: its renderer, module engine and error page, drawn from the site config.
 * Blotter, side nav and staff nav come from the modules' moduleFront through the front hooks.
 */
final class frontPageProvider {
	public static function register(appContainer $container): void {
		$siteConfig = $container->get('siteConfig');
		$site = (array)($siteConfig['SITE'] ?? []);
		$staffAccount = $container->get('staffAccountFromSession');
		$frontPagePolicy = $container->get('frontPagePolicy');

		$templateEngine = new templateEngine(getBackendDir() . 'templates/frontpage', [
			'config'    => $siteConfig,
			'boardData' => ['title' => (string)($site['NAME'] ?? ''), 'subtitle' => ''],
		]);

		moduleStylesheets::add((string)($siteConfig['STATIC_URL'] ?? '') . 'css/frontpage.css');

		// links to itself, not HOME: HOME is where boards send people and may live elsewhere
		$links = new frontPageLinks(self::selfUrl($container->get('request')->getScriptName()));

		$moduleEngine = new frontModuleEngine($container, $templateEngine, $links, $siteConfig, $staffAccount);

		// the global TIME_ZONE, which boards inherit unless they set their own
		$renderer = new frontPageRenderer(
			$templateEngine,
			$siteConfig,
			$site,
			$links,
			$container->get('frontPageService'),
			$moduleEngine,
			new postDateFormatter((string)($siteConfig['TIME_ZONE'] ?? '0')),
			$frontPagePolicy,
			self::postStats($container, $site),
			self::boardList($container, $site),
		);

		$softErrorHandler = new softErrorHandler(
			$renderer->renderHead('Error!', $staffAccount->getRoleLevel()->isStaff(), withInfo: false),
			$renderer->renderFooter(),
			(string)($siteConfig['HOME'] ?? ''),
			$templateEngine,
			$staffAccount,
			$container->get('request'),
		);

		$container->set('frontPageRenderer', $renderer);
		$container->set('frontModuleEngine', $moduleEngine);
		$container->set('frontPageLinks', $links);
		$container->set('frontAdminRenderer', new frontAdminRenderer($templateEngine, $links));
		$container->set('frontLogoService', new frontLogoService(
			$container->get('configService'),
			$container->get('actionLoggerService'),
			$container->get('frontPageCache'),
			(string)($siteConfig['STATIC_PATH'] ?? ''),
			(string)($siteConfig['STATIC_URL'] ?? ''),
		));
		$container->set('softErrorHandler', $softErrorHandler);
	}

	/** The front page's own path; a trailing index.php is dropped so links read /?page=2. */
	private static function selfUrl(string $scriptName): string {
		if ($scriptName === '') {
			return './';
		}

		return basename($scriptName) === 'index.php' ? substr($scriptName, 0, -strlen('index.php')) : $scriptName;
	}

	/** Null when SITE.SHOW_BOARD_LIST is off; counts nothing when SITE.BOARD_LIST_STATS is off. */
	private static function boardList(appContainer $container, array $site): ?frontBoardList {
		if (empty($site['SHOW_BOARD_LIST'])) {
			return null;
		}

		return new frontBoardList(
			static fn(): array => $container->get('boardService')->getAllListedBoards() ?? [],
			empty($site['BOARD_LIST_STATS']) ? null : $container->get('postRepository'),
			getBackendGlobalDir() . 'front-page-cache/board-list.json',
		);
	}

	/** Null when SITE.SHOW_POST_STATS is off, so nothing is drawn or counted. */
	private static function postStats(appContainer $container, array $site): ?frontPostStats {
		if (empty($site['SHOW_POST_STATS'])) {
			return null;
		}

		return new frontPostStats(
			$container->get('postRepository'),
			static fn(): array => $container->get('boardRepository')->getAllListedBoardUIDs(),
			getBackendGlobalDir() . 'front-page-cache/post-stats.json',
		);
	}
}
