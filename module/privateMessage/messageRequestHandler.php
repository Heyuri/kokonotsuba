<?php

namespace Kokonotsuba\Modules\privateMessage;

use Kokonotsuba\error\BoardException;

use function Kokonotsuba\libraries\_T;
use function Puchiko\request\redirect;

class messageRequestHandler {
    public function __construct(
        private messageService $messageService,
        private messagePolicy $messagePolicy,
        private messageRenderer $messageRenderer,
        private messageUtility $messageUtility,
    ) {}

	private function handlePrivateThread(string $usertripCode, string $contactTripcode, int $page = 0): string {
		// validate that the user's PM hash is among the contact's allowed hashes
		$isAuthorized = $this->messagePolicy->canViewContact($contactTripcode, $usertripCode);

		// if not authorized then throw 403
		if(!$isAuthorized) {
			throw new BoardException(_T('pm_login_required'), 403);
		}

		// get PMs from the contact
		$messages = $this->messageService->getMessagesForTripCode($usertripCode, $page);

		// render the thread view and return it
		return $this->messageRenderer->renderContactView($messages, $page) ?? '';
	}

	public function handleInboxPage(): void {
		// get selected contact's tripcode from query params
		$contactTripcode = $_GET['tripcode'] ?? null;
		
		// if not selected, return empty string (no thread view)
		if(!$contactTripcode) {
			throw new BoardException(_T('contact_not_selected'), 400);
		}

		// get page number for the thread view
		$page = intval($_GET['page'] ?? 0);

		// get user trip hash
		$tripCode = $this->messageUtility->getUsertripCode();

		// handle PM thread html generation if one is selected
		$messageThreadHtml = $this->handlePrivateThread($tripCode, $contactTripcode, $page);

		// get contact data
		$contacts = $this->messageService->getContactsForUser($tripCode);

		// render the inbox page
		$this->messageRenderer->renderInboxPage(
            $contacts, 
            $messageThreadHtml, 
            $this->messageUtility->getModulePageURL()
        );
	}

	public function handleWriteMessage(): void {
		// get form inputs
		$recipient = $_POST['recipient'] ?? '';
		$name = $_POST['name'] ?? '';
		$subject = $_POST['subject'] ?? '';
		$body = $_POST['body'] ?? '';

		// validate empty inputs
		if (empty($recipient) || empty($body)) {
			throw new BoardException(_T('pm_recipient_and_message_required'), 400);
		}

		// now validate that they're valid trip codes
		if($this->messageUtility->isValidTripCode($recipient) === false) {
			throw new BoardException(_T('pm_invalid_recipient'), 400);
		}

		// get sender trip hash
		$senderTripCode = $this->messageUtility->getUsertripCode();

		// send the message
		$this->messageService->sendMessage($senderTripCode, $recipient, $name, $subject, $body);

		// redirect back to inbox
		redirect($this->messageUtility->getModulePageURL());
		exit();
	}

	private function handleLogin(): void {
		// get tripcode from form input
		$tripCodeInput = $_POST['tripcode'] ?? '';

		// validate that it's not empty and is a valid tripcode
		if (empty($tripCodeInput) || !$this->messageUtility->isValidTripCodeInput($tripCodeInput)) {
			throw new BoardException(_T('pm_invalid_tripcode'), 400);
		}

		// log the user in by setting a cookie with their tripcode
		$this->messageUtility->logInUser($tripCodeInput);

		// redirect to inbox
		redirect($this->messageUtility->getModulePageURL());
		exit();
	}

	public function handlePostRequest(): void {
		// determine which action
		$action = $_POST['action'] ?? '';

		// check if user is logged in
		$tripAuthorized = $this->messageUtility->isLoggedIn();

		// if they aren't and the action isn't login - then throw a 403
		if (!$tripAuthorized && $action !== 'tripLogin') {
			throw new BoardException(_T('pm_login_required'), 403);
		}

		// handle login action
		else if($action === 'tripLogin') {
			$this->handleLogin();
			return;
		}

		// handle logout action
		else if($action === 'tripLogout') {
			$this->handleLogout();
			return;
		}

		// handle write message action
		else if($action === 'submitPm') {
			$this->handleWriteMessage();
			return;
		}
		else {
			throw new BoardException(_T('page_not_found'), 404);
		}
	}

	private function handleLoginPage(): void {
		$this->messageRenderer->renderLoginPage($this->messageUtility->getModulePageURL());
	}

	private function handleLogout(): void {
		$this->messageUtility->logoutUser();
		redirect($this->messageUtility->getModulePageURL());
		exit();
	}

	public function handleGetRequest(): void {
		// determine which page to show
		$pageName = $_GET['pageName'] ?? '';

		// check if user is logged in
		$tripAuthorized = $this->messageUtility->isLoggedIn();

		// if they aren't - then handle the login page
		if (!$tripAuthorized) {
			$this->handleLoginPage();
			return;
		}
		// check if user is authorized to view PMs
		if($pageName === '') {
			$this->handleInboxPage();
			return;
		}
		// if the page doesn't match any of the above, throw a 404
		 else {
			throw new BoardException(_T('page_not_found'), 404);
		}
	}
}