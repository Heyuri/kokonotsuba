<?php

namespace Kokonotsuba\background;

use Kokonotsuba\account\staffAccountFromSession;
use Kokonotsuba\containers\appContainer;
use Kokonotsuba\cookie\cookieService;
use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\database\transactionManager;
use Kokonotsuba\policy\postPolicy;
use Kokonotsuba\policy\postRenderingPolicy;
use Kokonotsuba\request\request;

/**
 * The container a background task renders boards from: built the way a request builds it, with
 * no HTTP session, so everything it draws is what an anonymous reader gets.
 *
 * Defines GLOBAL_BOARD_ARRAY, so it is booted once per process.
 */
final class backgroundBoardContext {
	public static function boot(): appContainer {
		// ── Database ─────────────────────────────────────────────────────
		$databaseConnection = databaseConnection::getInstance();
		$dbSettings         = getDatabaseSettings();
		$tableNames         = getTableNames();
		$transactionManager = new transactionManager($databaseConnection);

		// ── Request and auth stubs (no HTTP session in CLI) ───────────────
		$request                 = new request();
		$cookieService           = new cookieService([]);
		$staffAccountFromSession = new staffAccountFromSession();
		$currentUserId           = $staffAccountFromSession->getUID();
		$globalConfig            = getGlobalConfig();

		$postPolicy = new postPolicy(
			$globalConfig['AuthLevels'],
			$staffAccountFromSession->getRoleLevel(),
			$currentUserId
		);
		$postRenderingPolicy = new postRenderingPolicy(
			$globalConfig['AuthLevels'],
			$staffAccountFromSession->getRoleLevel(),
			$currentUserId,
			$cookieService
		);

		// ── Container ─────────────────────────────────────────────────────
		$container = new appContainer();
		$container->set('request',                 $request);
		$container->set('cookieService',           $cookieService);
		$container->set('staffAccountFromSession', $staffAccountFromSession);
		$container->set('currentUserId',           $currentUserId);
		$container->set('postPolicy',              $postPolicy);
		$container->set('postRenderingPolicy',     $postRenderingPolicy);
		$container->set('globalConfig',            $globalConfig);
		$container->set('databaseConnection',      $databaseConnection);
		$container->set('transactionManager',      $transactionManager);
		$container->set('dbSettings',              $dbSettings);
		$container->set('tableNames',              $tableNames);

		// ── Repositories, then the board layer ───────────────────────────
		// Both register their services in $container; the board layer also
		// defines GLOBAL_BOARD_ARRAY.
		require getBackendDir() . 'bootstrap/repositories.php';
		require getBackendDir() . 'bootstrap/board.php';

		return $container;
	}
}
