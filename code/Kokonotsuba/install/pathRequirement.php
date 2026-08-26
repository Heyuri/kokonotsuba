<?php

namespace Kokonotsuba\install;

/** One directory the install needs, and what kind of access it needs to it. */
final class pathRequirement {
	public function __construct(
		public readonly string $path,
		public readonly string $label,
		public readonly string $purpose,
		public readonly bool $needsWrite,
		public readonly bool $createIfMissing = false
	) {}
}
