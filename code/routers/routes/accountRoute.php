<?php

// account route - displays account info and actions

class accountRoute {
	public function __construct(
		private readonly array $config,
		private readonly staffAccountFromSession $staffSession,
		private readonly softErrorHandler $softErrorHandler,
		private readonly accountRepository $accountRepository,
		private readonly ?templateEngine $adminTemplateEngine,
		private readonly pageRenderer $adminPageRenderer
	) {}

	public function drawAccountPage(): void {
		$authRoleLevel = $this->staffSession->getRoleLevel();
		$authUsername = htmlspecialchars($this->staffSession->getUsername());

		$this->softErrorHandler->handleAuthError(\Kokonotsuba\Root\Constants\userRole::LEV_USER);

		$accounts = $this->accountRepository->getAllAccounts();

		$accountTableList = ($authRoleLevel === \Kokonotsuba\Root\Constants\userRole::LEV_ADMIN) 
			? drawAccountTable($this->config['LIVE_INDEX_FILE'], $accounts) 
			: '';

		$currentAccount = $this->accountRepository->getAccountByID($this->staffSession->getUID());

		$accountTemplateValues = [
			'{$ACCOUNT_ID}' => htmlspecialchars($this->staffSession->getUID()),
			'{$ACCOUNT_NAME}' => htmlspecialchars($authUsername),
			'{$ACCOUNT_ROLE}' => htmlspecialchars($authRoleLevel->displayRoleName()),
			'{$ACCOUNT_ACTIONS}' => htmlspecialchars($currentAccount->getNumberOfActions()),
		];

		$accountTemplateRoles = [
			'{$USER}' => \Kokonotsuba\Root\Constants\userRole::LEV_USER->value,
			'{$JANITOR}' => \Kokonotsuba\Root\Constants\userRole::LEV_JANITOR->value,
			'{$MODERATOR}' => \Kokonotsuba\Root\Constants\userRole::LEV_MODERATOR->value,
			'{$ADMIN}' => \Kokonotsuba\Root\Constants\userRole::LEV_ADMIN->value,
		];

		$template_values = [
			'{$ACCOUNT_LIST}' => $accountTableList,
			'{$CREATE_ACCOUNT}' => ($authRoleLevel === \Kokonotsuba\Root\Constants\userRole::LEV_ADMIN)
				? $this->adminTemplateEngine->ParseBlock('CREATE_ACCOUNT', $accountTemplateRoles)
				: '',
			'{$VIEW_OWN_ACCOUNT}' => $this->adminTemplateEngine->ParseBlock('VIEW_ACCOUNT', $accountTemplateValues),
		];

		$accountPageHtml = $this->adminPageRenderer->ParseBlock('ACCOUNT_PAGE', $template_values);
		echo $this->adminPageRenderer->ParsePage('GLOBAL_ADMIN_PAGE_CONTENT', ['{$PAGE_CONTENT}' => $accountPageHtml], true);
	}
}
