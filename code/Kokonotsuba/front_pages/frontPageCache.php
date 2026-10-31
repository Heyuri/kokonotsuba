<?php

namespace Kokonotsuba\front_pages;

use function Puchiko\writeFileAtomically;

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
		writeFileAtomically($this->path($key), $html);
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
