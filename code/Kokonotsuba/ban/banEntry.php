<?php

namespace Kokonotsuba\ban;

/**
 * One row of the ban table, with the questions the ban system actually asks of it.
 *
 * Dates come out of MariaDB as DATETIME strings; they are converted once here so everything
 * downstream deals in unix timestamps, which is what postDateFormatter takes.
 */
class banEntry {
	private function __construct(
		public readonly int $id,
		public readonly int $boardUid,
		public readonly string $ipPattern,
		public readonly bool $isWildcard,
		public readonly ?string $visitorTokenHash,
		public readonly ?int $postUid,
		public readonly string $reason,
		public readonly string $publicReason,
		public readonly string $privateReason,
		/** @var list<string> */
		public readonly array $checkpoints,
		public readonly bool $isWarning,
		public readonly bool $isMute,
		public readonly bool $rejectsAppeals,
		public readonly int $filedAt,
		public readonly ?int $expiresAt,
		public readonly ?int $filedBy,
		public readonly ?string $filedByUsername,
		public readonly ?int $seenAt,
		public readonly ?bool $seenWithCookies,
		public readonly ?int $expirySeenAt,
		public readonly ?int $revokedAt,
		public readonly ?int $revokedBy,
		public readonly ?string $revokedByUsername,
		public readonly ?int $postNumber,
		public readonly ?string $boardTitle,
		public readonly int $pendingAppealCount,
		public readonly int $appealCount,
	) {}

	public static function fromRow(array $row): self {
		$checkpoints = array_values(array_filter(
			array_map('trim', explode(',', (string) ($row['checkpoints'] ?? ''))),
			fn(string $key): bool => $key !== ''
		));

		return new self(
			(int) $row['ban_id'],
			(int) $row['board_uid'],
			(string) $row['ip_pattern'],
			(bool) ($row['is_wildcard'] ?? false),
			($row['visitor_token_hash'] ?? null) !== null ? (string) $row['visitor_token_hash'] : null,
			isset($row['post_uid']) && $row['post_uid'] !== null ? (int) $row['post_uid'] : null,
			(string) ($row['reason'] ?? ''),
			(string) ($row['public_reason'] ?? ''),
			(string) ($row['private_reason'] ?? ''),
			$checkpoints,
			(bool) ($row['is_warning'] ?? false),
			(bool) ($row['is_mute'] ?? false),
			(bool) ($row['rejects_appeals'] ?? false),
			self::toTimestamp($row['filed_at'] ?? null) ?? 0,
			self::toTimestamp($row['expires_at'] ?? null),
			isset($row['filed_by']) && $row['filed_by'] !== null ? (int) $row['filed_by'] : null,
			($row['filed_by_username'] ?? null) !== null ? (string) $row['filed_by_username'] : null,
			self::toTimestamp($row['seen_at'] ?? null),
			($row['seen_cookies'] ?? null) === null ? null : (bool) $row['seen_cookies'],
			self::toTimestamp($row['expiry_seen_at'] ?? null),
			self::toTimestamp($row['revoked_at'] ?? null),
			isset($row['revoked_by']) && $row['revoked_by'] !== null ? (int) $row['revoked_by'] : null,
			($row['revoked_by_username'] ?? null) !== null ? (string) $row['revoked_by_username'] : null,
			isset($row['post_number']) && $row['post_number'] !== null ? (int) $row['post_number'] : null,
			($row['board_title'] ?? null) !== null ? (string) $row['board_title'] : null,
			(int) ($row['pending_appeal_count'] ?? 0),
			(int) ($row['appeal_count'] ?? 0),
		);
	}

	private static function toTimestamp(mixed $value): ?int {
		if ($value === null || $value === '' || $value === '0000-00-00 00:00:00') {
			return null;
		}

		$timestamp = strtotime((string) $value);

		return $timestamp === false ? null : $timestamp;
	}

	/** A ban with no expiry that isn't a warning runs until someone lifts it. */
	public function isPermanent(): bool {
		return !$this->isWarning && $this->expiresAt === null;
	}

	public function isRevoked(): bool {
		return $this->revokedAt !== null;
	}

	public function isExpired(int $now): bool {
		return $this->expiresAt !== null && $this->expiresAt <= $now;
	}

	public function hasBeenSeen(): bool {
		return $this->seenAt !== null;
	}

	/** Whether the banned party has been told this ban ran out. */
	public function hasSeenExpiryNotice(): bool {
		return $this->expirySeenAt !== null;
	}

	/**
	 * Whether this ban has lapsed but still owes its notice.
	 *
	 * A ban is not over until whoever it stopped has been told it is: the next thing they try is
	 * interrupted once more to say so, and only then does the row stop holding anything. Mutes
	 * and warnings are excluded - a mute is thrown away once it lapses, and a warning never had
	 * an expiry to announce.
	 */
	public function awaitsExpiryNotice(int $now): bool {
		return !$this->isRevoked()
			&& !$this->isWarning
			&& !$this->isMute
			&& $this->isExpired($now)
			&& !$this->hasSeenExpiryNotice();
	}

	/** Whether this row still has any force behind it. */
	public function isActive(int $now): bool {
		return !$this->isRevoked() && !$this->isExpired($now);
	}

	public function blocks(string $checkpointKey): bool {
		return in_array($checkpointKey, $this->checkpoints, true);
	}

	/** Seconds until expiry, or null when there is no expiry to count down to. */
	public function secondsRemaining(int $now): ?int {
		return $this->expiresAt === null ? null : max(0, $this->expiresAt - $now);
	}

	/** Coarse state, used for the status column's label and its dimming class. */
	public function statusKey(int $now): string {
		if ($this->isRevoked()) {
			return 'revoked';
		}

		if ($this->isExpired($now)) {
			return 'expired';
		}

		if ($this->isWarning) {
			return 'warning';
		}

		if ($this->isMute) {
			return 'mute';
		}

		return 'active';
	}

	/**
	 * Whether an appeal can still be filed against this row.
	 *
	 * Mutes are excluded along with warnings: a mute is minutes long and is thrown away once it
	 * lapses, so an appeal against one would outlive the thing it argues with. A ban can also
	 * refuse appeals outright, which the moderator opts into per ban.
	 */
	public function isAppealable(int $now): bool {
		return !$this->isWarning
			&& !$this->isMute
			&& !$this->rejectsAppeals
			&& $this->isActive($now);
	}
}
