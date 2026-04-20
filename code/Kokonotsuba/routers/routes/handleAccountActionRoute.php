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
use function Kokonotsuba\libraries\requirePostWithCsrf;
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

		// All account mutations require POST + CSRF
		if ($this->request->isPost()) {
			requirePostWithCsrf($this->request);
		}

		if ($this->staffAccountFromSession->getRoleLevel() === userRole::LEV_ADMIN) {
			// Bulk delete from checkboxes
			$bulkDelete = $this->request->getParameter('bulk_delete', 'POST');
			$delIds = $this->request->getParameter('del_ids', 'POST');
			if ($bulkDelete !== null && is_array($delIds)) {
				foreach ($delIds as $id) {
					$this->accountService->handleAccountDelete((int)$id);
				}
			}

			// Single account actions from the view page
			$action = $this->request->getParameter('action', 'POST');
			$targetAccountId = $this->request->getParameter('target_account_id', 'POST');

			if ($action !== null && $targetAccountId !== null) {
				$targetAccountId = (int)$targetAccountId;

				switch ($action) {
					case 'delete':
						$this->accountService->handleAccountDelete($targetAccountId);
						redirect($this->config['LIVE_INDEX_FILE'] . '?mode=account');
						return;
					case 'demote':
						$this->accountService->handleAccountDemote($targetAccountId);
						break;
					case 'promote':
						$this->accountService->handleAccountPromote($targetAccountId);
						break;
					case 'reset_password':
						$adminResetPassword = $this->request->getParameter('admin_reset_password', 'POST');
						if (!empty($adminResetPassword)) {
							$this->accountService->handleAdminPasswordReset($targetAccountId, $adminResetPassword);
						}
						break;
				}

				redirect($this->config['LIVE_INDEX_FILE'] . '?mode=viewStaffAccount&id=' . $targetAccountId);
				return;
			}

			// Account creation
			$newAccountUsername = $this->request->getParameter('usrname', 'POST');
			$newAccountPassword = $this->request->getParameter('passwd', 'POST');
			$newAccountIsAlreadyHashed = !empty($this->request->getParameter('ishashed', 'POST'));
			$newAccountRole = $this->request->getParameter('role', 'POST');

			if (!empty($newAccountUsername) && !empty($newAccountPassword)) {
				$this->accountService->handleAccountCreation($newAccountIsAlreadyHashed, $newAccountPassword, $newAccountUsername, $newAccountRole);
			}
		}

		// Own password reset (any logged-in user)
		$passwordResetForm = $this->request->getParameter('password_reset_form', 'POST');
		$newAccountPasswordForReset = $this->request->getParameter('new_account_password', 'POST');

		if (!empty($passwordResetForm) && !empty($newAccountPasswordForReset)) {
			$this->accountService->handleAccountPasswordReset($this->staffAccountFromSession, $newAccountPasswordForReset);
		}

		redirect($this->config['LIVE_INDEX_FILE'] . '?mode=account');
	}
}
