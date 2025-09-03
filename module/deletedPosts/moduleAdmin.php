<?php

namespace Kokonotsuba\Modules\deletedPosts;

use BoardException;
use boardRebuilder;
use Kokonotsuba\ModuleClasses\abstractModuleAdmin;
use Kokonotsuba\Root\Constants\userRole;
use RuntimeException;
use staffAccountFromSession;

class moduleAdmin extends abstractModuleAdmin {
    // property to store the url of the module
    private string $myPage;
    
    // property for the role required to modify all deleted posts
    private userRole $requiredRoleForAll;

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
		$this->requiredRoleForAll = userRole::LEV_MODERATOR;

        // initialize url
		$this->myPage = $this->getModulePageURL([], false);

		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this,
			'LinksAboveBar',
			function(string &$linkHtml) {
				$this->onRenderLinksAboveBar($linkHtml);
			}
		);
	} 

    private function onRenderLinksAboveBar(string &$linkHtml): void {
        // modify the "links above bar" html to have a [Deleted Posts] button
		$linkHtml .= '<li class="adminNavLink"><a title="Manage posts that have been deleted" href="' . $this->myPage . '">Manage deleted posts</a></li>';
    }

	private function handleModPageRequests(int $accountId, userRole $roleLevel): void {
		$deletedPostIdList = $_POST['deletedPostIdList'] ?? null;
		$deletedPostId = $_POST['deletedPostId'] ?? null;
		$purgeAll = $_POST['purgeAll'] ?? null;

		// handle an action for single deleted post
		if(isset($deletedPostId)) {
			// make sure the user is a high enough role level if the post wasn't deleted by them
			// if not, throw excepton
			$this->authenticateDeletedPost($deletedPostId, $roleLevel, $accountId);

			$this->handleAction($deletedPostId, $accountId);
		}

		// handle an action for checkbox selected deleted posts
		else if(!empty($deletedPostIdList)) {
			// remove IDs for deleted posts the user isn't authorized to delete/restore from the array
			// doesn't throw an exception
			$this->authenticateList($deletedPostIdList, $roleLevel, $accountId);

			$this->handleActionFromList($deletedPostIdList);
		}

		// purge all posts, either all of them or ones deleted  by the user
		else if(isset($purgeAll)) {
			$this->handlePurgeAll($accountId, $roleLevel);
		}

		// invalid action from request - it didn't fit any of the above criteria
		else {
			throw new BoardException("Invalid action");
		}
	}

	private function authenticateDeletedPost(int $deletedPostId, userRole $roleLevel, int $accountId): void {
		// don't loop if the user has the required permission to restore/purge any post regardless of their role
		if($roleLevel->isAtLeast($this->requiredRoleForAll)) {
			return;
		}

		// check the database if the user is the one who deleted the post
		$isAuthenticated = $this->moduleContext->deletedPostsService->authenticateDeletedPost($deletedPostId, $accountId);

		// throw an exception if the user isn't authenticated to deleted/restored it
		if(!$isAuthenticated) {
			throw new BoardException("You are not authenticated to modify this deleted post!");
		}
	}

	private function authenticateList(array &$deletedPostIdList, userRole $roleLevel, int $accountId): void {
		// don't loop if the user has the required permission to restore/purge any post regardless of their role
		if($roleLevel->isAtLeast($this->requiredRoleForAll)) {
			return;
		}

		// query the table and only return deleted post ids that 
		$deletedPostIdList = $this->moduleContext->deletedPostsService->authenticateDeletedPostList($deletedPostIdList, $accountId);

		// if aren't any results in it - then throw an exception
		if(empty($deletedPostIdList)) {
			throw new BoardException("You are not authenticated to modify any of the selected posts!");
		}
	}

	private function handleAction(int $deletedPostId, int $accountId): void {
		// If its a restore action, handle the restoring of the post
		if(isset($_POST['restore'])) {
			$this->moduleContext->deletedPostsService->restorePost($deletedPostId, $accountId);
		}

		// if it's a purge action, handle the purging and associated actions 
		else if (isset($_POST['purge'])) {
			$this->moduleContext->deletedPostsService->purgePost($deletedPostId, $accountId);
		}
	}

	private function handleActionFromList(array $deletedPostIdList): void {
		// restore post from list of IDs
		if(isset($_POST['purgeList'])) {
			$this->moduleContext->deletedPostsService->purgePostsFromList($deletedPostIdList);
		}
	}

	private function handlePurgeAll(int $accountId, userRole $roleLevel): void {
		// if the user isn't a moderator, only purge all posts that they deleted
		if($roleLevel->isLessThan($this->requiredRoleForAll)) {
			$this->moduleContext->deletedPostsService->purgeDeletedPostsByAccountId($accountId);
		}

		// if the user is at least a mod, then go ahead and purge all deleted posts
		else if ($roleLevel->isAtLeast($this->requiredRoleForAll)) {
			$this->moduleContext->deletedPostsService->purgeAllDeletedPosts();
		}
	}

	private function drawModPage(int $accountId, userRole $roleLevel): void {
		// get page number from GET
		$page = $_GET['page'] ?? 0;

		// get paginated results
		// If the user is at least a moderator, get all deleted posts
		if($roleLevel->isAtLeast($this->requiredRoleForAll)) {
			// get the deleted posts from the database
			$deletedPosts = [];//$this->deletedPostsService->getDeletedPosts($page, $this->board->getConfigValue('PAGE_DEF', 40));

			// get the total amount of deleted posts
			$deletedPostsCount = 1; //$this->deletedPostsService->getTotalAmount();
		} else {
			// only get posts deleted by the staff
			$deletedPosts = $this->moduleContext->deletedPostsService->getDeletedPostsByAccount($accountId, $page, $this->moduleContext->board->getConfigValue('PAGE_DEF', 40));

			// get the total amount
			$deletedPostsCount = $this->moduleContext->deletedPostsService->getTotalAmountFromAccountId($accountId);
		}

		// finalize html output
		$this->handleHtmlOutput($deletedPosts, $deletedPostsCount);
	}

	private function handleHtmlOutput(array $deletedPosts, int $deletedPostsCount): void {
		// flag for if there's no posts.
		$areNoPosts = empty($deletedPosts);
		
		// keep track of the template values for deleted post entries
		$deletedPostListValues = [];

		// don't bother trying to parse if there's no posts
		if(!$areNoPosts) {
			// get deleted posts html
			$deletedPostListValues = $this->renderDeletedPosts($deletedPosts);
		}
		
		// bind deted posts list html to placeholder
		$deletedPostsPageHtml = $this->moduleContext->adminPageRenderer->ParseBlock('DELETED_POSTS_MOD_PAGE', [
			'{$DELETED_POSTS}' => $deletedPostListValues,
			'{$ARE_NO_POSTS}' => $areNoPosts
		]);

		// pager
		$entriesPerPage = $this->moduleContext->board->getConfigValue('PAGE_DEF');
		$totalEntries = $deletedPostsCount;
		$pager = drawPager($entriesPerPage, $totalEntries, $this->myPage);
		
		// assign placeholder values
		$pageContent = [
			'{$URL}' => htmlspecialchars($this->myPage),
			'{$PAGE_CONTENT}' => $deletedPostsPageHtml, 
			'{$PAGER}' => $pager,
		];

		// parse the page
		$pageHtml = $this->moduleContext->adminPageRenderer->ParsePage('GLOBAL_ADMIN_PAGE_CONTENT', $pageContent, true);

		// echo output to browser
		echo $pageHtml;	
	}

	private function renderDeletedPosts(array $deletedPostEntries): array {
		// array to store the template values
		$deletedPostsTemplateValues = [];
		
		// loop through the deleted posts data and generate placeholder-to-value elements
		foreach($deletedPostEntries as $deletedEntry) {
			// get the post data associated with the deleted post
			$postData = $deletedEntry->getPostData();

			// post name
			$name = $postData['name'];

			// post tripcode
			$tripcode = $postData['tripcode'];

			// post secure tripcode
			$secure_tripcode = $postData['secure_tripcode'];

			// post capcode
			$capcode = $postData['capcode'];

			// post email
			$email = $postData['email'];

			// post number
			$no = $postData['no'];

			// post subject
			$subject = $postData['sub'];

			// post board uid 
			$boardUID = $postData['boardUID'];

			// post comment
			$comment = $postData['com'];

			// generate the post's name html
			// don't bother displaying sage
			$nameHtml = generatePostNameHtml(
				$this->moduleContext->board->getConfigValue('staffCapcodes'),
				$this->moduleContext->board->getConfigValue('CAPCODES'),
				$name,
				$tripcode,
				$secure_tripcode,
				$capcode,
				$email,
				false
			);

			// id of the deleted post row
			$id = $deletedEntry->getId();

			// url to restore the post
			$restoreUrl = $this->generateRestoreUrl($id, $this->myPage);

			// url to purge the post
			$purgeUrl = $this->generatePurgeUrl($id, $this->myPage);

			$deletedPostsTemplateValues[] = [
				'{$NO}' => htmlspecialchars($no),
				'{$ID}' => htmlspecialchars($id),
				'{$SUBJECT}' => $subject,
				'{$NAME_HTML}' => $nameHtml,
				'{$COMMENT}' => $comment,
				'{$BOARD_UID}' => htmlspecialchars($boardUID),
				'{$PURGE_URL}' => htmlspecialchars($purgeUrl),
				'{$RESTORE_URL}' => htmlspecialchars($restoreUrl)
			];
		}

		// now, return the template values
		return $deletedPostsTemplateValues;
	}

	private function generatePurgeUrl(int $id, string $baseUrl): string {
		// generate the purge url
		$purgeUrl = $this->generateActionUrl($id, $baseUrl, 'purge');

		// return generated purge url
		return $purgeUrl;
	}

	private function generateRestoreUrl(int $id, string $baseUrl): string {
		// generate the restore url
		$restoreUrl = $this->generateActionUrl($id, $baseUrl, 'restore');

		// return generated restore url
		return $restoreUrl;
	}

	private function generateActionUrl(int $id, string $baseUrl, string $action): string {
		// if the action (which is used as an array key) is blank then something has gone very wrong 
		if(empty($action)) {
			throw new RuntimeException("Invalid action when generating action URLs");
		}
		
		// generate the url parameters
		$purgeParams = http_build_query([
			'deletedPostId' => $id,
			$action => "on"
		]);

		// get the base url
		$actionUrl = $baseUrl . $purgeParams;

		// return generated url
		return $actionUrl;	
	}

    public function ModulePage(): void {
        // Account session values
        $staffAccountFromSession = new staffAccountFromSession;

		// get staff id and role level
		$accountId = $staffAccountFromSession->getUID();
		$roleLevel = $staffAccountFromSession->getRoleLevel();

		// request vs draw
		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			$this->handleModPageRequests($accountId, $roleLevel);
		} else {
			$this->drawModPage($accountId, $roleLevel);
		}
	}

}
