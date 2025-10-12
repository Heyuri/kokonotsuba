<?php

namespace Kokonotsuba\Modules\deletedPosts;

use BoardException;
use deletedPostsService;
use Kokonotsuba\Root\Constants\userRole;

class deletedPostUtility {
	public function __construct(
		private moduleAdmin $moduleAdmin,
		private deletedPostsService $deletedPostsService,
		private userRole $requiredRoleActionForModAll
	) {}

	public function isPostDeleted(array $post): bool {
		// has a value of 1 if the post is deleted
		$openFlag = $post['open_flag'] ?? 0;

		// return true if its value is 1
		if((int)$openFlag === 1) {
			return true;
		} 
		// not deleted / restored
		else {
			return false;
		}
	}

	public function isModulePage(): bool {
		// get current module
		$loadedModule = $_REQUEST['load'] ?? '';

		// return true if its the module
		if($loadedModule === 'deletedPosts') {
			return true;
		}
		// return false otherwise
		else {
			return false;
		}

	}

	public function adminPostViewModuleButton(array $post): string {
		// whether we're viewing from the module page
		$isModulePage = $this->isModulePage();

		// don't display it if we're in the module view - 
		// coz we can already see all the infos or we're already on the page it'd take the user to
		if($isModulePage) {
			return '';
		}

		// is a reply of a deleted thread
		$byProxy = $post['by_proxy'] ?? 0;

		// also don't display it if the post is only deleted by proxy
		// replies of deleted threads aren't meant to be view or changed individually
		// in other words, they're bound to whatever action happens to the OP post
		// e.g, OP purged = reply also purged
		if($byProxy) {
			return '';
		}

		// get the deleted post id
		$deletedPostId = $post['deleted_post_id'];
		
		// url parameters
		$urlParameters = [
			'pageName' => 'viewMore',
			'deletedPostId' => $deletedPostId,
		];

		// get url
		$modulePageUrl = $this->moduleAdmin->getModulePageURL($urlParameters,
			false,
			true
		);

		// render the html
		$buttonUrl = '<span class="adminFunctions adminViewDeletedPostFunction">[<a href="' . htmlspecialchars($modulePageUrl) . '" title="View deleted post">VD</a>]</span> ';

		// return string
		return $buttonUrl;
	}

	public function generateDeletedPostViewUrl(int $deletedPostId): string {
		// generate module url for page
		$url = $this->moduleAdmin->getModulePageURL(
			[
				'deletedPostId' => $deletedPostId,
				'pageName' => 'viewMore'
			],
			false
		);

		// return generated url
		return $url;
	}

    public function authenticateDeletedPost(int $deletedPostId, userRole $roleLevel, int $accountId): void {
		// don't loop if the user has the required permission to restore/purge any post regardless of their role
		if($roleLevel->isAtLeast($this->requiredRoleActionForModAll)) {
			return;
		}

		// check the database if the user is the one who deleted the post
		$isAuthenticated = $this->deletedPostsService->authenticateDeletedPost($deletedPostId, $accountId);

		// throw an exception if the user isn't authenticated to deleted/restored it
		if(!$isAuthenticated) {
			throw new BoardException("You are not authenticated to modify or view this deleted post!");
		}
	}
}