<?php

namespace Kokonotsuba\front_pages;

/**
 * Rendered front page views for visitors, one file per view. Forgotten as a whole whenever
 * anything they draw changes; never written or read for staff, who see the edit bar.
 */
final class frontPageCache {
	public function __construct(private readonly string $directory) {}

	/** @param string[] $dependencies Files the view draws (e.g. the global message); one newer than the cache makes it stale. */
	public function get(string $key, array $dependencies = []): ?string {
		$file = $this->path($key);
		$cachedAt = @filemtime($file);
		if ($cachedAt === false) {
			return null;
		}

		foreach ($dependencies as $dependency) {
			$changedAt = @filemtime($dependency);
			if ($changedAt !== false && $changedAt >= $cachedAt) {
				return null;
			}
		}

		$html = @file_get_contents($file);

		return $html === false ? null : $html;
	}

	public function put(string $key, string $html): void {
		if (!is_dir($this->directory) && !@mkdir($this->directory, 0755, true) && !is_dir($this->directory)) {
			return;
		}

		// written beside and renamed, so a concurrent reader never sees half a page
		$file = $this->path($key);
		$temporary = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
		if (@file_put_contents($temporary, $html) !== false) {
			@rename($temporary, $file);
		}
	}

	public function forgetAll(): void {
		foreach (glob($this->directory . '*.html') ?: [] as $file) {
			@unlink($file);
		}
	}

	/** The site's cache, global/front-page-cache/. */
	public static function forSite(): self {
		return new self(getBackendGlobalDir() . 'front-page-cache/');
	}

	private function path(string $key): string {
		return $this->directory . hash('xxh128', $key) . '.html';
	}
}
