<?php

use Kokonotsuba\ban\legacyBanImporter;
use Kokonotsuba\migrations\migration;
use Kokonotsuba\migrations\migrationContext;

/**
 * Import the flat-file bans an older install still has on disk into the bans table.
 *
 * Expired bans are left behind; Utilities/ban-import-cli.php --include-expired brings them across
 * if they are wanted for the record.
 */
return new class extends migration {
	public function description(): string {
		return 'Import legacy bans.log.txt / globalbans.log entries';
	}

	public function up(migrationContext $ctx): void {
		$importer = $this->importer($ctx);

		if (!$importer->hasSources()) {
			return;
		}

		$result = $importer->import($ctx->isDryRun(), false, fn(string $m) => $ctx->note($m));
		$ctx->note("imported {$result['imported']}, already present {$result['skipped']}, expired and left behind {$result['expired']}");
	}

	/** Imported bans are ordinary rows now, and the files are still on disk: nothing to undo. */
	public function down(migrationContext $ctx): void {}

	public function detect(migrationContext $ctx): ?bool {
		if (!$ctx->inspector->tableExists($ctx->table('BAN_TABLE'))) {
			return false;
		}

		return !$this->importer($ctx)->hasSources();
	}

	private function importer(migrationContext $ctx): legacyBanImporter {
		return new legacyBanImporter(
			$ctx->getConnection(),
			$ctx->table('BAN_TABLE'),
			$ctx->table('BOARD_TABLE'),
			$ctx->appRoot . '/global/',
			$ctx->appRoot . '/global/board-storages/'
		);
	}
};
