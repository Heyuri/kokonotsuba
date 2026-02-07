<?php

use Kokonotsuba\Root\Constants\userRole;

class postRenderingPolicy {
	public function __construct(
		private array $authLevels,
        private userRole $roleLevel
	) {}

	private function canViewDeletedPosts(): bool {
		// Minimum auth role
		$canDeleteAll = $this->authLevels['CAN_DELETE_ALL'];

		// the user's role is the required role or higher - they are allowed to view deleted posts
		// so return true in order for them to be rendered
		if($this->roleLevel->isAtLeast($canDeleteAll)) {
			return true;
		}

		// the user's role is below the minimum to view deleted posts.
		// return false so they dont see it
		else {
			return false;
		}
	}

	private function deletedEnabled(): bool {
		// init default value
		$defaultCookieValue = '1';

		// initialize the cookie value if it doesn't exist
		if(!isset($_COOKIE['viewDeletedPosts'])) {
			setcookie('viewDeletedPosts', $defaultCookieValue, time() + (86400 * 30), "/");
		}

		// extract the value
		$viewDeletedPosts = $_COOKIE['viewDeletedPosts'] ?? $defaultCookieValue;

		// cast to boolean and return
		return (bool) $viewDeletedPosts;
	}

	public function viewDeleted(): bool {
		// check if the user is authorized to view deleted posts
		// return false to ensure unauthorized users cant see DPs
		if($this->canViewDeletedPosts() === false) {
			return false;
		}

		// now check if they have it enabled or disabled
		if($this->deletedEnabled() === false) {
			return false;
		}

		// all is well - return true so deleted posts are rendered
		return true;
	}
}