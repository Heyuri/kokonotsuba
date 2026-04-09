<?php

namespace Kokonotsuba\Modules\fullBanner;

use Kokonotsuba\database\transactionManager;
use Kokonotsuba\database\TransactionalTrait;
use Kokonotsuba\error\BoardException;

class fullBannerService {
	use TransactionalTrait;

	private const ALLOWED_EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif'];

	private const ALLOWED_MIME_TYPES = [
		'image/png',
		'image/jpeg',
		'image/gif',
	];

	public function __construct(
		private fullBannerRepository $fullBannerRepository,
		private transactionManager $transactionManager,
		private string $storageDir,
	) {}

	public function getApprovedActiveBanners(): array {
		return $this->fullBannerRepository->getApprovedActiveBanners();
	}

	public function getAllBanners(): array {
		return $this->fullBannerRepository->getAllBanners();
	}

	public function getRandomActiveBanner(): ?fullBannerEntry {
		return $this->fullBannerRepository->getRandomActiveBanner();
	}

	public function submitBanner(array $uploadedFile, ?string $link, string $ipAddress, int $cooldownSeconds, int $requiredWidth, int $requiredHeight, int $maxFileSize): void {
		$this->checkFlood($ipAddress, $cooldownSeconds);
		$this->validateUploadedFile($uploadedFile, $requiredWidth, $requiredHeight, $maxFileSize);

		$storedFileName = $this->storeFile($uploadedFile);

		$this->inTransaction(function () use ($storedFileName, $link, $ipAddress) {
			$this->fullBannerRepository->insertBanner($storedFileName, $link, $ipAddress, false, false);
		});
	}

	public function adminUploadBanner(array $uploadedFile, ?string $link, int $requiredWidth, int $requiredHeight, int $maxFileSize): void {
		$this->validateUploadedFile($uploadedFile, $requiredWidth, $requiredHeight, $maxFileSize);

		$storedFileName = $this->storeFile($uploadedFile);

		$this->inTransaction(function () use ($storedFileName, $link) {
			$this->fullBannerRepository->insertBanner($storedFileName, $link, null, true, true);
		});
	}

	public function approveBanners(array $ids): void {
		if (empty($ids)) return;

		$this->inTransaction(function () use ($ids) {
			$this->fullBannerRepository->approveBanners($ids);
		});
	}

	public function deleteBanners(array $ids): void {
		if (empty($ids)) return;

		$fileNames = [];

		$this->inTransaction(function () use ($ids, &$fileNames) {
			$fileNames = $this->fullBannerRepository->deleteBanners($ids);
		});

		// Delete physical files after successful DB deletion
		foreach ($fileNames as $fileName) {
			$filePath = $this->storageDir . $fileName;
			if (file_exists($filePath)) {
				unlink($filePath);
			}
		}
	}

	public function setActive(int $id, bool $isActive): void {
		$this->inTransaction(function () use ($id, $isActive) {
			$this->fullBannerRepository->setActive($id, $isActive);
		});
	}

	public function setActiveMultiple(array $ids, bool $isActive): void {
		if (empty($ids)) return;

		$this->inTransaction(function () use ($ids, $isActive) {
			foreach ($ids as $id) {
				$this->fullBannerRepository->setActive((int) $id, $isActive);
			}
		});
	}

	public function getBannerFilePath(string $fileName): ?string {
		// Prevent directory traversal
		$baseName = basename($fileName);
		$filePath = $this->storageDir . $baseName;

		if (!file_exists($filePath) || !is_file($filePath)) {
			return null;
		}

		return $filePath;
	}

	public function getStorageDir(): string {
		return $this->storageDir;
	}

	private function checkFlood(string $ipAddress, int $cooldownSeconds): void {
		if ($cooldownSeconds <= 0) return;

		$lastSubmission = $this->fullBannerRepository->getLastSubmissionTimeForIp($ipAddress);
		if ($lastSubmission === null) return;

		$lastTime = strtotime($lastSubmission);
		if ($lastTime === false) return;

		$elapsed = time() - $lastTime;
		if ($elapsed < $cooldownSeconds) {
			$remaining = $cooldownSeconds - $elapsed;
			throw new BoardException("Please wait {$remaining} seconds before submitting another banner.");
		}
	}

	private function validateUploadedFile(array $file, int $requiredWidth, int $requiredHeight, int $maxFileSize): void {
		if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
			throw new BoardException('File upload failed.');
		}

		if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
			throw new BoardException('Invalid file upload.');
		}

		// Validate file size
		if ($maxFileSize > 0 && $file['size'] > $maxFileSize) {
			throw new BoardException("File size exceeds the maximum allowed size of " . round($maxFileSize / 1024) . "KB.");
		}

		// Validate extension
		$originalName = $file['name'] ?? '';
		$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
		if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
			throw new BoardException('Only PNG, JPG, JPEG, and GIF files are allowed.');
		}

		// Validate MIME type from actual file content
		$finfo = new \finfo(FILEINFO_MIME_TYPE);
		$detectedMime = $finfo->file($file['tmp_name']);
		if (!in_array($detectedMime, self::ALLOWED_MIME_TYPES, true)) {
			throw new BoardException('The file does not appear to be a valid image.');
		}

		// Cross-check: ensure extension matches detected MIME type
		$mimeToExtMap = [
			'image/png' => ['png'],
			'image/jpeg' => ['jpg', 'jpeg'],
			'image/gif' => ['gif'],
		];
		$allowedExtsForMime = $mimeToExtMap[$detectedMime] ?? [];
		if (!in_array($extension, $allowedExtsForMime, true)) {
			throw new BoardException('File extension does not match its content type.');
		}

		// Validate image dimensions
		if ($requiredWidth > 0 && $requiredHeight > 0) {
			$imageSize = getimagesize($file['tmp_name']);
			if ($imageSize === false) {
				throw new BoardException('The file does not appear to be a valid image.');
			}
			if ($imageSize[0] !== $requiredWidth || $imageSize[1] !== $requiredHeight) {
				throw new BoardException("Banner images must be exactly {$requiredWidth}x{$requiredHeight} pixels.");
			}
		}
	}

	private function storeFile(array $file): string {
		$this->ensureStorageDir();

		$originalName = $file['name'];
		$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
		$storedFileName = bin2hex(random_bytes(16)) . '.' . $extension;
		$destPath = $this->storageDir . $storedFileName;

		if (!move_uploaded_file($file['tmp_name'], $destPath)) {
			throw new BoardException('Failed to save uploaded file.');
		}

		return $storedFileName;
	}

	private function ensureStorageDir(): void {
		if (!is_dir($this->storageDir)) {
			if (!mkdir($this->storageDir, 0755, true)) {
				throw new BoardException('Failed to create banner storage directory.');
			}
		}
	}
}
