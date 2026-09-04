<?php

use Kokonotsuba\migrations\migration;
use Kokonotsuba\migrations\migrationContext;
use Kokonotsuba\Modules\blotter\legacyBlotterImporter;

/**
 * Import the blotter flat file (global/blotter.txt) into the blotter table.
 */
return new class extends migration {
	public function description(): string {
		return 'Import legacy blotter.txt entries';
	}

	public function up(migrationContext $ctx): void {
		$path = $this->sourcePath($ctx);

		if (!is_file($path)) {
			return;
		}

		require_once $ctx->appRoot . '/module/blotter/legacyBlotterImporter.php';

		$entries = legacyBlotterImporter::parseFile($path);
		$importer = new legacyBlotterImporter($ctx->getConnection(), $ctx->table('BLOTTER_TABLE'));
		$result = $importer->import($entries, $ctx->isDryRun());

		$ctx->note("{$path}: imported {$result['imported']}, already present {$result['skipped']}");
	}

	/** Imported entries are ordinary rows now, and the file is still on disk: nothing to undo. */
	public function down(migrationContext $ctx): void {}

	public function detect(migrationContext $ctx): ?bool {
		return !is_file($this->sourcePath($ctx));
	}

	private function sourcePath(migrationContext $ctx): string {
		return $ctx->appRoot . '/global/blotter.txt';
	}
};
