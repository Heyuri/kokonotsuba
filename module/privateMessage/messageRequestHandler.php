<?php

namespace Kokonotsuba\Modules\privateMessage;

use Kokonotsuba\error\BoardException;
use Kokonotsuba\request\request;

use function Kokonotsuba\libraries\_T;
use function Puchiko\json\sendJsonResponse;
use function Puchiko\request\redirect;
use function Puchiko\strings\strlenUnicode;

class messageRequestHandler {
    public function __construct(
        private messageService $messageService,
        private messagePolicy $messagePolicy,
        private messageRenderer $messageRenderer,
        private messageUtility $messageUtility,
        private request $request,
        private int $inputMax = 100,
        private int $messagesPerPage = 20,
    ) {}

	public function handleInboxPage(): void {
		$tripCode = $this->messageUtility->getUsertripCode();
		$modulePageUrl = $this->messageUtility->getModulePageURL();

		$page = ($this->request->hasParameter('page') && is_numeric($this->request->getParameter('page')))
			? max(0, (int) $this->request->getParameter('page'))
			: 0;

		$messages = $this->messageService->getAllMessagesForUser($tripCode, $page, $this->messagesPerPage);
		$totalEntries = $this->messageService->getAllMessagesCountForUser($tripCode);

		$this->messageRenderer->renderInboxPage(
			$messages,
			$modulePageUrl,
			$tripCode,
			$totalEntries,
			$this->messagesPerPage
		);
	}

	private function handleViewMessage(): void {
		$messageId = (int) $this->request->getParameter('view');
		$tripCode = $this->messageUtility->getUsertripCode();

		$message = $this->messageService->getMessageById($messageId);

		if (!$message) {
			throw new BoardException(_T('pm_message_not_found'), 404);
		}

		// only sender or recipient can view
		if ($message['sender_tripcode'] !== $tripCode && $message['recipient_tripcode'] !== $tripCode) {
			throw new BoardException(_T('pm_no_conversation'), 403);
		}

		// mark as read if this user is the recipient
		if ($message['recipient_tripcode'] === $tripCode && !$message['is_read']) {
			$this->messageService->markMessageAsRead($messageId);
			$message['is_read'] = 1;
		}

		$modulePageUrl = $this->messageUtility->getModulePageURL();
		$this->messageRenderer->renderViewMessage($message, $modulePageUrl, $tripCode);
	}

	public function handleWriteMessage(): void {
		$recipient = trim($this->request->getParameter('recipient', 'POST') ?? '');
		$rawName = trim($this->request->getParameter('name', 'POST') ?? '');
		$subject = trim($this->request->getParameter('subject', 'POST') ?? '');
		$body = trim($this->request->getParameter('body', 'POST') ?? '');

		if (empty($recipient) || empty($body)) {
			throw new BoardException(_T('pm_recipient_and_message_required'), 400);
		}

		if (strlenUnicode($recipient) > $this->inputMax) {
			throw new BoardException(_T('pm_input_too_long'), 400);
		}

		if (strlenUnicode($rawName) > $this->inputMax) {
			throw new BoardException(_T('pm_input_too_long'), 400);
		}

		if (strlenUnicode($subject) > $this->inputMax) {
			throw new BoardException(_T('pm_input_too_long'), 400);
		}

		if (strlenUnicode($body) > $this->inputMax) {
			throw new BoardException(_T('pm_input_too_long'), 400);
		}

		if (!$this->messageUtility->isValidTripCode($recipient)) {
			throw new BoardException(_T('pm_invalid_recipient'), 400);
		}

		$senderTripCode = $this->messageUtility->getUsertripCode();

		if (!$this->messagePolicy->canSendMessage($senderTripCode)) {
			throw new BoardException(_T('pm_login_required'), 403);
		}

		$parsed = $this->messageUtility->parseName($rawName);
		$displayName = $parsed['name'];

		$ipAddress = (string) $this->request->userIp();
		$this->messageService->sendMessage($senderTripCode, $recipient, $displayName, $subject, $body, $ipAddress);

		redirect($this->messageUtility->getModulePageURL());
		exit();
	}

	private function handleLogin(): void {
		$tripCodeInput = $this->request->getParameter('tripcodeLogin', 'POST') ?? '';

		if (empty($tripCodeInput) || !$this->messageUtility->isValidTripCodeInput($tripCodeInput)) {
			throw new BoardException(_T('pm_invalid_tripcode'), 400);
		}

		if (strlenUnicode($tripCodeInput) > $this->inputMax) {
			throw new BoardException(_T('pm_input_too_long'), 400);
		}

		$this->messageUtility->loginUser($tripCodeInput);

		redirect($this->messageUtility->getModulePageURL());
		exit;
	}

	private function handleLogout(): void {
		$this->messageUtility->logoutUser();
		redirect($this->messageUtility->getModulePageURL());
		exit;
	}

	public function handlePostRequest(): void {
		$action = $this->request->getParameter('action', 'POST') ?? '';
		$tripAuthorized = $this->messageUtility->isLoggedIn();

		if ($action === 'tripLogin') {
			$this->handleLogin();
			return;
		}

		if (!$tripAuthorized) {
			throw new BoardException(_T('pm_login_required'), 403);
		}

		switch ($action) {
			case 'tripLogout':
				$this->handleLogout();
				return;
			case 'submitPm':
				$this->handleWriteMessage();
				return;
			default:
				throw new BoardException(_T('page_not_found'), 404);
		}
	}

	private function handleLoginPage(): void {
		$this->messageRenderer->renderLoginPage($this->messageUtility->getModulePageURL());
	}

	public function handleGetRequest(): void {
		if ($this->request->hasParameter('notifications')) {
			$this->handleNotificationsApi();
			return;
		}

		$tripAuthorized = $this->messageUtility->isLoggedIn();

		if (!$tripAuthorized) {
			$this->handleLoginPage();
			return;
		}

		if ($this->request->hasParameter('view')) {
			$this->handleViewMessage();
			return;
		}

		$this->handleInboxPage();
	}

	private function handleNotificationsApi(): void {
		if (!$this->messageUtility->isLoggedIn()) {
			sendJsonResponse(['unreadCount' => 0]);
			return;
		}

		$tripCode = $this->messageUtility->getUsertripCode();
		$unreadCount = $this->messageService->getUnreadMessageCount($tripCode);

		sendJsonResponse([
			'unreadCount' => $unreadCount,
			'title' => _T('pm_notification_title'),
			'body' => _T('pm_notification_body', $unreadCount),
			'url' => $this->messageUtility->getModulePageURL([], true),
		]);
	}
}