<?php

namespace Kokonotsuba\Modules\viewPosts;

use Kokonotsuba\error\BoardException;
use Kokonotsuba\module_classes\abstractModuleAdmin;
use Kokonotsuba\userRole;

use function Kokonotsuba\libraries\_T;
use function Kokonotsuba\libraries\generateModerateButton;
use function Kokonotsuba\libraries\getRoleLevelFromSession;
use function Kokonotsuba\libraries\html\getCurrentUrlNoQuery;
use function Puchiko\request\redirect;

class moduleAdmin extends abstractModuleAdmin {
	public function getRequiredRole(): userRole {
		return $this->getConfig('AuthLevels.CAN_ONLY_VIEW_POSTS_FROM_USER', userRole::LEV_JANITOR);
	}

	public function getName(): string {
		return 'View posts mod tool';
	}

	public function getVersion(): string {
		return 'Twendy twendy sex';
	}

	public function initialize(): void {
		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this->getRequiredRole(),
			'ManagePostsControls',
			function(string &$modControlSection, array &$post) {
				$this->renderViewPostsButton($modControlSection, $post, false);
			}
		);

		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this->getRequiredRole(),
			'ManagePostsControls',
			function(string &$modControlSection, array &$post) {
				$this->renderViewPostsButton($modControlSection, $post, true);
			}
		);

		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this->getRequiredRole(),
			'ModeratePostWidget',
			function(array &$widgetArray, array &$post) {
				$this->onRenderPostWidget($widgetArray, $post);
			}
		);

		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this->getRequiredRole(),
			'PostAdminControls',
			function(string &$modControlSection, array &$post) {
				$this->onRenderPostAdminControls($modControlSection, $post);
			}
		);

	}

    private function generateViewPostsUrl(string $postUid): string {
        return $this->getModulePageURL(['post_uid' => $postUid], false, true);
    }

	private function generateViewIpUrl(string $ipAddress): string {
		return $this->getModulePageURL(['ip_address' => $ipAddress], false, true);
	}

	private function canViewRawIp(): bool {
		$roleLevel = getRoleLevelFromSession();
		$canViewIpLevel = $this->getConfig('AuthLevels.CAN_VIEW_IP_ADDRESSES', userRole::LEV_MODERATOR);
		return $roleLevel->isAtLeast($canViewIpLevel);
	}

	private function renderViewPostsButton(string &$modControlSection, array &$post, bool $noScript = false): void {
		$viewPostsUrl = $this->generateViewPostsUrl($post['post_uid']);
		
		$modControlSection .= generateModerateButton(
			$viewPostsUrl,
			'VP',
			_T('view_posts_by_user'),
			'adminViewPostsFunction',
			$noScript
		);
	}

	private function onRenderPostWidget(array &$widgetArray, array &$post): void {
        // dont bother for IP viewers
        if($this->canViewRawIp()) {
            return;
        }

		// generate view posts url
		$viewPostsUrl = $this->generateViewPostsUrl($post['post_uid']);

		// build the widget entry for view posts
		$viewPostsWidget = $this->buildWidgetEntry(
			$viewPostsUrl, 
			'viewPosts', 
			_T('view_posts_by_user'), 
			''
		);
		
		// add the widget to the array
		$widgetArray[] = $viewPostsWidget;
	}

	public function ModulePage(): void {
        // get current url
        $currentUrl = getCurrentUrlNoQuery();

        // determine which type of failter to apply based on user role
		$postUid = $_GET['post_uid'] ?? '';
		$ipAddress = $_GET['ip_address'] ?? '';

		if(!empty($postUid)) {
			// view posts by user flow
			$this->handleViewPostsByUser($currentUrl, $postUid);
		} elseif(!empty($ipAddress)) {
			// view posts by IP flow
			$this->handleViewPostsByIp($currentUrl, $ipAddress);
		} else {
			throw new BoardException(_T('post_not_found'), 404);
		}
	}

	private function handleViewPostsByUser(string $currentUrl, string $postUid): void {
        // board uids for query build
		$allBoardUids = [];

        // build a list of all board uids for the query string
		foreach(GLOBAL_BOARD_ARRAY as $board) {
			$allBoardUids[] = $board->getBoardUID();
		}
		
        // build the board list
		$boardList = implode(' ', $allBoardUids);
		
        // build the query string with the post uid and board list
		$query = http_build_query(
			[
				'mode' => 'managePosts',
				'postsFrom' => $postUid,
				'board' => $boardList
			]);
		
        // build the final url to redirect to
		$url = $currentUrl . '?' . $query;

        // redirect to the manage posts page with the query parameters to show posts from the selected user
		redirect($url);
	}

	private function handleViewPostsByIp(string $currentUrl, string $ipAddress): void {
		$allBoardUids = [];

		foreach(GLOBAL_BOARD_ARRAY as $board) {
			$allBoardUids[] = $board->getBoardUID();
		}
		
		$boardList = implode(' ', $allBoardUids);
		
		$query = http_build_query(
			[
				'mode' => 'managePosts',
				'ip_address' => $ipAddress,
				'board' => $boardList
			]);
		
		$url = $currentUrl . '?' . $query;

		redirect($url);
	}

	private function isManagePostsRoute(): bool {
		// Get the mode
		$mode = $_GET['mode'] ?? '';
		
		// Check if its manage posts
		if($mode === 'managePosts') {
			return true;
		} else {
			return false;
		}
	}

	private function onRenderPostAdminControls(string &$modControlSection, array &$post): void {
		// Return early if the user is viewing the manage posts screen
		// This is so the control doesn't show up in the func column
		if($this->isManagePostsRoute()) {
			return;
		}
		
		// Check user role to determine which behavior to use
		if($this->canViewRawIp()) {
			// Show raw IP for higher-privilege users
			$postLink = $this->generateViewIpUrl($post['host']);
			$button = '[<a href="' . htmlspecialchars($postLink) . '">' . htmlspecialchars($post['host']) . '</a>]';
		} else {
			// Show hashed IP and user-based filter for lower-privilege users
			$postLink = $this->generateViewPostsUrl($post['post_uid']);
			$button = '[<a href="' . htmlspecialchars($postLink) . '">' . htmlspecialchars(substr(md5($post['host']), 0, 8)) . '</a>]';
		}
		
		// append the button to the hook point
		$modControlSection .= $button;
	}

}
