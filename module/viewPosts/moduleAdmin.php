<?php

namespace Kokonotsuba\Modules\viewPosts;

use Kokonotsuba\ban\visitorTokenSigner;
use Kokonotsuba\error\BoardException;
use Kokonotsuba\module_classes\abstractModuleAdmin;
use Kokonotsuba\module_classes\traits\listeners\PostControlHooksTrait;
use Kokonotsuba\post\managePostsLink;
use Kokonotsuba\post\Post;
use Kokonotsuba\userRole;

use function Kokonotsuba\libraries\_T;
use function Kokonotsuba\libraries\generateModerateButton;
use function Kokonotsuba\libraries\getRoleLevelFromSession;

use function Puchiko\request\redirect;

class moduleAdmin extends abstractModuleAdmin {
	use PostControlHooksTrait;

	private readonly bool $showVisitorToken;

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
		$this->showVisitorToken = (bool) $this->getModuleConfig('SHOW_VISITOR_TOKEN', true);

		$this->listenProtected('ManagePostsControls', function(string &$modControlSection, Post &$post) {
			$this->renderViewPostsButton($modControlSection, $post, false);
		});

		$this->listenProtected('ManagePostsControls', function(string &$modControlSection, Post &$post) {
			$this->renderViewPostsButton($modControlSection, $post, true);
		});

		$this->registerPostWidgetHook('onRenderPostWidget');

		$this->listenProtected('PostAdminControls', function(string &$modControlSection, Post &$post) {
			$this->onRenderPostAdminControls($modControlSection, $post);
		});

