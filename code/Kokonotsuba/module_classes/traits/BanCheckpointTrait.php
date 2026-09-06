<?php

namespace Kokonotsuba\module_classes\traits;

use Kokonotsuba\ban\banCheckpoint;
use Kokonotsuba\ban\banEntry;
use Kokonotsuba\ban\banService;

/**
 * Gate a module's action behind a ban checkpoint.
 *
 * Call assertNotBanned() before doing the thing; if a ban blocks that checkpoint the request ends
 * there, showing the ban. Modules with an action of their own can add a checkpoint for it with
 * registerBanCheckpoint() during initialize(), which puts a checkbox for it on the ban form.
 *
 * Requires the using class to extend abstractModule.
 */
trait BanCheckpointTrait {
	protected function getBanService(): banService {
		return $this->moduleContext->banService;
	}

	/**
	 * Stop the request if this visitor is banned from the given checkpoint.
	 *
	 * Scoped to the current board plus the global ban scope unless another board is named.
	 */
	protected function assertNotBanned(banCheckpoint|string $checkpoint, ?int $boardUid = null, ?string $ip = null): void {
		$this->getBanService()->assertNotBanned(
			$checkpoint,
			$boardUid ?? $this->moduleContext->board->getBoardUID(),
			$ip
		);
	}

	/** The ban that would block this checkpoint, without ending the request. */
	protected function findBlockingBan(banCheckpoint|string $checkpoint, ?int $boardUid = null): ?banEntry {
		return $this->getBanService()->findBlockingBan(
			$checkpoint,
			$boardUid ?? $this->moduleContext->board->getBoardUID()
		);
	}

	/** Declare a checkpoint of this module's own. */
	protected function registerBanCheckpoint(string $key, string $label, bool $default = false): void {
		$this->getBanService()->registerCheckpoint($key, $label, $default);
	}
}
