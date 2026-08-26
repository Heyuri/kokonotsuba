<?php

namespace Kokonotsuba\install;

/** One step of the install, as shown on the result page. */
final class installStep {
	public const OK = 'ok';
	public const WARN = 'warn';
	public const FAIL = 'fail';

	public function __construct(
		public readonly string $label,
		public readonly string $status,
		public readonly string $detail = '',
		public readonly ?string $fix = null
	) {}

	public static function ok(string $label, string $detail = ''): self {
		return new self($label, self::OK, $detail);
	}

	public static function warn(string $label, string $detail = ''): self {
		return new self($label, self::WARN, $detail);
	}

	public static function fail(string $label, string $detail = '', ?string $fix = null): self {
		return new self($label, self::FAIL, $detail, $fix);
	}
}
