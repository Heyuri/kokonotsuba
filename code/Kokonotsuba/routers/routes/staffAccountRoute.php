<?php

// staff account route - one staff account's details and actions, for admins

namespace Kokonotsuba\routers\routes;

use Kokonotsuba\routers\route;
use Kokonotsuba\account\accountRepository;
use Kokonotsuba\error\softErrorHandler;
use Kokonotsuba\request\request;
use Kokonotsuba\template\pageRenderer;
use Kokonotsuba\template\templateEngine;
use Kokonotsuba\userRole;

use function Kokonotsuba\libraries\getCsrfHiddenInput;
use function Puchiko\strings\sanitizeStr;

class staffAccountRoute implements route {
	public function __construct(
		private readonly array $config,
		private readonly softErrorHandler $softErrorHandler,
		private readonly accountRepository $accountRepository,
		private readonly templateEngine $adminTemplateEngine,
		private readonly pageRenderer $adminPageRenderer,
		private readonly request $request
	) {}

	public function handle(): void {
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
