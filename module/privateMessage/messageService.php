<?php

namespace Kokonotsuba\Modules\privateMessage;

class messageService {
	public function __construct(
		private messageRepository $messageRepository,
	) {}

	public function getContactsForUser(string $recipientTripCode): false|array {
		// fetch the latest unique message from each sender
		$latestMessages = $this->messageRepository->getLatestUniqueMessages($recipientTripCode);
	
		// now return them
		return $latestMessages;
	}

	public function sendMessage(
		string $senderTripCode, 
		string $recipientTripCode,
		string $senderName, 
		string $messageSubject, 
		string $messageBody
	): void {
		$this->messageRepository->sendMessage(
			$senderTripCode, 
			$recipientTripCode, 
			$senderName, 
			$messageSubject, 
			$messageBody
		);
	}

	public function getMessagesForTripCode(string $tripCode, int $page = 0, int $messagesPerPage = 10): false|array {
		// calculate pagination parameters
		$offset = $page * $messagesPerPage;
		$limit = $messagesPerPage;
		
		// get all messages for the trip code
		$messages  = $this->messageRepository->getMessagesForTripCode($tripCode, $offset, $limit);

		// return messages
		return $messages;
	}
}