<?php

namespace Kokonotsuba\ban;

/** One appeal against one ban, joined to the ban it argues with. */
class banAppeal {
	private function __construct(
		public readonly int $id,
		public readonly int $banId,
		public readonly string $appellantIp,
		public readonly string $reason,
		public readonly banAppealStatus $status,
		public readonly int $filedAt,
		public readonly ?int $actionedAt,
		public readonly ?int $actionedBy,
		public readonly ?string $actionedByUsername,
		public readonly string $staffNote,
		public readonly ?string $banIpPattern,
		public readonly ?string $banReason,
		public readonly ?string $boardTitle,
	) {}

	public static function fromRow(array $row): self {
		return new self(
			(int) $row['appeal_id'],
			(int) $row['ban_id'],
			(string) $row['appellant_ip'],
			(string) ($row['reason'] ?? ''),
			banAppealStatus::fromValue($row['status'] ?? 0),
			(int) (strtotime((string) ($row['filed_at'] ?? '')) ?: 0),
			self::toTimestamp($row['actioned_at'] ?? null),
			isset($row['actioned_by']) && $row['actioned_by'] !== null ? (int) $row['actioned_by'] : null,
			($row['actioned_by_username'] ?? null) !== null ? (string) $row['actioned_by_username'] : null,
			(string) ($row['staff_note'] ?? ''),
			($row['ban_ip_pattern'] ?? null) !== null ? (string) $row['ban_ip_pattern'] : null,
			($row['ban_reason'] ?? null) !== null ? (string) $row['ban_reason'] : null,
			($row['board_title'] ?? null) !== null ? (string) $row['board_title'] : null,
		);
	}

	private static function toTimestamp(mixed $value): ?int {
		if ($value === null || $value === '') {
			return null;
		}

		$timestamp = strtotime((string) $value);

		return $timestamp === false ? null : $timestamp;
	}
}
