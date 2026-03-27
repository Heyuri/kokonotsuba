<?php

namespace Kokonotsuba\Modules\privateMessage;

use Kokonotsuba\database\databaseConnection;

class messageRepository {
	public function __construct(
		private databaseConnection $databaseConnection,
		private string $privateMessageTable,
	) {}

	public function sendMessage(
		string $senderTripCode, 
		string $recipientTripCode, 
		string $senderName,
		string $messageSubject, 
		string $messageBody
	): void {
		// query to insert the message into the database
		$query = "INSERT INTO {$this->privateMessageTable} 
				(sender_tripcode, sender_name, recipient_tripcode, message_subject, message_body) 
				VALUES (:sender_tripcode, :sender_name, :recipient_tripcode, :message_subject, :message_body)";

		// parameters
		$params = [
			':sender_tripcode' => $senderTripCode,
			':sender_name' => $senderName,
			':recipient_tripcode' => $recipientTripCode,
			':message_subject' => $messageSubject,
			':message_body' => $messageBody,
		];

		// insert the message into the database
		$this->databaseConnection->execute($query, $params);
	}

	public function deleteMessage(int $messageId): void {
		// query to delete the message from the database
		$query = "DELETE FROM {$this->privateMessageTable} WHERE id = :message_id";
		
		// parameters
		$params = [':message_id' => $messageId];
		
		// delete the message from the database
		$this->databaseConnection->execute($query, $params);
	}

	public function getMessagesForTripCode(string $tripCode, int $offset, int $limit): array {
		// query to get messages for the given trip hash
		$query = "SELECT * FROM {$this->privateMessageTable} 
				WHERE sender_tripcode = :trip_hash OR recipient_tripcode = :trip_hash
				ORDER BY timestamp DESC";

		// add limit and offset to query
		$query .= " LIMIT $limit OFFSET $offset";

		// parameters
		$params = [':trip_hash' => $tripCode];

		// execute the query and return the results
		return $this->databaseConnection->fetchAllAsArray($query, $params);
	}

	public function getLatestUniqueMessages(string $recipientTripCode): array {
		// query to get the latest unique message from each sender
		$query = "
			SELECT 
				id, 
				sender_tripcode, 
				recipient_tripcode, 
				message_body, 
				message_subject, 
				date_sent, 
				is_read, 
				date_sent
			FROM (
				SELECT 
					id, 
					sender_tripcode, 
					recipient_tripcode, 
					message_body, 
					message_subject, 
					date_sent, 
					is_read, 
					date_sent,
					ROW_NUMBER() OVER (
						PARTITION BY sender_tripcode 
						ORDER BY date_sent DESC, id DESC
					) as rn
				FROM {$this->privateMessageTable}
				WHERE recipient_tripcode = :recipient_tripcode
			) sub
			WHERE rn = 1
			ORDER BY date_sent DESC
		";
		// parameters
		$params = [':recipient_tripcode' => $recipientTripCode];

		// execute the query and return the results
		return $this->databaseConnection->fetchAllAsArray($query, $params);
	}
}