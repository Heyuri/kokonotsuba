<?php

namespace Kokonotsuba\Modules\lockDown;

use function Puchiko\writeFileAtomically;

/**
 * A board's lock-down marker: a file in its storage directory, present while locked.
 * started_at is UTC, as posts.root is, so the two compare directly.
 */
final class lockDownFlag {
	private const FILE = 'lockdown.json';

	public function __construct(private readonly string $boardStoragePath) {}

	/** ['started_at' => string, 'started_by' => ?string], or null when unlocked. */
	public function read(): ?array {
		$raw = @file_get_contents($this->path());
		if ($raw === false) {
			return null;
		}

		$data = json_decode($raw, true);

		// a marker that will not parse still locks, from the moment it was written
		return [
			'started_at' => (string) ($data['started_at'] ?? gmdate('Y-m-d H:i:s', (int) @filemtime($this->path()))),
			'started_by' => $data['started_by'] ?? null,
		];
	}

	/** Locks the board; an existing lock-down keeps its start. */
	public function start(?string $startedBy): bool {
		if (is_file($this->path())) {
			return true;
		}

		return writeFileAtomically($this->path(), (string) json_encode([
			'started_at' => gmdate('Y-m-d H:i:s'),
			'started_by' => $startedBy,
		]));
	}

	public function end(): void {
		@unlink($this->path());
	}

	private function path(): string {
		return $this->boardStoragePath . self::FILE;
	}
}
