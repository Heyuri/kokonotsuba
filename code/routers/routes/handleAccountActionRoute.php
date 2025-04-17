<?php

// handleAccountAction route - handles actions on accounts

class handleAccountActionRoute {
	private readonly array $config;
	private readonly board $board;
	private readonly softErrorHandler $softErrorHandler;
	private readonly staffAccountFromSession $staffSession;

	public function __construct(
		array $config,
		board $board,
		softErrorHandler $softErrorHandler,
		staffAccountFromSession $staffSession
	) {
		$this->config = $config;
		$this->board = $board;
		$this->softErrorHandler = $softErrorHandler;
		$this->staffSession = $staffSession;
	}

	public function handleAccountRequests(): void {
		$accountRequestHandler = new accountRequestHandler($this->board);

		$this->softErrorHandler->handleAuthError($this->config['roles']['LEV_USER']);

		if ($this->staffSession->getRoleLevel() == $this->config['roles']['LEV_ADMIN']) {
			if (isset($_GET['del'])) {
				$accountRequestHandler->handleAccountDelete();
			}
			if (isset($_GET['dem'])) {
				$accountRequestHandler->handleAccountDemote();
			}
			if (isset($_GET['up'])) {
				$accountRequestHandler->handleAccountPromote();
			}
			if (!empty($_POST['usrname']) && !empty($_POST['passwd'])) {
				$accountRequestHandler->handleAccountCreation($this->board);
			}
		}

		if (!empty($_POST['new_account_password'] ?? '')) {
			$accountRequestHandler->handleAccountPasswordReset($this->board);
		}

		redirect($this->config['PHP_SELF'] . '?mode=account');
	}
}
