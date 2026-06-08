<?php

namespace Kokonotsuba\Modules\rebuild;

use Puchiko\background\BackgroundTaskInterface;
use Kokonotsuba\account\staffAccountFromSession;
use Kokonotsuba\containers\appContainer;
use Kokonotsuba\cookie\cookieService;
use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\database\transactionManager;
use Kokonotsuba\policy\postPolicy;
use Kokonotsuba\policy\postRenderingPolicy;
use Kokonotsuba\request\request;

use function Kokonotsuba\libraries\rebuildBoardsByArray;

class rebuildTask implements BackgroundTaskInterface {
	public function handle(array $args): void {
		$boardUIDs = array_map('intval', $args['boardUIDs'] ?? []);
		if (empty($boardUIDs)) {
			return;
		}

		// ── Database ─────────────────────────────────────────────────────
		$databaseConnection = databaseConnection::getInstance();
		$dbSettings         = getDatabaseSettings();
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

		// ── Repositories (also registers services in $container) ──────────
		// Variables created here become local vars: $actionLoggerService,
		// $threadRepository, $threadService, $quoteLinkService, etc.
		require getBackendDir() . 'bootstrap/repositories.php';

		// ── Board layer ───────────────────────────────────────────────────
		// Creates $boardService, defines GLOBAL_BOARD_ARRAY, registers
		// boardPostNumbers, boardPathService, boardRepository in $container.
		require getBackendDir() . 'bootstrap/board.php';

		// ── Rebuild ───────────────────────────────────────────────────────
		$boards = $boardService->getBoardsFromUIDs($boardUIDs);
		rebuildBoardsByArray($boards, false);
	}
}
