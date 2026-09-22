<?php

namespace Kokonotsuba\error;

use Kokonotsuba\account\staffAccountFromSession as AccountStaffAccountFromSession;
use Kokonotsuba\request\request;
use Kokonotsuba\template\templateEngine;
use Kokonotsuba\userRole;

use function Kokonotsuba\libraries\_T;

class softErrorHandler {
	public function __construct(
		private readonly string $boardHtmlHeader, 
		private readonly string $boardHtmlFooter, 
		private readonly string $boardIndexFile, 
		private templateEngine $templateEngine,
		private readonly AccountStaffAccountFromSession $staffSession,
		private readonly request $request) {}

	public function handleAuthError(userRole $minimumRole) {
		$authRoleLevel = $this->staffSession->getRoleLevel();

		//handle cases
		if($authRoleLevel === userRole::LEV_NONE) {
			$this->errorAndExit("You aren't logged in!", 401); //this user isn't logged in!
		}
		
		if(!$authRoleLevel->isAtLeast($minimumRole)) {
			$this->errorAndExit("You aren't authorized to view this page!", 403); //forbidden from viewing page
		}
	}

	/**
	 * Render an error page and stop.
	 *
	 * @param int $statusCode 0 for the default: a refusal (400). A crash says 500 itself.
	 * @param bool $showErrorImage Show the oopsie image - reserved for the blanket error, where
	 *                             the message alone says nothing about what went wrong.
	 */
	public function errorAndExit(string $errorMessage, int $statusCode = 0, bool $showErrorImage = false): void {
		http_response_code($statusCode > 0 ? $statusCode : 400);

		$pte_vals = array(
			'{$SELF2}' => $this->boardIndexFile.'?'.time(),
			'{$MESG}' => htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'),
			'{$RETURN_TEXT}' => _T('return'),
			'{$BACK_TEXT}' => _T('error_back'),
			'{$BACK_URL}' => htmlspecialchars($this->request->getReferer()),
			'{$SHOW_ERROR_IMAGE}' => $showErrorImage
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
