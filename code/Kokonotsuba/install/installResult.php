<?php

namespace Kokonotsuba\install;

/** What the install did, step by step, and what the user has to do next. */
final class installResult {
	/** @var list<installStep> */
	private array $steps = [];

	private bool $failed = false;

	private string $boardUrl = '';

	private string $boardPath = '';

	/** @var list<string> */
	private array $followUpCommands = [];

	public function add(installStep $step): void {
		$this->steps[] = $step;

		if ($step->status === installStep::FAIL) {
			$this->failed = true;
		}
	}

	/** @return list<installStep> */
	public function steps(): array {
		return $this->steps;
	}

	public function succeeded(): bool {
		return !$this->failed;
	}

	public function setBoard(string $url, string $path): void {
		$this->boardUrl = $url;
		$this->boardPath = $path;
	}

	public function boardUrl(): string {
		return $this->boardUrl;
	}

	public function boardPath(): string {
		return $this->boardPath;
	}

	public function addFollowUpCommand(string $command): void {
		$this->followUpCommands[] = $command;
	}

	/** @return list<string> */
	public function followUpCommands(): array {
		return $this->followUpCommands;
	}

	/** The first failing step, which is the one that stopped the install. */
	public function failure(): ?installStep {
		foreach ($this->steps as $step) {
			if ($step->status === installStep::FAIL) {
				return $step;
			}
		}

		return null;
	}
}
