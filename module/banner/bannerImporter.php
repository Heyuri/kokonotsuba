<?php

namespace Kokonotsuba\Modules\banner;

/**
 * Moves the old banner storage into the merged banner module.
 *
 * Two things happened when fullBanner and banner became one module: uploaded banners moved from
 * global/fullbanners/ to global/banners/, and board banners stopped being loose files under the
 * static image/banner directory and became rows like every other banner. This carries both
 * across; the static files are copied, not moved, so the old directory is left as it was.
 * Safe to run twice: a board banner whose file name is already in the table is skipped.
 */
final class bannerImporter {
	public function __construct(
		private readonly bannerRepository $repository,
		private readonly bannerPreset $boardPreset,
		private readonly string $storageDir,
		private readonly string $legacyStorageDir,
		private readonly string $staticBannerDir
	) {}

	/** Whether either old location still holds files. */
	public function hasSources(): bool {
		return self::filesIn($this->legacyStorageDir) !== [] || self::filesIn($this->staticBannerDir) !== [];
	}

	/**
	 * @param callable(string):void|null $report Receives one line per file.
	 * @return array{moved: int, imported: int, skipped: int, odd: int}
	 */
	public function import(bool $dryRun, ?callable $report = null): array {
		$report ??= static function (string $message): void {};

		if (!$dryRun && !is_dir($this->storageDir) && !mkdir($this->storageDir, 0755, true) && !is_dir($this->storageDir)) {
			throw new \RuntimeException("Could not create {$this->storageDir}");
		}

		return [
			'moved' => $this->moveUploaded($dryRun, $report),
		] + $this->importBoardBanners($dryRun, $report);
	}

	/** Uploaded banners: global/fullbanners/ -> global/banners/ */
	private function moveUploaded(bool $dryRun, callable $report): int {
		$moved = 0;

		foreach (self::filesIn($this->legacyStorageDir) as $path) {
			$destination = $this->storageDir . basename($path);

			if (file_exists($destination)) {
				$report('  already moved: ' . basename($path));
				continue;
			}

			$report('  move ' . basename($path));

			if (!$dryRun && !rename($path, $destination)) {
				$report('  could not move ' . basename($path));
				continue;
			}

			$moved++;
		}

		return $moved;
	}

	/**
	 * Board banners: loose files under the static image/banner directory become rows.
	 *
	 * @return array{imported: int, skipped: int, odd: int}
	 */
	private function importBoardBanners(bool $dryRun, callable $report): array {
		$imported = 0;
		$skipped = 0;
		$odd = 0;

		$files = self::filesIn($this->staticBannerDir);

		if ($files === []) {
			return ['imported' => 0, 'skipped' => 0, 'odd' => 0];
		}

		$existing = $this->repository->getFileNames($this->boardPreset->key);

		foreach ($files as $path) {
			$fileName = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($path));

			if (in_array($fileName, $existing, true)) {
				$skipped++;
				continue;
			}

			$size = @getimagesize($path);
			if ($size !== false && ($size[0] !== $this->boardPreset->width || $size[1] !== $this->boardPreset->height)) {
				$report("  {$fileName} is {$size[0]}x{$size[1]}, not {$this->boardPreset->width}x{$this->boardPreset->height}");
				$odd++;
			}

			$report("  import {$fileName}");

			if ($dryRun) {
				$imported++;
				continue;
			}

			if (!copy($path, $this->storageDir . $fileName)) {
				$report("  could not copy {$fileName}");
				continue;
			}

			$this->repository->insertBanner($this->boardPreset->key, $fileName, null, null, true, true);
			$imported++;
		}

		return ['imported' => $imported, 'skipped' => $skipped, 'odd' => $odd];
	}

	/** @return list<string> */
	private static function filesIn(string $dir): array {
		if ($dir === '' || !is_dir($dir)) {
			return [];
		}

		return array_values(array_filter((array)glob(rtrim($dir, '/') . '/*'), 'is_file'));
	}
}
