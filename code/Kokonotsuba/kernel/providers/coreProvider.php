<?php

namespace Kokonotsuba\kernel\providers;

use Kokonotsuba\account\staffAccountFromSession;
use Kokonotsuba\background\rebuildBoardsTask;
use Kokonotsuba\containers\appContainer;
use Kokonotsuba\cookie\cookieService;
use Kokonotsuba\kernel\databaseHandles;
use Kokonotsuba\policy\postPolicy;
use Kokonotsuba\policy\postRenderingPolicy;
use Kokonotsuba\request\request;
use Puchiko\background\BackgroundTaskDispatcher;
use Puchiko\background\BackgroundTaskRegistry;

/** Request, cookies, global config, the session's account and policies, and the database. */
final class coreProvider {
	public static function register(appContainer $container, request $request, cookieService $cookieService, databaseHandles $db): void {
		BackgroundTaskDispatcher::setContext(getBackendDir() . 'bootstrap/background.php');
		BackgroundTaskDispatcher::setAppRoot(getBackendDir());

		// Core background tasks. Registered here rather than by a module so that core routes (the board
		// and global config editors) can dispatch a rebuild whether or not the rebuild module is enabled.
		BackgroundTaskRegistry::register('rebuild_boards', rebuildBoardsTask::class);

		$globalConfig = getGlobalConfig();

		$staffAccountFromSession = new staffAccountFromSession;
		$currentUserId = $staffAccountFromSession->getUID();

		$postPolicy = new postPolicy(
			$globalConfig['AuthLevels'],
			$staffAccountFromSession->getRoleLevel(),
			$currentUserId);

		$postRenderingPolicy = new postRenderingPolicy(
			$globalConfig['AuthLevels'],
			$staffAccountFromSession->getRoleLevel(),
			$currentUserId,
			$cookieService);

		$container->set('request', $request);
		$container->set('cookieService', $cookieService);
		$container->set('staffAccountFromSession', $staffAccountFromSession);
		$container->set('currentUserId', $currentUserId);
		$container->set('postPolicy', $postPolicy);
		$container->set('postRenderingPolicy', $postRenderingPolicy);
		$container->set('globalConfig', $globalConfig);

		$container->set('databaseConnection', $db->databaseConnection);
		$container->set('transactionManager', $db->transactionManager);
		$container->set('dbSettings', $db->dbSettings);
		$container->set('tableNames', $db->tableNames);
	}
}
