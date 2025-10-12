<?php

namespace Kokonotsuba\Modules\deletedPosts;

use Kokonotsuba\ModuleClasses\abstractModuleAdmin;
use Kokonotsuba\Root\Constants\userRole;
use staffAccountFromSession;

// require helper classes
require __DIR__ . '/deletedPostUtility.php';
require __DIR__ . '/deletedPostActionHandler.php';
require __DIR__ . '/deletedPostRenderer.php';
require __DIR__ . '/deletedPostUIHooks.php';

class moduleAdmin extends abstractModuleAdmin {
	// property to store the url of the module
	private string $modulePageUrl;

	// property for the role required to modify all deleted posts
	private userRole $requiredRoleActionForModAll;

	// property for the role required to delete restored posts
	private userRole $requiredRoleForDeleteRestoredRecord;

	// class used for handling dp requests
	private deletedPostActionHandler $deletedPostActionHandler;

	// handles
	private deletedPostRenderer $deletedPostRenderer;

	public function getRequiredRole(): userRole {
		return $this->getConfig('AuthLevels.CAN_DELETE_POST');
	}

	public function getName(): string {
		return 'Deleted posts mod page';
	}

	public function getVersion(): string {
		return 'Koko 2025';
	}

	public function initialize(): void {
		// initialize role property
		$this->requiredRoleActionForModAll = $this->getConfig('AuthLevels.CAN_DELETE_ALL', userRole::LEV_MODERATOR);

		// init role property
		$this->requiredRoleForDeleteRestoredRecord = $this->getConfig('AuthLevels.CAN_DELETE_RESTORE_RECORDS', userRole::LEV_ADMIN);

		// initialize url
		$this->modulePageUrl = $this->getModulePageURL([], false);

		// generate the restored index url
		$restoredIndexUrl = $this->getModulePageURL(['pageName' => 'restoredIndex'], false);

		// init the module template engine
		$moduleTemplateEngine = $this->initModuleTemplateEngine('ModuleSettings.DELETED_POSTS_TEMPLATE', 'kokoimg.tpl');

		// init utility class
		$deletedPostUtility = new deletedPostUtility(
			$this, 
			$this->moduleContext->deletedPostsService,
			$this->requiredRoleActionForModAll);

		// init action handler
		$this->deletedPostActionHandler = new deletedPostActionHandler(
			$this, 
			$this->requiredRoleActionForModAll, 
			$this->moduleContext->deletedPostsService, 
			$deletedPostUtility,
			$restoredIndexUrl
		);

		// init rendering class
		$this->deletedPostRenderer = new deletedPostRenderer(
			$this->moduleContext->board,
			$this->moduleContext->board->loadBoardConfig(),
			$this->moduleContext->moduleEngine,
			$moduleTemplateEngine,
			$deletedPostUtility,
			$this->moduleContext->deletedPostsService,
			$this->requiredRoleActionForModAll,
			$this->moduleContext->adminPageRenderer,
			$this->moduleContext->threadService,
			$this->moduleContext->quoteLinkService,
			$this->modulePageUrl,
			$restoredIndexUrl,
			$this->requiredRoleForDeleteRestoredRecord
		);

		// init ui hooks class
		$deletedPostUIHooks = new deletedPostUIHooks(
			$this, 
			$deletedPostUtility, 
			$this->modulePageUrl
		);

		// run hooks
		$deletedPostUIHooks->runHooks(
			$this->moduleContext->moduleEngine, 
			$this->getRequiredRole()
		);
	}

	private function pruneDeletedPosts(): void {
		// get time limit config variable (hours)
		// default to 1 week
		$timeLimit = $this->getConfig('ModuleSettings.PRUNE_TIME', 336);

		// prune the expired deleted posts in the system
		$this->moduleContext->deletedPostsService->pruneExpiredPosts($timeLimit);
	}

	public function ModulePage(): void {
		// first things first, prune posts from the table that have expired
		$this->pruneDeletedPosts();

		// Account session values
		$staffAccountFromSession = new staffAccountFromSession;

		// get staff id and role level
		$accountId = $staffAccountFromSession->getUID();
		$roleLevel = $staffAccountFromSession->getRoleLevel();

		// request vs draw
		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			$this->deletedPostActionHandler->handleModPageRequests($accountId, $roleLevel);

			redirect($this->modulePageUrl);
		} elseif (isset($_GET['json'])) {
			// handle json output (API)
			// to do
		} else {
			// draw the overview of the deleted posts
			$this->deletedPostRenderer->drawModPage($accountId, $roleLevel);
		}
	}

}
