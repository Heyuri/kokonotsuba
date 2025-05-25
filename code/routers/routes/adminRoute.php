<?php

// admin route - shows log in page and a router using POST

class adminRoute {
    private readonly array $config;
    private readonly globalHTML $globalHTML;
	private readonly adminLoginController $adminLoginController;
	private readonly staffAccountFromSession $staffSession;
	private readonly pageRenderer $adminPageRenderer;


    public function __construct(array $config,
        globalHTML $globalHTML, 
		adminLoginController $adminLoginController,
        staffAccountFromSession $staffSession,
		pageRenderer $adminPageRenderer) {
        $this->config = $config;
        $this->globalHTML = $globalHTML;
		$this->adminLoginController = $adminLoginController;
        $this->staffSession = $staffSession;
		$this->adminPageRenderer = $adminPageRenderer;
    }

    public function drawAdminPage(): void {
		$username = $_POST['username'] ?? '';
		$password = $_POST['password'] ?? '';

		$adminRouteUrl = $this->globalHTML->fullURL().$this->config['PHP_SELF'].'?mode=admin';

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
			$adminRouteHtml .= $this->globalHTML->drawAdminLoginForm($adminRouteUrl);
		}

		$adminRouteHtml.= '</div>';

		$htmlOutput = $this->adminPageRenderer->ParsePage('GLOBAL_ADMIN_PAGE_CONTENT', ['{$PAGE_CONTENT}' => $adminRouteHtml], true);

		echo $htmlOutput;
	}
}