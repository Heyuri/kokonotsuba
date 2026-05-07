<?php

namespace Kokonotsuba\Modules\privateMessage;

class messageService {
	public function __construct(
		private messageRepository $messageRepository,
	) {}

	public function getContactsForUser(string $userTripCode): array {
		return $this->messageRepository->getLatestUniqueMessages($userTripCode);
	}

	public function sendMessage(
		string $senderTripCode, 
		string $recipientTripCode,
		string $senderName, 
		string $messageSubject, 
		string $messageBody,
		string $ipAddress
	): void {
		$this->messageRepository->sendMessage(
			$senderTripCode, 
			$recipientTripCode, 
			$senderName, 
			$messageSubject, 
			$messageBody,
			$ipAddress
		);
	}

	public function getMessagesForThread(
		string $userTripCode, 
		string $contactTripCode, 
		int $page = 1, 
		int $messagesPerPage = 20
	): array {
		$offset = ($page - 1) * $messagesPerPage;
		return $this->messageRepository->getMessagesForTripCode(
			$userTripCode, 
			$contactTripCode, 
			$offset, 
			$messagesPerPage
		);
	}

	public function getConversationPageCount(string $userTripCode, string $contactTripCode, int $messagesPerPage = 20): int {
		$count = $this->messageRepository->getConversationCount($userTripCode, $contactTripCode);
		return max(1, (int) ceil($count / $messagesPerPage));
	}

	public function getConversationCount(string $userTripCode, string $contactTripCode): int {
		return $this->messageRepository->getConversationCount($userTripCode, $contactTripCode);
	}

	public function markMessagesAsRead(string $recipientTripCode, string $senderTripCode): void {
		$this->messageRepository->markMessagesAsRead($recipientTripCode, $senderTripCode);
	}

	public function hasConversationWith(string $userTripCode, string $contactTripCode): bool {
		return $this->messageRepository->getConversationCount($userTripCode, $contactTripCode) > 0;
	}

	public function getAllMessagesForUser(string $userTripCode, int $page = 1, int $messagesPerPage = 20): array {
		$offset = ($page - 1) * $messagesPerPage;
		return $this->messageRepository->getAllMessagesForUser($userTripCode, $offset, $messagesPerPage);
	}

	public function getAllMessagesCountForUser(string $userTripCode): int {
		return $this->messageRepository->getAllMessagesCountForUser($userTripCode);
	}

	public function getMessageById(int $messageId): ?array {
		return $this->messageRepository->getMessageById($messageId);
	}

	public function markMessageAsRead(int $messageId): void {
		$this->messageRepository->markMessageAsRead($messageId);
	}

	public function getUnreadMessageCount(string $recipientTripCode): int {
		return $this->messageRepository->getUnreadMessageCount($recipientTripCode);
	}
}