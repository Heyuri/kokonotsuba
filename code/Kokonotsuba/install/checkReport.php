<?php

namespace Kokonotsuba\install;

/** An ordered collection of checkResults, grouped for display. */
final class checkReport {
	/** @var list<checkResult> */
	private array $results = [];

	/** @param iterable<checkResult> $results */
	public function addAll(iterable $results): void {
		foreach ($results as $result) {
			$this->results[] = $result;
		}
	}

	public function add(checkResult $result): void {
		$this->results[] = $result;
	}

	/** @return list<checkResult> */
	public function all(): array {
		return $this->results;
	}

	/** @return array<string, list<checkResult>> group name => results, in insertion order */
	public function grouped(): array {
		$grouped = [];

		foreach ($this->results as $result) {
			$grouped[$result->group][] = $result;
		}

		return $grouped;
	}

	/** @return list<checkResult> */
	public function failures(): array {
		return array_values(array_filter($this->results, static fn (checkResult $r): bool => $r->isFailure()));
	}

	/** @return list<checkResult> */
	public function warnings(): array {
		return array_values(array_filter(
			$this->results,
			static fn (checkResult $r): bool => $r->status === checkResult::WARN
		));
	}

	public function hasFailures(): bool {
		return $this->failures() !== [];
	}

	/**
	 * Every distinct fix command from failing checks, in order, so the page can offer them as one
	 * copy-paste block.
	 *
	 * @return list<string>
	 */
	public function fixCommands(): array {
		$commands = [];

		foreach ($this->results as $result) {
			if ($result->fix !== null && $result->fix !== '' && $result->isFailure()) {
				$commands[] = $result->fix;
			}
		}

		return array_values(array_unique($commands));
	}
}
