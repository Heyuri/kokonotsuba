<?php

// handleAccountAction route - handles actions on accounts

class handleAccountActionRoute {
	public function __construct(
		private readonly array $config,
		private board $board,
		private readonly accountService $accountService,
		private readonly actionLoggerService $actionLoggerService,
		private readonly softErrorHandler $softErrorHandler,
		private readonly staffAccountFromSession $staffAccountFromSession
	) {}

	public function handleAccountRequests(): void {
		$this->softErrorHandler->handleAuthError(\Kokonotsuba\Root\Constants\userRole::LEV_USER);

		if ($this->staffAccountFromSession->getRoleLevel() === \Kokonotsuba\Root\Constants\userRole::LEV_ADMIN) {
			$accountIdToDelete = $_GET['del'] ?? null;
			$accountIdToDemote = $_GET['dem'] ?? null;
			$accountIdToPromote = $_GET['up'] ?? null;

			$newAccountUsername = $_POST['usrname'] ?? null;
			$newAccountPassword = $_POST['passwd'] ?? null;
			$newAccountIsAlreadyHashed = $_POST['ishashed'] ?? null;

			if (isset($accountIdToDelete)) {
				$this->accountService->handleAccountDelete($accountIdToPromote);
			}
			if (isset($accountIdToDemote)) {
				$this->accountService->handleAccountDemote($accountIdToDemote);
			}
			if (isset($accountIdToPromote)) {
				$this->accountService->handleAccountPromote($accountIdToPromote);
			}
			if (!empty($newAccountUsername) && !empty($newAccountPassword)) {
				$this->accountService->handleAccountCreation($this->board);
			}
		}

		if (!empty($newAccountIsAlreadyHashed ?? '')) {
			$this->accountService->handleAccountPasswordReset($this->board);
		}

		redirect($this->config['LIVE_INDEX_FILE'] . '?mode=account');
	}
}
