<?php

// admin route - shows log in page and a router using POST

namespace Kokonotsuba\routers\routes;

use Kokonotsuba\board\board;
use Kokonotsuba\log_in\adminLoginController;
use Kokonotsuba\log_in\loginAttemptService;
use Kokonotsuba\request\request;
use Kokonotsuba\template\pageRenderer;

use function Kokonotsuba\libraries\_T;
use function Kokonotsuba\libraries\html\drawAdminLoginForm;
use function Kokonotsuba\libraries\getIdFromSession;
use function Kokonotsuba\libraries\isLoggedIn;
use function Puchiko\request\redirect;

class adminRoute {
	public function __construct(
		private board $board,
		private readonly adminLoginController $adminLoginController,
		private readonly pageRenderer $adminPageRenderer,
		private readonly loginAttemptService $loginAttemptService,
		private readonly request $request) {}

	public function drawAdminPage(): void {
		$username = $this->request->getParameter('username', 'POST', '');
		$password = $this->request->getParameter('password', 'POST', '');

		$adminRouteUrl = $this->board->getBoardURL(true) . '?mode=admin';

		if(!empty($username) && !empty($password)) {
			$this->adminLoginController->adminLogin($username, $password);
			redirect($adminRouteUrl);
		}

		$modAction = $this->request->getParameter('modAction', 'GET', '');
		if($modAction === 'logout') {
			$this->adminLoginController->adminLogout();
			redirect($adminRouteUrl);
		}

		$adminRouteHtml = '';

		$adminRouteHtml.= '<div id="adminOptionContainer" class="centerText">';

		if(isLoggedIn()) {
			$adminRouteHtml .= $this->drawFailedLoginWarning();
			$adminRouteHtml .= '[<a href="'.$adminRouteUrl.'&modAction=logout">' . _T('admin_logout') . '</a>]';
		} else {
			$adminRouteHtml .= drawAdminLoginForm($adminRouteUrl);
		}

		$adminRouteHtml.= '</div>';

		$htmlOutput = $this->adminPageRenderer->ParsePage('GLOBAL_ADMIN_PAGE_CONTENT', ['{$PAGE_CONTENT}' => $adminRouteHtml], true);

		echo $htmlOutput;
	}

	/**
	 * Red notice listing the failed logins made against this account since it was last warned.
	 *
	 * Shown here rather than at the login itself because a successful login redirects: the ledger
	 * carries the count across that, and reading it marks the batch as warned about.
	 *
	 * @return string Notice markup, or an empty string when there is nothing to report.
	 */
	private function drawFailedLoginWarning(): string {
		$accountId = getIdFromSession();

		if($accountId === null) {
			return '';
		}

		$pending = $this->loginAttemptService->takePendingWarning($accountId);

		if($pending === null) {
			return '';
		}

		$warning = _T('login_failure_warning', $pending['count'], $pending['addresses']);

		if($pending['lastAttempt'] !== null) {
			$warning .= ' ' . _T('login_failure_warning_last', $pending['lastAttempt']);
		}

		return '<p class="loginFailureWarning">' . htmlspecialchars($warning, ENT_QUOTES, 'UTF-8') . '</p>';
	}
}