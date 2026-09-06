<?php

namespace Kokonotsuba;

// account role
// Values are spaced so a new role can be slotted in without renumbering the ones above it.
// They are stored in the `role` column of the account and action log tables - changing them
// needs a migration (migrations/20260815_000002_role_levels.php).
enum userRole: int {
	case LEV_NONE = 0;
	case LEV_USER = 10;
	case LEV_JANITOR = 25;
	case LEV_MODERATOR = 50;
	case LEV_MANAGER = 70;
	case LEV_ADMIN = 100;
	case LEV_SYSTEM = 200;

	/**
	 * Check if this role is at least as high as another
	 */
	public function isAtLeast(self $other): bool {
		return $this->value >= $other->value;
	}

	/**
	 * Check if this role is at most as high as another
	 */
	public function isAtMost(self $other): bool {
		return $this->value <= $other->value;
	}

	/**
	 * Check if this role is less than another role
	 */
	public function isLessThan(self $other): bool {
		return $this->value < $other->value;
	}

	/**
	 * Check if this is a staff role (Janitor or higher)
	 */
	public function isStaff(): bool {
		return $this->value >= self::LEV_JANITOR->value;
	}

	/**
	 * Get a human-readable display name
	 */
	public function displayRoleName(): string {
		return match ($this) {
			self::LEV_SYSTEM => 'System',
			self::LEV_ADMIN => 'Admin',
			self::LEV_MANAGER => 'Manager',
			self::LEV_MODERATOR => 'Moderator',
			self::LEV_JANITOR => 'Janitor',
			self::LEV_USER => 'User',
			self::LEV_NONE => 'None',
		};
	}

	/**
	 * Resolve a role integer from the database, a session or a request.
	 * Unknown values (an unmigrated row, a hand-edited session) fall back to LEV_NONE.
	 */
	public static function fromStored(int|string|null $value): self {
		if ($value === null || $value === '') {
			return self::LEV_NONE;
		}

		return self::tryFrom((int)$value) ?? self::LEV_NONE;
	}

	/**
	 * Every role an account can hold, lowest first. Excludes LEV_SYSTEM, which outranks
	 * everything but is never stored on a real account.
	 *
	 * @return self[]
	 */
	public static function accountRoles(): array {
		return [
			self::LEV_NONE,
			self::LEV_USER,
			self::LEV_JANITOR,
			self::LEV_MODERATOR,
			self::LEV_MANAGER,
			self::LEV_ADMIN,
		];
	}

	/**
	 * The promote/demote ladder, lowest first: everything above LEV_NONE.
	 *
	 * @return self[]
	 */
	public static function assignableRoles(): array {
		return array_values(array_filter(
			self::accountRoles(),
			static fn(self $role): bool => $role !== self::LEV_NONE
		));
	}

	/** Whether this role can be given to an account. */
	public function isAssignable(): bool {
		return in_array($this, self::assignableRoles(), true);
	}

	/** The next role up the ladder, or null if already at the top. */
	public function promoted(): ?self {
		if ($this === self::LEV_NONE) {
			return self::LEV_USER;
		}

		$ladder = self::assignableRoles();
		$index = array_search($this, $ladder, true);

		if ($index === false) {
			return null; // LEV_SYSTEM is not on the ladder
		}

		return $ladder[$index + 1] ?? null;
	}

	/** The next role down the ladder, or null if already at LEV_USER. */
	public function demoted(): ?self {
		$ladder = self::assignableRoles();
		$index = array_search($this, $ladder, true);

		if ($index === false || $index === 0) {
			return null;
		}

		return $ladder[$index - 1];
	}

}