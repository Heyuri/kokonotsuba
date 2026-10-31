<?php

namespace Kokonotsuba\kernel\providers;

use Kokonotsuba\containers\appContainer;
use Kokonotsuba\front_pages\frontPageActionType;
use Kokonotsuba\front_pages\frontPageCache;
use Kokonotsuba\front_pages\frontPageLinks;
use Kokonotsuba\front_pages\frontPageRepository;
use Kokonotsuba\front_pages\frontPageService;
use Kokonotsuba\news\newsActionType;
use Kokonotsuba\news\newsRepository;
use Kokonotsuba\news\newsService;
use Kokonotsuba\policy\frontPagePolicy;

use const Kokonotsuba\GLOBAL_BOARD_UID;

/**
 * Front page news and pages. Registered for every request, not just the front page, because the
 * action log (served from a board) has to know their types and where their references point.
 */
final class frontContentProvider {
	public static function register(appContainer $container): void {
		$databaseConnection = $container->get('databaseConnection');
		$tableNames = $container->get('tableNames');
		$actionLoggerService = $container->get('actionLoggerService');
		$staffAccountFromSession = $container->get('staffAccountFromSession');

		$newsService = new newsService(
			new newsRepository($databaseConnection, $tableNames['NEWS_TABLE'], $tableNames['ACCOUNT_TABLE']),
			$actionLoggerService
		);
		$frontPageService = new frontPageService(
			new frontPageRepository($databaseConnection, $tableNames['FRONT_PAGES_TABLE']),
			$actionLoggerService
		);

		// a write from any entry point forgets the rendered front page
		$frontPageCache = frontPageCache::forSite();
		$newsService->onChange($frontPageCache->forgetAll(...));
		$frontPageService->onChange($frontPageCache->forgetAll(...));

		$frontPagePolicy = new frontPagePolicy(
			$container->get('globalConfig')['AuthLevels'],
			$staffAccountFromSession->getRoleLevel(),
			$container->get('currentUserId')
		);

		// Resolved only when a log line with a reference is drawn, so HOME is read then.
		$configService = $container->get('configService');
		$links = static fn(): frontPageLinks => new frontPageLinks((string)$configService->getEffectiveConfig(GLOBAL_BOARD_UID)['HOME']);

		$actionLoggerService->registerTypes(newsActionType::class);
		$actionLoggerService->registerTypes(frontPageActionType::class);
		$actionLoggerService->registerReference('news', static fn(string $id): string => $links()->news((int)$id));
		$actionLoggerService->registerReference('page', static fn(string $slug): string => $links()->page($slug));

		$container->set('newsService', $newsService);
		$container->set('frontPageService', $frontPageService);
		$container->set('frontPagePolicy', $frontPagePolicy);
		$container->set('frontPageCache', $frontPageCache);
	}
}
