<?php

namespace Kokonotsuba\Modules\banner;

use Kokonotsuba\database\transactionManager;
use Kokonotsuba\database\TransactionalTrait;
use Kokonotsuba\error\BoardException;

use function Kokonotsuba\libraries\_T;

class bannerService {
	use TransactionalTrait;

	private const ALLOWED_EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif'];

	private const ALLOWED_MIME_TYPES = [
		'image/png',
		'image/jpeg',
		'image/gif',
	];

	public function __construct(
		private bannerRepository $bannerRepository,
		private transactionManager $transactionManager,
		private string $storageDir,
	) {}

	public function getApprovedActivePage(string $preset, int $requestedPage, int $entriesPerPage): array {
		$totalEntries = $this->bannerRepository->countApprovedActive($preset);
		[$entriesPerPage, $currentPage, $offset] = $this->calculatePagination($requestedPage, $entriesPerPage, $totalEntries);

		return [
			'items' => $this->bannerRepository->getApprovedActivePaginated($preset, $entriesPerPage, $offset),
			'totalEntries' => $totalEntries,
			'entriesPerPage' => $entriesPerPage,
			'currentPage' => $currentPage,
		];
	}

	/** @param ?string $preset null for every preset at once */
	public function getAllBannersPage(?string $preset, int $requestedPage, int $entriesPerPage): array {
		$totalEntries = $this->bannerRepository->countAll($preset);
		[$entriesPerPage, $currentPage, $offset] = $this->calculatePagination($requestedPage, $entriesPerPage, $totalEntries);

		return [
			'items' => $this->bannerRepository->getAllPaginated($preset, $entriesPerPage, $offset),
			'totalEntries' => $totalEntries,
			'entriesPerPage' => $entriesPerPage,
			'currentPage' => $currentPage,
		];
	}

	public function countPendingBanners(): int {
		return $this->bannerRepository->countPending();
	}

	public function hasBanners(string $preset): bool {
		return $this->bannerRepository->hasApprovedActive($preset);
	}

	public function getRandomActiveBanner(string $preset): ?bannerEntry {
		return $this->bannerRepository->getRandomActive($preset);
	}

	/** A reader's submission: held unapproved and inactive until staff act on it. */
	public function submitBanner(bannerPreset $preset, array $uploadedFile, ?string $link, string $ipAddress): void {
		$this->checkFlood($preset, $ipAddress);
		$this->validateUploadedFile($uploadedFile, $preset);

		$storedFileName = $this->storeFile($uploadedFile);

		$this->inTransaction(function () use ($preset, $storedFileName, $link, $ipAddress) {
			$this->bannerRepository->insertBanner($preset->key, $storedFileName, $link, $ipAddress, false, false);
		});
	}

	/** A staff upload, which goes live immediately. */
	public function adminUploadBanner(bannerPreset $preset, array $uploadedFile, ?string $link): void {
		$this->validateUploadedFile($uploadedFile, $preset);

		$storedFileName = $this->storeFile($uploadedFile);

		$this->inTransaction(function () use ($preset, $storedFileName, $link) {
			$this->bannerRepository->insertBanner($preset->key, $storedFileName, $link, null, true, true);
		});
	}

	public function approveBanners(array $ids): void {
		if (empty($ids)) return;

		$this->inTransaction(function () use ($ids) {
			$this->bannerRepository->approveBanners($ids);
		});
	}

	public function deleteBanners(array $ids): void {
		if (empty($ids)) return;

		$fileNames = [];

		$this->inTransaction(function () use ($ids, &$fileNames) {
			$fileNames = $this->bannerRepository->deleteBanners($ids);
		});

		// Delete physical files after successful DB deletion
		foreach ($fileNames as $fileName) {
			$filePath = $this->storageDir . $fileName;
			if (file_exists($filePath)) {
				unlink($filePath);
			}
		}
	}

