<?php

namespace Kokonotsuba\Modules\threadMod;

/**
 * What a visitor is to a thread. Owner is the OP (or whoever ownership was transferred to);
 * mod is anyone the owner granted. The int is what thread_mods.role stores.
 */
enum threadRole: int {
	case NONE = 0;
	case MOD = 1;
	case OWNER = 2;

	public static function fromStored(mixed $value): self {
		return self::tryFrom((int)$value) ?? self::NONE;
	}

	/** The key kept on the post, in its capcode column and in the opdel flag. */
	public function key(): string {
		return match ($this) {
			self::OWNER => 'owner',
			self::MOD => 'mod',
			self::NONE => '',
		};
	}

	public static function fromKey(string $key): self {
		return match ($key) {
			'owner' => self::OWNER,
			'mod' => self::MOD,
			default => self::NONE,
		};
	}

	public function isAtLeastMod(): bool {
		return $this !== self::NONE;
	}

	public function isOwner(): bool {
		return $this === self::OWNER;
	}
}
