<?php

namespace Kokonotsuba\Modules\viewPosts;

use Kokonotsuba\error\BoardException;
use Kokonotsuba\module_classes\abstractModuleAdmin;
use Kokonotsuba\userRole;

use function Kokonotsuba\libraries\_T;
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
        // dont bother if the mod is above the max permission level for this module.
        //
        // its still authenticated by the module loader so you dont need to worry about it being accessed
        // by under-privileged users.
        if(!getRoleLevelFromSession()->isAtMost($this->getRequiredRole())) {
            return;
        }

		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this->getRequiredRole(),
			'ManagePostsControls',
			function(string &$modControlSection, array &$post) {
				$this->renderViewPostsButton($modControlSection, $post);
			}
		);

		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this->getRequiredRole(),
			'ModeratePostWidget',
			function(array &$widgetArray, array &$post) {
				$this->onRenderPostWidget($widgetArray, $post);
			}
		);

	}

    private function generateViewPostsUrl(string $postUid): string {
        return $this->getModulePageURL(['post_uid' => $postUid], false, true);
    }

	private function renderViewPostsButton(string &$modControlSection, array &$post): void {
		$viewPostsUrl = $this->generateViewPostsUrl($post['post_uid']);
		
		$modControlSection .= '<span class="adminFunctions adminViewFunction">[<a href="' . htmlspecialchars($viewPostsUrl) . '" title="View posts by this user.">VP</a>]</span>';
	}

	private function onRenderPostWidget(array &$widgetArray, array &$post): void {
		// generate view posts url
		$viewPostsUrl = $this->generateViewPostsUrl($post['post_uid']);

		// build the widget entry for view posts
		$viewPostsWidget = $this->buildWidgetEntry(
			$viewPostsUrl, 
			'viewPosts', 
			'View posts by user.', 
			''
		);
		
		// add the widget to the array
		$widgetArray[] = $viewPostsWidget;
	}

	public function ModulePage(): void {
        // get current url
        $currentUrl = getCurrentUrlNoQuery();

        // get the post uid from the query parameters
		$postUid = $_GET['post_uid'] ?? '';

        // no post selected
        if(empty($postUid)) {
            throw new BoardException(_T('post_not_found'), 404);
        }

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
        // (without)
		redirect($url);
	}
}