	public function setActiveMultiple(array $ids, bool $isActive): void {
		if (empty($ids)) return;

		$this->inTransaction(function () use ($ids, $isActive) {
			foreach ($ids as $id) {
				$this->bannerRepository->setActive((int) $id, $isActive);
			}
		});
	}

	public function getBannerFilePath(string $fileName): ?string {
		// Prevent directory traversal
		$filePath = $this->storageDir . basename($fileName);

		if (!file_exists($filePath) || !is_file($filePath)) {
			return null;
		}

		return $filePath;
	}

	public function getStorageDir(): string {
		return $this->storageDir;
	}

	private function calculatePagination(int $requestedPage, int $entriesPerPage, int $totalEntries): array {
		$entriesPerPage = max(1, $entriesPerPage);
		$currentPage = max(1, $requestedPage);

		if ($totalEntries <= 0) {
			return [$entriesPerPage, 1, 0];
		}

		$lastPage = (int) ceil($totalEntries / $entriesPerPage);
		$currentPage = min($currentPage, $lastPage);
		$offset = ($currentPage - 1) * $entriesPerPage;

		return [$entriesPerPage, $currentPage, $offset];
	}

	/** Cooldowns are per preset, so the lookup is too. */
	private function checkFlood(bannerPreset $preset, string $ipAddress): void {
		if ($preset->submissionCooldown <= 0) return;

		$lastSubmission = $this->bannerRepository->getLastSubmissionTimeForIp($preset->key, $ipAddress);
		if ($lastSubmission === null) return;

		$lastTime = strtotime($lastSubmission);
		if ($lastTime === false) return;

		$elapsed = time() - $lastTime;
		if ($elapsed < $preset->submissionCooldown) {
			throw new BoardException(_T('banner_flood', $preset->submissionCooldown - $elapsed));
		}
	}

	private function validateUploadedFile(array $file, bannerPreset $preset): void {
		if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
			throw new BoardException(_T('banner_upload_failed'));
		}

		if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
			throw new BoardException(_T('banner_invalid_upload'));
		}

		if ($preset->maxFileSize > 0 && $file['size'] > $preset->maxFileSize) {
			throw new BoardException(_T('banner_file_too_large', round($preset->maxFileSize / 1024)));
		}

		$extension = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
		if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
			throw new BoardException(_T('banner_invalid_extension'));
		}

		// MIME type from the actual bytes, not from what the browser claimed
		$finfo = new \finfo(FILEINFO_MIME_TYPE);
		$detectedMime = $finfo->file($file['tmp_name']);
		if (!in_array($detectedMime, self::ALLOWED_MIME_TYPES, true)) {
			throw new BoardException(_T('banner_invalid_image'));
		}

		$mimeToExtMap = [
			'image/png' => ['png'],
			'image/jpeg' => ['jpg', 'jpeg'],
			'image/gif' => ['gif'],
		];
		if (!in_array($extension, $mimeToExtMap[$detectedMime] ?? [], true)) {
			throw new BoardException(_T('banner_ext_mime_mismatch'));
		}

		if ($preset->width > 0 && $preset->height > 0) {
			$imageSize = getimagesize($file['tmp_name']);
			if ($imageSize === false) {
				throw new BoardException(_T('banner_invalid_image'));
			}
			if ($imageSize[0] !== $preset->width || $imageSize[1] !== $preset->height) {
				throw new BoardException(_T('banner_invalid_dimensions', $preset->width, $preset->height));
			}
		}
	}

	private function storeFile(array $file): string {
		$this->ensureStorageDir();

		$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
		$storedFileName = bin2hex(random_bytes(16)) . '.' . $extension;

		if (!move_uploaded_file($file['tmp_name'], $this->storageDir . $storedFileName)) {
			throw new BoardException(_T('banner_save_failed'));
		}

		return $storedFileName;
	}

	private function ensureStorageDir(): void {
		if (!is_dir($this->storageDir) && !mkdir($this->storageDir, 0755, true)) {
			throw new BoardException(_T('banner_mkdir_failed'));
		}
	}
}
