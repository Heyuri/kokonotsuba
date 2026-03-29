<?php

// handleAccountAction route - handles actions on accounts

namespace Kokonotsuba\routers\routes;

use Kokonotsuba\board\board;
use Kokonotsuba\account\accountService;
use Kokonotsuba\action_log\actionLoggerService;
use Kokonotsuba\error\softErrorHandler;
use Kokonotsuba\account\staffAccountFromSession;
use Kokonotsuba\request\request;
use Kokonotsuba\userRole;
use function Puchiko\request\redirect;

class handleAccountActionRoute {
	public function __construct(
		private readonly array $config,
		private board $board,
		private readonly accountService $accountService,
		private readonly actionLoggerService $actionLoggerService,
		private readonly softErrorHandler $softErrorHandler,
		private readonly staffAccountFromSession $staffAccountFromSession,
		private readonly request $request
	) {}

	public function handleAccountRequests(): void {
		$this->softErrorHandler->handleAuthError(userRole::LEV_USER);

		if ($this->staffAccountFromSession->getRoleLevel() === userRole::LEV_ADMIN) {
			$accountIdToDelete = $this->request->getParameter('del', 'GET');
			$accountIdToDemote = $this->request->getParameter('dem', 'GET');
			$accountIdToPromote = $this->request->getParameter('up', 'GET');

			$newAccountUsername = $this->request->getParameter('usrname', 'POST');
			$newAccountPassword = $this->request->getParameter('passwd', 'POST');
			$newAccountIsAlreadyHashed = !empty($this->request->getParameter('ishashed', 'POST'));
			$newAccountRole = $this->request->getParameter('role', 'POST');

			if (isset($accountIdToDelete)) {
				$this->accountService->handleAccountDelete($accountIdToDelete);
			}
			if (isset($accountIdToDemote)) {
				$this->accountService->handleAccountDemote($accountIdToDemote);
			}
			if (isset($accountIdToPromote)) {
				$this->accountService->handleAccountPromote($accountIdToPromote);
			}
			if (!empty($newAccountUsername) && !empty($newAccountPassword)) {
				$this->accountService->handleAccountCreation($newAccountIsAlreadyHashed, $newAccountPassword, $newAccountUsername, $newAccountRole);
			}
		}

		// used for password reset
		$newAccountPasswordForReset = $this->request->getParameter('new_account_password', 'POST');

		if (!empty($newAccountPasswordForReset)) {
			$this->accountService->handleAccountPasswordReset($this->staffAccountFromSession, $newAccountPasswordForReset);
		}

		redirect($this->config['LIVE_INDEX_FILE'] . '?mode=account');
	}
}
