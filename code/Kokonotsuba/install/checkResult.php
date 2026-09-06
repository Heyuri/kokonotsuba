<?php

namespace Kokonotsuba\install;

/**
 * One preflight check outcome.
 *
 * A failure blocks installation; a warning is shown but does not. `fix` is a shell command the
 * user is meant to run, shown verbatim next to the check.
 */
final class checkResult {
	public const OK = 'ok';
	public const WARN = 'warn';
	public const FAIL = 'fail';

	public function __construct(
		public readonly string $group,
		public readonly string $label,
		public readonly string $status,
		public readonly string $detail = '',
		public readonly ?string $fix = null
	) {}

	public static function ok(string $group, string $label, string $detail = ''): self {
		return new self($group, $label, self::OK, $detail);
	}

	public static function warn(string $group, string $label, string $detail = '', ?string $fix = null): self {
		return new self($group, $label, self::WARN, $detail, $fix);
	}

	public static function fail(string $group, string $label, string $detail = '', ?string $fix = null): self {
		return new self($group, $label, self::FAIL, $detail, $fix);
	}

	public function isFailure(): bool {
		return $this->status === self::FAIL;
	}
}
