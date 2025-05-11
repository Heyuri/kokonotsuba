<?php

// handleAccountAction route - handles actions on accounts

class handleAccountActionRoute {
	private readonly array $config;
	private readonly board $board;
	private readonly AccountIO $AccountIO;
	private readonly actionLogger $actionLogger;
	private readonly softErrorHandler $softErrorHandler;
	private readonly staffAccountFromSession $staffSession;

	public function __construct(
		array $config,
		board $board,
		AccountIO $AccountIO,
		actionLogger $actionLogger,
		softErrorHandler $softErrorHandler,
		staffAccountFromSession $staffSession
	) {
		$this->config = $config;
		$this->board = $board;
		$this->AccountIO = $AccountIO;
		$this->actionLogger = $actionLogger;
		$this->softErrorHandler = $softErrorHandler;
		$this->staffSession = $staffSession;
	}

	public function handleAccountRequests(): void {
		$accountRequestHandler = new accountRequestHandler($this->AccountIO, $this->actionLogger);

		$this->softErrorHandler->handleAuthError(\Kokonotsuba\Root\Constants\userRole::LEV_USER);

		if ($this->staffSession->getRoleLevel() === \Kokonotsuba\Root\Constants\userRole::LEV_ADMIN) {
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
