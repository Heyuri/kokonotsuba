<?php

use Kokonotsuba\config\configRepository;
use Kokonotsuba\config\legacyConfigImporter;
use Kokonotsuba\migrations\migration;
use Kokonotsuba\migrations\migrationContext;

/**
 * Carry a pre-database install's config files into board_configs.
 *
 * global/globalBoardConfig.php and global/board-configs/board-{uid}.php are gone from the
 * codebase but still on disk in an install that predates the config editor. Nothing to do when
 * they are absent. A scope that already has overrides is left alone, since the panel may have
 * been used since; Utilities/migrateBoardConfigs-cli.php replaces them wholesale if that is wanted.
 */
return new class extends migration {
	public function description(): string {
		return 'Import legacy board config files into board_configs';
	}

	public function up(migrationContext $ctx): void {
		$importer = $this->importer($ctx);

		if (!$importer->hasSources()) {
			return;
		}

		$ctx->note('reading ' . $importer->globalConfigFile());
		$plan = $importer->load();

		if ($ctx->isDryRun()) {
			$ctx->note('would write the global config and ' . count($plan['boards']) . ' board(s)');
			return;
		}

		$result = $importer->write($plan, true, fn(string $m) => $ctx->note($m));
		$ctx->note("{$result['written']} scope(s) written, {$result['kept']} left as they were");
	}

	/** Imported overrides are ordinary rows now, and the files are still on disk: nothing to undo. */
	public function down(migrationContext $ctx): void {}

	public function detect(migrationContext $ctx): ?bool {
		return !$this->importer($ctx)->hasSources();
	}

	private function importer(migrationContext $ctx): legacyConfigImporter {
		return new legacyConfigImporter(
			new configRepository($ctx->getConnection(), $ctx->table('BOARD_CONFIG_TABLE')),
			$ctx->appRoot . '/global/',
			legacyConfigImporter::boardConfigFilesFromDatabase($ctx->getConnection(), $ctx->table('BOARD_TABLE'))
		);
	}
};
