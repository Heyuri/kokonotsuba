<?php

/*
 * Constants and enums for Kokonotsuba!
 *
 * This file is strictly for constants or unchanging/non-configurable
 * values that are to be accessible globally, regardless of board or
 * configuration. Do not add configurations to this file.
 */
namespace Kokonotsuba\Root\Constants;


/* Constants */
const GLOBAL_BOARD_UID = -1; // number that corrosponds to all boards


/* Enums */

// account role
enum userRole: int {
	case LEV_NONE = 0;
	case LEV_USER = 1;
	case LEV_JANITOR = 2;
	case LEV_MODERATOR = 3;
	case LEV_ADMIN = 4;
	case LEV_SYSTEM = 5;

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
			self::LEV_MODERATOR => 'Moderator',
			self::LEV_JANITOR => 'Janitor',
			self::LEV_USER => 'User',
			self::LEV_NONE => 'None',
		};
	}

}