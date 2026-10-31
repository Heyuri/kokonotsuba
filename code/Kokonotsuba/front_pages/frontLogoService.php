<?php

namespace Kokonotsuba\front_pages;

use Kokonotsuba\action_log\actionLoggerService;
use Kokonotsuba\config\configService;
use Kokonotsuba\error\BoardException;

use function Kokonotsuba\libraries\_T;

use const Kokonotsuba\GLOBAL_BOARD_UID;

/**
 * The front page logo: an uploaded image stored under STATIC_PATH/image/front/ and pointed to by
 * SITE.LOGO. Replaced and removed logos stay on disk; only the setting changes.
 */
final class frontLogoService {
	public const MAX_BYTES = 2 * 1024 * 1024;

	/** Image types taken, and the extension each is stored with. SVG is left out: it can carry script. */
	private const TYPES = [
		IMAGETYPE_PNG  => 'png',
		IMAGETYPE_GIF  => 'gif',
		IMAGETYPE_JPEG => 'jpg',
		IMAGETYPE_WEBP => 'webp',
	];

	public function __construct(
		private readonly configService $configService,
		private readonly actionLoggerService $actionLoggerService,
		private readonly frontPageCache $frontPageCache,
		private readonly string $staticPath,
		private readonly string $staticUrl,
	) {}

	/**
	 * Stores an upload as logo-<hash>.<ext> and makes it the logo.
	 *
	 * @param array|null $file The upload's $_FILES entry.
	 * @return string The new logo's URL.
	 */
	public function upload(?array $file): string {
		if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
			throw new BoardException(_T('front_logo_missing'), 400);
		}
		if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
			throw new BoardException(_T('front_logo_failed'), 400);
		}
		if ($file['size'] > self::MAX_BYTES) {
			throw new BoardException(_T('front_logo_too_big', self::MAX_BYTES / 1024), 400);
		}

		$info = @getimagesize($file['tmp_name']);
		$extension = $info === false ? null : (self::TYPES[$info[2]] ?? null);
		if ($extension === null) {
			throw new BoardException(_T('front_logo_bad_type'), 400);
		}

		$directory = $this->staticPath . 'image/front/';
		if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
			throw new BoardException(_T('front_logo_failed'), 500);
		}

		$fileName = 'logo-' . substr(hash_file('sha256', $file['tmp_name']), 0, 16) . '.' . $extension;
		if (!is_file($directory . $fileName) && !move_uploaded_file($file['tmp_name'], $directory . $fileName)) {
			throw new BoardException(_T('front_logo_failed'), 500);
		}

		$url = $this->staticUrl . 'image/front/' . $fileName;
		$this->configService->setOverride(GLOBAL_BOARD_UID, 'SITE.LOGO', $url);
		$this->frontPageCache->forgetAll();
		$this->actionLoggerService->logAction("Changed the front page logo to {$fileName}", GLOBAL_BOARD_UID, frontPageActionType::LOGO);

		return $url;
	}

	/** No logo; the file stays where it was. */
	public function remove(): void {
		$this->configService->unsetOverride(GLOBAL_BOARD_UID, 'SITE.LOGO');
		$this->frontPageCache->forgetAll();
		$this->actionLoggerService->logAction('Removed the front page logo', GLOBAL_BOARD_UID, frontPageActionType::LOGO);
	}
}
