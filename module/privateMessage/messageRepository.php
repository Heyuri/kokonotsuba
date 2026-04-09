<?php

namespace Kokonotsuba\Modules\privateMessage;

use Kokonotsuba\database\baseRepository;
use Kokonotsuba\database\databaseConnection;

class messageRepository extends baseRepository {
	public function __construct(
		databaseConnection $databaseConnection,
		string $privateMessageTable,
	) {
		parent::__construct($databaseConnection, $privateMessageTable);
	}

	public function sendMessage(
		string $senderTripCode, 
		string $recipientTripCode, 
		string $senderName,
		string $messageSubject, 
		string $messageBody,
		string $ipAddress
	): void {
		$this->insert([
			'sender_tripcode' => $senderTripCode,
			'sender_name' => $senderName,
			'recipient_tripcode' => $recipientTripCode,
			'message_subject' => $messageSubject,
			'message_body' => $messageBody,
			'ip_address' => $ipAddress,
			'date_sent' => date('Y-m-d H:i:s'),
			'is_read' => 0,
		]);
	}

	public function deleteMessage(int $messageId): void {
		$this->deleteWhere('id', $messageId);
	}

	public function getMessagesForTripCode(string $tripCode, string $contactTrip, int $offset, int $limit): array {
		$query = "SELECT * FROM {$this->table} 
				WHERE (sender_tripcode = :trip_sender AND recipient_tripcode = :contact_recipient) 
				   OR (sender_tripcode = :contact_sender AND recipient_tripcode = :trip_recipient)
				ORDER BY date_sent ASC";

		$params = [
			':trip_sender' => $tripCode,
			':contact_recipient' => $contactTrip,
			':contact_sender' => $contactTrip,
			':trip_recipient' => $tripCode,
		];

		$this->paginate($query, $params, $limit, $offset);
		return $this->queryAll($query, $params);
	}

	public function getConversationCount(string $tripCode, string $contactTrip): int {
		return $this->count(
			"(sender_tripcode = :trip_sender AND recipient_tripcode = :contact_recipient) 
			 OR (sender_tripcode = :contact_sender AND recipient_tripcode = :trip_recipient)",
			[
				':trip_sender' => $tripCode,
				':contact_recipient' => $contactTrip,
				':contact_sender' => $contactTrip,
				':trip_recipient' => $tripCode,
			]
		);
	}

	public function getLatestUniqueMessages(string $userTripCode): array {
		$query = "
			SELECT 
				id, 
				sender_tripcode, 
				sender_name,
				recipient_tripcode, 
				message_body, 
				message_subject, 
				date_sent, 
				is_read,
				contact_tripcode
			FROM (
				SELECT 
					id, 
					sender_tripcode, 
					sender_name,
					recipient_tripcode, 
					message_body, 
					message_subject, 
					date_sent, 
					is_read,
					CASE 
						WHEN sender_tripcode = :user_trip_case THEN recipient_tripcode 
						ELSE sender_tripcode 
					END as contact_tripcode,
					ROW_NUMBER() OVER (
						PARTITION BY CASE 
							WHEN sender_tripcode = :user_trip_partition THEN recipient_tripcode 
							ELSE sender_tripcode 
						END
						ORDER BY date_sent DESC, id DESC
					) as rn
				FROM {$this->table}
				WHERE sender_tripcode = :user_trip_sender 
				   OR recipient_tripcode = :user_trip_recipient
			) sub
			WHERE rn = 1
			ORDER BY date_sent DESC
		";

		$params = [
			':user_trip_case' => $userTripCode,
			':user_trip_partition' => $userTripCode,
			':user_trip_sender' => $userTripCode,
			':user_trip_recipient' => $userTripCode,
		];

		return $this->queryAll($query, $params);
	}

	public function markMessagesAsRead(string $recipientTripCode, string $senderTripCode): void {
		$this->query(
			"UPDATE {$this->table} SET is_read = 1 
			 WHERE recipient_tripcode = :recipient AND sender_tripcode = :sender AND is_read = 0",
			[':recipient' => $recipientTripCode, ':sender' => $senderTripCode]
		);
	}

	public function getAllMessagesForUser(string $userTripCode, int $offset, int $limit): array {
		$query = "SELECT * FROM {$this->table} 
				WHERE sender_tripcode = :trip_sender 
				   OR recipient_tripcode = :trip_recipient
				ORDER BY date_sent DESC";

		$params = [
			':trip_sender' => $userTripCode,
			':trip_recipient' => $userTripCode,
		];

		$this->paginate($query, $params, $limit, $offset);
		return $this->queryAll($query, $params);
	}

	public function getAllMessagesCountForUser(string $userTripCode): int {
		return $this->count(
			"sender_tripcode = :trip_sender OR recipient_tripcode = :trip_recipient",
			[':trip_sender' => $userTripCode, ':trip_recipient' => $userTripCode]
		);
	}

	public function getMessageById(int $messageId): ?array {
		return $this->findBy('id', $messageId);
	}

	public function markMessageAsRead(int $messageId): void {
		$this->updateWhere(['is_read' => 1], 'id', $messageId);
	}

	public function getAllMessages(int $offset, int $limit): array {
		$query = "SELECT * FROM {$this->table} ORDER BY date_sent DESC";
		$params = [];
		$this->paginate($query, $params, $limit, $offset);
		return $this->queryAll($query, $params);
	}

	public function getAllMessagesCount(): int {
		return $this->count();
	}

	public function getUnreadMessageCount(string $recipientTripCode): int {
		return $this->count(
			"recipient_tripcode = :recipient AND is_read = 0",
			[':recipient' => $recipientTripCode]
		);
	}

	public function deleteMessages(array $ids): void {
		$this->deleteWhereIn('id', $ids);
	}
}