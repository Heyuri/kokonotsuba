<?php

declare(strict_types=1);

/**
 * Move the old banner storage into the merged banner module.
 *
 * Two things happened when fullBanner and banner became one module: uploaded banners moved from
 * global/fullbanners/ to global/banners/, and board banners stopped being loose files under the
 * static image/banner directory and became rows like every other banner. `php
 * Utilities/migrate-cli.php up` carries both across as part of the upgrade; this is the
 * hand-driven version (Kokonotsuba\Modules\banner\bannerImporter), mainly for a dry run first.
 * The static files are copied, not moved, so the old directory is left as it was.
 *
 *   php Utilities/banner-import-cli.php [--dry-run] [--no-color]
 *
 * Safe to run twice: a board banner whose file name is already in the table is skipped.
 */

use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\Modules\banner\bannerImporter;
use Kokonotsuba\Modules\banner\bannerPresetRegistry;

use function Kokonotsuba\Modules\banner\getBannerRepository;
use function Kokonotsuba\Modules\banner\getBannerStorageDir;

if (PHP_SAPI !== 'cli') {
	http_response_code(403);
	exit("This script must be run from the command line.\n");
}

$rootDir = dirname(__DIR__);

require $rootDir.'/paths.php';
require $rootDir.'/autoload.php';
require $rootDir.'/code/Kokonotsuba/constants.php';
require $rootDir.'/module/banner/bannerPreset.php';
require $rootDir.'/module/banner/bannerPresetRegistry.php';
require $rootDir.'/module/banner/bannerEntry.php';
require $rootDir.'/module/banner/bannerRepository.php';
require $rootDir.'/module/banner/bannerService.php';
require $rootDir.'/module/banner/bannerLib.php';
require $rootDir.'/module/banner/bannerImporter.php';

$argumentList = array_slice($argv, 1);
$dryRun = in_array('--dry-run', $argumentList, true);
$useColor = !in_array('--no-color', $argumentList, true) && getenv('NO_COLOR') === false;

$paint = static function (string $text, string $color) use ($useColor): string {
	if (!$useColor) {
		return $text;
	}

	$codes = ['red' => '0;31', 'green' => '0;32', 'yellow' => '0;33', 'dim' => '0;90'];

	return "\033[{$codes[$color]}m{$text}\033[0m";
};

databaseConnection::createInstance(getDatabaseSettings());

$config = getTemplateConfigArray();

$boardPreset = bannerPresetRegistry::fromConfig(
	static fn (string $key, mixed $default): mixed => $config['modules']['banner'][$key] ?? $default
)->get(bannerPresetRegistry::BOARD);

$importer = new bannerImporter(
	getBannerRepository(),
	$boardPreset,
	getBannerStorageDir(),
	getBackendGlobalDir().'fullbanners/',
	rtrim((string) ($config['STATIC_PATH'] ?? ''), '/').'/image/banner/'
);

if (!$importer->hasSources()) {
	echo "No legacy banner files found.\n";
	exit(0);
}

try {
	$result = $importer->import($dryRun, static function (string $message): void {
		echo $message."\n";
	});
} catch (Throwable $e) {
	exit($paint($e->getMessage()."\n", 'red'));
}

echo $paint("Moved {$result['moved']} uploaded banner(s) into ".getBannerStorageDir()."\n", 'green');
echo $paint("Imported {$result['imported']} board banner(s), skipped {$result['skipped']} already present\n", 'green');

if ($result['odd'] > 0) {
	echo $paint("{$result['odd']} imported banner(s) do not match the board banner preset's dimensions\n", 'yellow');
}

if ($dryRun) {
	echo $paint("Dry run: nothing was written\n", 'yellow');
}
