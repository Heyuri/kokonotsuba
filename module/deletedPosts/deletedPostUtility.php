<?php

namespace Kokonotsuba\Modules\deletedPosts;

use Kokonotsuba\error\BoardException;
use Kokonotsuba\post\deletion\deletedPostsService;
use Kokonotsuba\post\Post;
use Kokonotsuba\request\request;
use Kokonotsuba\userRole;

use function Kokonotsuba\libraries\generateModerateButton;

class deletedPostUtility {
	public function __construct(
		private moduleAdmin $moduleAdmin,
		private deletedPostsService $deletedPostsService,
		private userRole $requiredRoleActionForModAll,
		private readonly request $request
	) {}

	public function isPostDeleted(Post $post): bool {
		// has a value of 1 if the post is deleted
		$openFlag = $post->getOpenFlag() ?? 0;

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
		$loadedModule = $this->request->getParameter('load', default: '');

		// return true if its the module
		if($loadedModule === 'deletedPosts') {
			return true;
		}
		// return false otherwise
		else {
			return false;
		}

	}

	public function adminPostViewModuleButton(Post $post, bool $noScript = false): string {
		// whether to render it
		if(!$this->canRenderButton($post)) {
			return '';
		}

		// get the deleted post id
		$deletedPostId = $post->getDeletedPostId();

		// get url
		$modulePageUrl = $this->generateViewDeletedPostUrl($deletedPostId);

		// generate the button html
		$buttonHtml = generateModerateButton(
			$modulePageUrl,
			'VD',
			'View deleted post',
			'adminViewDeletedPostFunction',
			$noScript
		);

		// return string
		return $buttonHtml;
	}

	public function canRenderButton(Post $post): bool {
		// isDeleted() is post-level only: a file-only deletion belongs to the attachment menu,
		// and its entry is reached from there
		if(!$post->isDeleted()) {
			return false;
		}

		// whether we're viewing from the module page
		$isModulePage = $this->isModulePage();

		// don't display it if we're in the module view - 
		// coz we can already see all the infos or we're already on the page it'd take the user to
		if($isModulePage) {
			return false;
		}

		// is a reply of a deleted thread
		$byProxy = $post->isByProxy();

		// also don't display it if the post is only deleted by proxy
		// replies of deleted threads aren't meant to be view or changed individually
		// in other words, they're bound to whatever action happens to the OP post
		// e.g, OP purged = reply also purged
		if($byProxy) {
			return false;
		}

		// all korrect!
		// can be rendered
		return true;
	}

	public function generateViewDeletedPostUrl(int $deletedPostId): string {
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

		// return url
		return $modulePageUrl;
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

	/**
	 * Narrow a selection to the records this account may act on.
	 *
	 * Staff who can moderate anyone's deletions keep the whole list; everyone else keeps their own,
	 * resolved in one query rather than one per record.
	 *
	 * @param int[] $deletedPostIds Deletion record IDs.
	 * @return int[] The records the account may restore or purge.
	 */
	public function authenticateDeletedPosts(array $deletedPostIds, userRole $roleLevel, int $accountId): array {
		if($roleLevel->isAtLeast($this->requiredRoleActionForModAll)) {
			return $deletedPostIds;
		}

		$authenticated = $this->deletedPostsService->filterDeletedPostIdsByAccountId($deletedPostIds, $accountId);

		if(!$authenticated) {
			throw new BoardException("You are not authenticated to modify or view this deleted post!");
		}

		return $authenticated;
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