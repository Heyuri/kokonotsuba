<?php

namespace Kokonotsuba\Modules\fullBanner;

use Kokonotsuba\database\baseRepository;
use Kokonotsuba\database\databaseConnection;

class fullBannerRepository extends baseRepository {
	public function __construct(
		databaseConnection $databaseConnection,
		string $bannerAdTable,
	) {
		parent::__construct($databaseConnection, $bannerAdTable);
	}

	public function getApprovedActiveBanners(): array {
		$query = "SELECT * FROM {$this->table} WHERE is_active = 1 AND is_approved = 1 ORDER BY date_submitted DESC";
		return $this->databaseConnection->fetchAllAsClass($query, [], fullBannerEntry::class);
	}

	public function getAllBanners(): array {
		$query = "SELECT * FROM {$this->table} ORDER BY date_submitted DESC";
		return $this->databaseConnection->fetchAllAsClass($query, [], fullBannerEntry::class);
	}

	public function getRandomActiveBanner(): ?fullBannerEntry {
		$query = "SELECT * FROM {$this->table} WHERE is_active = 1 AND is_approved = 1 ORDER BY RAND() LIMIT 1";
		$result = $this->databaseConnection->fetchAsClass($query, [], fullBannerEntry::class);
		return $result ?: null;
	}

	public function insertBanner(string $fileName, ?string $link, ?string $ipAddress, bool $isApproved, bool $isActive): void {
		$this->insert([
			'banner_file_name' => $fileName,
			'link' => $link,
			'ip_address' => $ipAddress,
			'is_approved' => $isApproved ? 1 : 0,
			'is_active' => $isActive ? 1 : 0,
		]);
	}

	public function approveBanners(array $ids): void {
		if (empty($ids)) return;
		$this->updateWhereIn(['is_approved' => 1, 'is_active' => 1], 'id', $ids);
	}

	public function deleteBanners(array $ids): array {
		if (empty($ids)) return [];

		$banners = $this->findAllWhereIn('id', $ids, fullBannerEntry::class);
		$fileNames = array_map(fn($b) => $b->banner_file_name, $banners);

		$this->deleteWhereIn('id', $ids);

		return $fileNames;
	}

	public function setActive(int $id, bool $isActive): void {
		$data = ['is_active' => $isActive ? 1 : 0];
		if ($isActive) {
			$data['is_approved'] = 1;
		}
		$this->updateWhere($data, 'id', $id);
	}

	public function getLastSubmissionTimeForIp(string $ip): ?string {
		$query = "SELECT date_submitted FROM {$this->table} WHERE ip_address = :ip ORDER BY date_submitted DESC LIMIT 1";
		$result = $this->databaseConnection->fetchOne($query, [':ip' => $ip]);
		return $result ? $result['date_submitted'] : null;
	}

	public function findById(int $id): ?fullBannerEntry {
		return $this->findBy('id', $id, fullBannerEntry::class);
	}
}
