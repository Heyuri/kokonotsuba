<?php

use Kokonotsuba\config\siteSettings;
use Kokonotsuba\migrations\migration;
use Kokonotsuba\migrations\migrationContext;
use Kokonotsuba\Modules\banner\bannerImporter;
use Kokonotsuba\Modules\banner\bannerPresetRegistry;
use Kokonotsuba\Modules\banner\bannerRepository;

/**
 * Move the pre-merge banner storage into the banner module.
 *
 * Uploaded banners go from global/fullbanners/ to global/banners/, and the loose board banner
 * files under the static image/banner directory become rows. Files, so no transaction.
 */
return new class extends migration {
	public function description(): string {
		return 'Import legacy fullbanner uploads and static board banners';
	}

	public function isTransactional(): bool {
		return false;
	}

	public function up(migrationContext $ctx): void {
		// A dry run skips the banner_presets DDL this reads through, so there is nothing to preview yet.
		if ($ctx->isDryRun() && !$ctx->inspector->columnExists($ctx->table('BANNER_AD_TABLE'), 'preset')) {
			$ctx->note('banner_presets is not applied yet; the import runs once it is');
			return;
		}

		$importer = $this->importer($ctx);

		if (!$importer->hasSources()) {
			return;
		}

		$result = $importer->import($ctx->isDryRun(), fn(string $m) => $ctx->note($m));
		$ctx->note("moved {$result['moved']} upload(s); imported {$result['imported']} board banner(s), skipped {$result['skipped']}, {$result['odd']} off-size");
	}

	/** Moved files stay where they are: nothing to undo. */
	public function down(migrationContext $ctx): void {}

	public function detect(migrationContext $ctx): ?bool {
		if (!$ctx->inspector->columnExists($ctx->table('BANNER_AD_TABLE'), 'preset')) {
			return false;
		}

		return !$this->importer($ctx)->hasSources();
	}

	private function importer(migrationContext $ctx): bannerImporter {
		$moduleDir = $ctx->appRoot . '/module/banner/';
		foreach (['bannerPreset', 'bannerPresetRegistry', 'bannerEntry', 'bannerRepository', 'bannerImporter'] as $file) {
			require_once $moduleDir . $file . '.php';
		}

		// Preset dimensions are only used to flag off-size files, so the defaults will do.
		$boardPreset = bannerPresetRegistry::fromConfig(static fn(string $key, mixed $default): mixed => $default)
			->get(bannerPresetRegistry::BOARD);

		$siteSettings = siteSettings::load($ctx->appRoot . '/global/siteSettings.php');
		$staticPath = rtrim((string)($siteSettings['STATIC_PATH'] ?? '/var/www/static/'), '/');

		return new bannerImporter(
			new bannerRepository($ctx->getConnection(), $ctx->table('BANNER_AD_TABLE')),
			$boardPreset,
			$ctx->appRoot . '/global/banners/',
			$ctx->appRoot . '/global/fullbanners/',
			$staticPath . '/image/banner/'
		);
	}
};
