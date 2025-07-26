<?php
//Soft error handler koko
class softErrorHandler {
	public function __construct(
		private readonly string $boardHtmlHeader, 
		private readonly string $boardHtmlFooter, 
		private readonly string $boardIndexFile, 
		private ?templateEngine $templateEngine) {}

	public function handleAuthError(\Kokonotsuba\Root\Constants\userRole $minimumRole) {
		$staffSession = new staffAccountFromSession;
		$authRoleLevel = $staffSession->getRoleLevel() ?? \Kokonotsuba\Root\Constants\userRole::LEV_NONE;

		//handle cases
		if($authRoleLevel === \Kokonotsuba\Root\Constants\userRole::LEV_NONE) {
			$this->errorAndExit("You aren't logged in!"); //this user isn't logged in!
		}
		
		if(!$authRoleLevel->isAtLeast($minimumRole)) {
			$this->errorAndExit("You aren't authorized to view this page!", 403); //forbidden from viewing page
		}
	}

	public function errorAndExit(string $errorMessage, int $statusCode = 0): void {
		if ($statusCode > 0) {
			http_response_code($statusCode);
		} else {
			http_response_code(500); // Default to generic error if none is specified
		}

		$pte_vals = array(
			'{$SELF2}' => $this->boardIndexFile.'?'.time(),
			'{$MESG}' => $errorMessage,
			'{$RETURN_TEXT}' => _T('return'),
			'{$BACK_TEXT}' => _T('error_back'),
			'{$BACK_URL}' => htmlspecialchars($_SERVER['HTTP_REFERER'] ?? '')
		);

		$htmlOutput = '';

		// page header
		$htmlOutput .= $this->boardHtmlHeader;

		// error message html
		$htmlOutput .= $this->templateEngine->ParseBlock('ERROR', $pte_vals);
		
		// page footer
		$htmlOutput .= $this->boardHtmlFooter;

		// display pagr html and then exit
		exit($htmlOutput);
	}
}
