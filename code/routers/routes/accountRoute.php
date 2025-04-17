<?php

// account route - displays account info and actions

class accountRoute {
	private readonly array $config;
	private readonly staffAccountFromSession $staffSession;
	private readonly globalHTML $globalHTML;
	private readonly softErrorHandler $softErrorHandler;
	private readonly AccountIO $AccountIO;
	private readonly templateEngine $adminTemplateEngine;
	private readonly pageRenderer $adminPageRenderer;

	public function __construct(
		array $config,
		staffAccountFromSession $staffSession,
		globalHTML $globalHTML,
		softErrorHandler $softErrorHandler,
		AccountIO $AccountIO,
		templateEngine $adminTemplateEngine,
		pageRenderer $adminPageRenderer
	) {
		$this->config = $config;
		$this->staffSession = $staffSession;
		$this->globalHTML = $globalHTML;
		$this->softErrorHandler = $softErrorHandler;
		$this->AccountIO = $AccountIO;
		$this->adminTemplateEngine = $adminTemplateEngine;
		$this->adminPageRenderer = $adminPageRenderer;
	}

	public function drawAccountPage(): void {
		$authRoleLevel = $this->staffSession->getRoleLevel();
		$authUsername = htmlspecialchars($this->staffSession->getUsername());

		$this->softErrorHandler->handleAuthError($this->config['roles']['LEV_USER']);

		$accountTableList = ($authRoleLevel == $this->config['roles']['LEV_ADMIN']) 
			? $this->globalHTML->drawAccountTable() 
			: '';

		$currentAccount = $this->AccountIO->getAccountByID($this->staffSession->getUID());

		$accountTemplateValues = [
			'{$ACCOUNT_ID}' => htmlspecialchars($this->staffSession->getUID()),
			'{$ACCOUNT_NAME}' => htmlspecialchars($authUsername),
			'{$ACCOUNT_ROLE}' => htmlspecialchars($this->globalHTML->roleNumberToRoleName($authRoleLevel)),
			'{$ACCOUNT_ACTIONS}' => htmlspecialchars($currentAccount->getNumberOfActions()),
		];

		$accountTemplateRoles = [
			'{$USER}' => $this->config['roles']['LEV_USER'],
			'{$JANITOR}' => $this->config['roles']['LEV_JANITOR'],
			'{$MODERATOR}' => $this->config['roles']['LEV_MODERATOR'],
			'{$ADMIN}' => $this->config['roles']['LEV_ADMIN'],
		];

		$template_values = [
			'{$ACCOUNT_LIST}' => $accountTableList,
			'{$CREATE_ACCOUNT}' => ($authRoleLevel == $this->config['roles']['LEV_ADMIN'])
				? $this->adminTemplateEngine->ParseBlock('CREATE_ACCOUNT', $accountTemplateRoles)
				: '',
			'{$VIEW_OWN_ACCOUNT}' => $this->adminTemplateEngine->ParseBlock('VIEW_ACCOUNT', $accountTemplateValues),
		];

		$accountPageHtml = $this->adminPageRenderer->ParseBlock('ACCOUNT_PAGE', $template_values);
		echo $this->adminPageRenderer->ParsePage('GLOBAL_ADMIN_PAGE_CONTENT', ['{$PAGE_CONTENT}' => $accountPageHtml], true);
	}
}