		// Expose the staff-only "Show user IPs" toggle on live staff views.
		$this->registerAdminHeaderHook('onRenderModuleHeader');
	}

	private function onRenderModuleHeader(string &$moduleHeader): void {
		// Only raw-IP viewers see the IP control, so only they get the toggle.
		if(!$this->canViewRawIp()) {
			return;
		}

		// Not deferred: the script runs in <head> and hides the IP controls
		// before the posts paint, so toggling is seamless.
		$this->includeScript('viewPosts.js', $moduleHeader, false);
	}

    private function generateViewPostsUrl(string $postUid): string {
        return $this->getModulePageURL(['post_uid' => $postUid], false, true);
    }

	private function generateViewIpUrl(string $ipAddress): string {
		return $this->getModulePageURL(['ip_address' => $ipAddress], false, true);
	}

	private function generateViewTokenUrl(string $tokenHash): string {
		return $this->getModulePageURL(['visitor_token_hash' => $tokenHash], false, true);
	}

	private function canViewRawIp(): bool {
		$roleLevel = getRoleLevelFromSession();
		$canViewIpLevel = $this->getConfig('AuthLevels.CAN_VIEW_IP_ADDRESSES', userRole::LEV_MODERATOR);
		return $roleLevel->isAtLeast($canViewIpLevel);
	}

	private function canViewHashedIp(): bool {
		$roleLevel = getRoleLevelFromSession();
		$canViewHashedIpLevel = $this->getConfig('AuthLevels.CAN_ONLY_VIEW_POSTS_FROM_USER', userRole::LEV_JANITOR);
		return $roleLevel->isAtLeast($canViewHashedIpLevel);
	}

	private function renderViewPostsButton(string &$modControlSection, Post &$post, bool $noScript = false): void {
		if($this->canViewRawIp()) {
			return;
		}
		
		$viewPostsUrl = $this->generateViewPostsUrl($post->getUid());
		
		$modControlSection .= generateModerateButton(
			$viewPostsUrl,
			'VP',
			_T('view_posts_by_user'),
			'adminViewPostsFunction',
			$noScript
		);
	}

	private function onRenderPostWidget(array &$widgetArray, Post &$post): void {
        // dont bother for IP viewers
        if($this->canViewRawIp()) {
            return;
        }

		// generate view posts url
		$viewPostsUrl = $this->generateViewPostsUrl($post->getUid());

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
        $currentUrl = $this->moduleContext->request->getCurrentUrlNoQuery();

        // determine which type of failter to apply based on user role
		$postUid = $this->moduleContext->request->getParameter('post_uid', 'GET', '');
		$ipAddress = $this->moduleContext->request->getParameter('ip_address', 'GET', '');
		$tokenHash = $this->moduleContext->request->getParameter('visitor_token_hash', 'GET', '');

		if(!empty($postUid)) {
			// view posts by user flow
			$this->handleViewPostsByUser($currentUrl, $postUid);
		} elseif(!empty($ipAddress)) {
			// view posts by IP flow
			$this->handleViewPostsByIp($currentUrl, $ipAddress);
		} elseif(!empty($tokenHash)) {
			// view posts by browser flow
			$this->handleViewPostsByToken($currentUrl, $tokenHash);
		} else {
			throw new BoardException(_T('post_not_found'), 404);
		}
	}

	private function handleViewPostsByUser(string $currentUrl, string $postUid): void {
		redirect(managePostsLink::forPost($currentUrl, $postUid));
	}

	private function handleViewPostsByIp(string $currentUrl, string $ipAddress): void {
		redirect(managePostsLink::forIp($currentUrl, $ipAddress));
	}

	/**
	 * Every post made with one browser, across every board.
	 *
	 * The label beside the address is the first half of the token hash; the whole of it goes in
	 * the query so the filter is an equality and cannot drift onto somebody else's browser.
	 */
	private function handleViewPostsByToken(string $currentUrl, string $tokenHash): void {
		redirect(managePostsLink::forVisitorToken($currentUrl, $tokenHash));
	}

	private function isManagePostsRoute(): bool {
		// Get the mode
		$mode = $this->moduleContext->request->getParameter('mode', 'GET', '');
		
		// Check if its manage posts
		if($mode === 'managePosts') {
			return true;
		} else {
			return false;
		}
	}

	private function onRenderPostAdminControls(string &$modControlSection, Post &$post): void {
		// Return early if the user is viewing the manage posts screen
		// This is so the control doesn't show up in the func column
		if($this->isManagePostsRoute()) {
			return;
		}
		
		// Check user role to determine which behavior to use
		if($this->canViewRawIp()) {
			// Show raw IP for higher-privilege users. Wrapped so the "Show user
			// IPs" toggle (viewPosts.js) can hide the IP and its brackets cleanly.
			$postLink = $this->generateViewIpUrl($post->getIp());
			$button = '<span class="ipAddressControl">[<a class="ipAddress" href="' . htmlspecialchars($postLink) . '">' . htmlspecialchars($post->getIp()) . '</a>]' . $this->renderVisitorTokenHash($post) . '</span>';
		} else if($this->canViewHashedIp()) {
			// Show hashed IP and user-based filter for lower-privilege users
			$postLink = $this->generateViewPostsUrl($post->getUid());
			$button = '[<a class="hashedIp" href="' . htmlspecialchars($postLink) . '">' . htmlspecialchars(substr(md5($post->getIp()), 0, 8)) . '</a>]';
		}
		
		// append the button to the hook point
		$modControlSection .= $button;
	}

	/**
	 * The post's browser as a short label, telling apart people sharing one address.
	 *
	 * Three states, and they are not the same: a token hash, an explicit note that the browser
	 * kept no token at all, and nothing whatsoever for posts made before this was recorded.
	 */
	private function renderVisitorTokenHash(Post $post): string {
		if (!$this->showVisitorToken) {
			return '';
		}

		$hash = $post->getVisitorTokenHash();

		if ($hash === '') {
			// NULL and '' both read as '' here, so only a post recorded as cookieless says so.
			// Nothing to link to either: there is no browser to gather posts by.
			return $post->hasVisitorTokenHash()
				? ' <span class="visitorToken noCookies"><i>(' . htmlspecialchars(_T('view_posts_no_cookies')) . ')</i></span>'
				: '';
		}

		$label = substr($hash, 0, visitorTokenSigner::DISPLAY_LENGTH);

		return ' <span class="visitorToken"><i>(<a class="visitorTokenLink" href="'
			. htmlspecialchars($this->generateViewTokenUrl($hash)) . '" title="'
			. htmlspecialchars(_T('view_posts_by_browser')) . '">'
			. htmlspecialchars($label) . '</a>)</i></span>';
	}
}
