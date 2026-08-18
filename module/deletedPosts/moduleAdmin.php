<?php

namespace Kokonotsuba\Modules\deletedPosts;

use Kokonotsuba\error\BoardException;
use Kokonotsuba\module_classes\abstractModuleAdmin;
use Kokonotsuba\module_classes\traits\listeners\MassModerateListenerTrait;
use Kokonotsuba\userRole;
use Kokonotsuba\account\staffAccountFromSession;

use function Puchiko\request\redirect;
use function Puchiko\json\renderJsonPage;
use function Kokonotsuba\libraries\_T;
use function Kokonotsuba\libraries\requirePostWithCsrf;

// require helper classes
require __DIR__ . '/deletedPostUtility.php';
require __DIR__ . '/deletedPostActionHandler.php';
require __DIR__ . '/deletedPostRenderer.php';
require __DIR__ . '/deletedPostUIHooks.php';

class moduleAdmin extends abstractModuleAdmin {
	use MassModerateListenerTrait;

	// property to store the url of the module
	private string $modulePageUrl;

	// property for the role required to modify all deleted posts
	private userRole $requiredRoleActionForModAll;

	// property for the role required to delete restored posts
	private userRole $requiredRoleForDeleteRestoredRecord;

	// class used for handling dp requests
	private deletedPostActionHandler $deletedPostActionHandler;

	// shared checks on a deletion record
	private deletedPostUtility $deletedPostUtility;

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
		$this->modulePageUrl = $this->getModulePageURL([], false, true);

		// generate the restored index url
		$restoredIndexUrl = $this->getModulePageURL(['pageName' => 'restoredIndex'], false);

		// init the module template engine
		$moduleTemplateEngine = $this->initModuleTemplateEngine('modules.deletedPosts.DELETED_POSTS_TEMPLATE', 'kokoimg');

		// init utility class
		$this->deletedPostUtility = $deletedPostUtility = new deletedPostUtility(
			$this, 
			$this->moduleContext->deletedPostsService,
			$this->requiredRoleActionForModAll,
			$this->moduleContext->request);

		// init action handler
		$this->deletedPostActionHandler = new deletedPostActionHandler(
			$this->requiredRoleActionForModAll, 
			$this->moduleContext->deletedPostsService, 
			$deletedPostUtility,
			$restoredIndexUrl,
			$this->moduleContext->request
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
			$this->moduleContext->cookieService,
			$this->modulePageUrl,
			$restoredIndexUrl,
			$this->requiredRoleForDeleteRestoredRecord,
			$this->moduleContext->postDateFormatter,
			$this->moduleContext->request
		);

		// the [View entry] window's body, rendered here so its markup stays in a template
		$entryWindowHtml = $this->generateTemplate(
			'dpEntryWindowTemplate',
			$moduleTemplateEngine->ParseBlock('DELETED_POST_WINDOW', [])
		);

		// init ui hooks class
		$deletedPostUIHooks = new deletedPostUIHooks(
			$this->includeScript(...),
			$this->buildWidgetEntry(...),
			$deletedPostUtility,
			$this->modulePageUrl,
			$this->requiredRoleActionForModAll,
			$this->getMenuPolicy(),
			$entryWindowHtml
		);

		// run hooks
		$deletedPostUIHooks->runHooks(
			$this->moduleContext->moduleEngine,
			$this->getRequiredRole()
		);

		// bulk restore for anyone who may see deleted posts, purge only for the higher role
		$this->listenMassModerateTools('onMassModerateRestoreTool', 80);
		$this->listenMassModerateTools('onMassModeratePurgeTool', 70, $this->requiredRoleActionForModAll);
	}

	private function onMassModerateRestoreTool(array &$tools): void {
		$tools[] = $this->buildMassTool('restore', 'Restore', [
			'url' => $this->modulePageUrl,
			'group' => 'Deleted posts',
			'effect' => 'restore',
			'requires' => 'deleted',
			'priority' => 80,
		]);
	}

	private function onMassModeratePurgeTool(array &$tools): void {
		$tools[] = $this->buildMassTool('purge', 'Purge', [
			'url' => $this->modulePageUrl,
			'group' => 'Deleted posts',
			'effect' => 'purge',
			'requires' => 'deleted',
			'confirm' => _T('mass_moderate_confirm'),
			'priority' => 70,
		]);
	}

	private function pruneDeletedPosts(): void {
		// get time limit config variable (hours)
		// default to 1 week
		$timeLimit = $this->getModuleConfig('PRUNE_TIME', 336);

		// prune the expired deleted posts in the system
		$this->moduleContext->deletedPostsService->pruneExpiredPosts($timeLimit);
	}

	private function handleDpToggle(): void {
		$current = $this->moduleContext->cookieService->get('viewDeletedPosts', '1') === '1';
		$newValue = $current ? '0' : '1';

		$this->moduleContext->cookieService->set(
			'viewDeletedPosts',
			$newValue,
			time() + (86400 * 30),
			'/'
		);
	}

	/**
	 * Read one entry for the window the [View entry] widget opens. Gated exactly like acting on it:
	 * staff who may not touch someone else's deletion may not read it either.
	 *
	 * @return array<string, mixed>
	 */
	private function fetchEntryData(int $accountId, userRole $roleLevel): array {
		$deletedPostId = (int)$this->moduleContext->request->getParameter('deletedPostId', 'GET');

		if($deletedPostId <= 0) {
			throw new BoardException(_T('deleted_post_not_found'));
		}

		$this->deletedPostUtility->authenticateDeletedPost($deletedPostId, $roleLevel, $accountId);

		$deletedPost = $this->moduleContext->deletedPostsService->getDeletedPostRowById($deletedPostId);

		if(!$deletedPost) {
			throw new BoardException(_T('deleted_post_not_found'));
		}

		return ['success' => true, ...$this->deletedPostRenderer->getEntryData($deletedPost, $roleLevel)];
	}

	public function ModulePage(): void {
		// first things first, prune posts from the table that have expired
		$this->pruneDeletedPosts();

		// Account session values
		$staffAccountFromSession = new staffAccountFromSession;

		// get staff id and role level
		$accountId = $staffAccountFromSession->getUID();
		$roleLevel = $staffAccountFromSession->getRoleLevel();

		// handle POST requests
		if ($this->moduleContext->request->isPost()) {
			requirePostWithCsrf($this->moduleContext->request);
			$result = $this->deletedPostActionHandler->handleModPageRequests($accountId, $roleLevel);

			// return JSON for AJAX requests
			if ($this->moduleContext->request->isAjax()) {
				renderJsonPage(['success' => true, ...$result]);
			}

			// redirect for non-JS requests
			$redirectUrl = $result['redirect'] ?? $this->modulePageUrl;
			redirect($redirectUrl);
		} 
		// the [View entry] window asks for the entry's metadata and actions
		else if($this->moduleContext->request->getParameter('pageName', 'GET') === 'entryData') {
			renderJsonPage($this->fetchEntryData($accountId, $roleLevel));
		}
		// handle DP visibilty toggle
		else if($this->moduleContext->request->hasParameter('toggleVisibility', 'GET')) {
			$this->handleDpToggle();

			// redirect back to module index
			redirect($this->modulePageUrl);
		}
		// handle drawing
		else {
			// draw the overview of the deleted posts
			$this->deletedPostRenderer->drawModPage($accountId, $roleLevel);
		}
	}

}
