<?php

// admin route - shows log in page and a router using POST

class adminRoute {
	public function __construct(
		private board $board,
		private readonly array $config,
		private readonly adminLoginController $adminLoginController,
		private readonly pageRenderer $adminPageRenderer) {}

	public function drawAdminPage(): void {
		$username = $_POST['username'] ?? '';
		$password = $_POST['password'] ?? '';

		$adminRouteUrl = $this->board->getBoardURL(true) . '?mode=admin';

		if(!empty($username) && !empty($password)) {
			$this->adminLoginController->adminLogin($username, $password);
			redirect($adminRouteUrl);
		}

		$modAction = $_GET['modAction'] ?? '';
		if($modAction === 'logout') {
			$this->adminLoginController->adminLogout();
			redirect($adminRouteUrl);
		}

		$adminRouteHtml = '';

		$adminRouteHtml.= '<div id="adminOptionContainer" class="centerText">';

		if(isLoggedIn()) {
			$adminRouteHtml .= '[<a href="'.$adminRouteUrl.'&modAction=logout">Log out</a>]';
		} else {
			$adminRouteHtml .= drawAdminLoginForm($adminRouteUrl);
		}

		$adminRouteHtml.= '</div>';

		$htmlOutput = $this->adminPageRenderer->ParsePage('GLOBAL_ADMIN_PAGE_CONTENT', ['{$PAGE_CONTENT}' => $adminRouteHtml], true);

		echo $htmlOutput;
	}
}