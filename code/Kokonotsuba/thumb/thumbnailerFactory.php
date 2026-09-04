<?php

namespace Kokonotsuba\thumb;

/**
 * Picks the backend that will thumbnail a given file.
 *
 * THUMB_SETTING.Method says which one a board prefers, but preference is not the last
 * word: the other backend is tried when the preferred one cannot read the source. That
 * is what keeps AVIF and oversized stills working on a GD board.
 */
final class thumbnailerFactory {
	/** The methods a board may configure. */
	public const METHODS = ['gd', 'ffmpeg'];

	/**
	 * Methods older installs may still have in THUMB_SETTING. They were dropped along with
	 * the Pixmicat! wrappers; boards holding one fall through to the default order.
	 */
	public const RETIRED_METHODS = ['imagick', 'imagemagick', 'magickwand', 'repng2jpeg'];

	private readonly string $method;

	public function __construct(string $method = 'gd') {
		$this->method = strtolower(trim($method));
	}

	/** Whether the configured method is one that no longer exists. */
	public function isRetiredMethod(): bool {
		return in_array($this->method, self::RETIRED_METHODS, true);
	}

	/**
	 * Backends in the order they should be tried.
	 *
	 * @return thumbnailerInterface[]
	 */
	public function candidates(): array {
		$gd = new gdThumbnailer();
		$ffmpeg = new ffmpegThumbnailer();

		return $this->method === 'ffmpeg' ? [$ffmpeg, $gd] : [$gd, $ffmpeg];
	}

	/** The board's preferred backend, installed or not. */
	public function preferred(): thumbnailerInterface {
		return $this->candidates()[0];
	}

	/** The first installed backend that can read $sourceFile, or null when neither can. */
	public function forSource(string $sourceFile): ?thumbnailerInterface {
		foreach ($this->candidates() as $thumbnailer) {
			if ($thumbnailer->isWorking() && $thumbnailer->supports($sourceFile)) {
				return $thumbnailer;
			}
		}

		return null;
	}

	/**
	 * Installed backends named for the status page, preferred one first.
	 *
	 * @return string[]
	 */
	public function describeInstalled(): array {
		$installed = [];

		foreach ($this->candidates() as $thumbnailer) {
			if ($thumbnailer->isWorking()) {
				$installed[] = $thumbnailer->describe();
			}
		}

		return $installed;
	}
}
