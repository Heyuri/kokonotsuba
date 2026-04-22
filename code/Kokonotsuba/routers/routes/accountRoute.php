<?php

// account route - displays account info and actions

namespace Kokonotsuba\routers\routes;

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

class accountRoute {
	public function __construct(
		private readonly array $config,
		private readonly staffAccountFromSession $staffSession,
		private readonly softErrorHandler $softErrorHandler,
		private readonly accountRepository $accountRepository,
		private readonly templateEngine $adminTemplateEngine,
		private readonly pageRenderer $adminPageRenderer,
		private readonly request $request
	) {}

	public function drawAccountPage(): void {
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

		$accountTemplateRoles = [
			'{$CSRF_HIDDEN_INPUT}' => $csrfHiddenInput,
			'{$USER}' => userRole::LEV_USER->value,
			'{$JANITOR}' => userRole::LEV_JANITOR->value,
			'{$MODERATOR}' => userRole::LEV_MODERATOR->value,
			'{$ADMIN}' => userRole::LEV_ADMIN->value,
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

	public function drawStaffAccountPage(): void {
		$authRoleLevel = $this->staffSession->getRoleLevel();
		$this->softErrorHandler->handleAuthError(userRole::LEV_ADMIN);

		$viewAccountId = $this->request->getParameter('id', 'GET');
		if ($viewAccountId === null) {
			header('Location: ' . $this->config['LIVE_INDEX_FILE'] . '?mode=account');
			return;
		}

		$staffAccount = $this->accountRepository->getAccountByID((int)$viewAccountId);
		if (!$staffAccount) {
			header('Location: ' . $this->config['LIVE_INDEX_FILE'] . '?mode=account');
			return;
		}

		$csrfHiddenInput = getCsrfHiddenInput();

		$staffAccountValues = [
			'{$CSRF_HIDDEN_INPUT}' => $csrfHiddenInput,
			'{$ACCOUNT_ID}' => sanitizeStr($staffAccount->getId()),
			'{$ACCOUNT_NAME}' => sanitizeStr($staffAccount->getUsername()),
			'{$ACCOUNT_ROLE}' => sanitizeStr($staffAccount->getRoleLevel()->displayRoleName()),
			'{$ACCOUNT_ACTIONS}' => sanitizeStr($staffAccount->getNumberOfActions()),
			'{$ACCOUNT_LAST_LOGIN}' => $staffAccount->getLastLogin() ?? 'Never',
		];

		$staffAccountHtml = $this->adminTemplateEngine->ParseBlock('VIEW_STAFF_ACCOUNT', $staffAccountValues);
		echo $this->adminPageRenderer->ParsePage('GLOBAL_ADMIN_PAGE_CONTENT', ['{$PAGE_CONTENT}' => $staffAccountHtml], true);
	}
}
