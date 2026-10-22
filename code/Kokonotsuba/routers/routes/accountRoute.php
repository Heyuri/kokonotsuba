<?php

// account route - displays account info and actions

namespace Kokonotsuba\routers\routes;

use Kokonotsuba\routers\route;
use Kokonotsuba\account\accountRepository;
use Kokonotsuba\account\staffAccountFromSession;
use Kokonotsuba\error\softErrorHandler;
use Kokonotsuba\request\request;
use Kokonotsuba\template\pageRenderer;
use Kokonotsuba\template\templateEngine;
use Kokonotsuba\userRole;

use function Kokonotsuba\libraries\getCsrfHiddenInput;
use function Kokonotsuba\libraries\html\drawAccountTable;
use function Puchiko\strings\sanitizeStr;

class accountRoute implements route {
	public function __construct(
		private readonly array $config,
		private readonly staffAccountFromSession $staffSession,
		private readonly softErrorHandler $softErrorHandler,
		private readonly accountRepository $accountRepository,
		private readonly templateEngine $adminTemplateEngine,
		private readonly pageRenderer $adminPageRenderer,
		private readonly request $request
	) {}

	public function handle(): void {
		$authRoleLevel = $this->staffSession->getRoleLevel();
		$authUsername = htmlspecialchars($this->staffSession->getUsername());

		$this->softErrorHandler->handleAuthError(userRole::LEV_USER);

		$accounts = $this->accountRepository->getAllAccounts();

		$csrfHiddenInput = getCsrfHiddenInput();

		$accountTableList = ($authRoleLevel === userRole::LEV_ADMIN) 
			? drawAccountTable($this->config['LIVE_INDEX_FILE'], $accounts, $csrfHiddenInput) 
			: '';

		$currentAccount = $this->accountRepository->getAccountByID($this->staffSession->getUID());

		$accountTemplateValues = [
			'{$CSRF_HIDDEN_INPUT}' => $csrfHiddenInput,
			'{$ACCOUNT_ID}' => htmlspecialchars($this->staffSession->getUID()),
			'{$ACCOUNT_NAME}' => htmlspecialchars($authUsername),
			'{$ACCOUNT_ROLE}' => htmlspecialchars($authRoleLevel->displayRoleName()),
			'{$ACCOUNT_ACTIONS}' => htmlspecialchars($currentAccount->getNumberOfActions()),
		];

		$roleOptions = '';
		foreach (userRole::assignableRoles() as $role) {
			$roleOptions .= '<option value="' . $role->value . '">' . htmlspecialchars($role->displayRoleName()) . '</option>';
		}

		$accountTemplateRoles = [
			'{$CSRF_HIDDEN_INPUT}' => $csrfHiddenInput,
			'{$ROLE_OPTIONS}' => $roleOptions,
		];

		$template_values = [
			'{$ACCOUNT_LIST}' => $accountTableList,
			'{$CREATE_ACCOUNT}' => ($authRoleLevel === userRole::LEV_ADMIN)
				? $this->adminTemplateEngine->ParseBlock('CREATE_ACCOUNT', $accountTemplateRoles)
				: '',
			'{$VIEW_OWN_ACCOUNT}' => $this->adminTemplateEngine->ParseBlock('VIEW_ACCOUNT', $accountTemplateValues),
		];

		$accountPageHtml = $this->adminPageRenderer->ParseBlock('ACCOUNT_PAGE', $template_values);
		echo $this->adminPageRenderer->ParsePage('GLOBAL_ADMIN_PAGE_CONTENT', ['{$PAGE_CONTENT}' => $accountPageHtml], true);
	}
}
