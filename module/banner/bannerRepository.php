<?php

namespace Kokonotsuba\Modules\banner;

use Kokonotsuba\database\baseRepository;
use Kokonotsuba\database\databaseConnection;

class bannerRepository extends baseRepository {
	public function __construct(
		databaseConnection $databaseConnection,
		string $bannerTable,
	) {
		parent::__construct($databaseConnection, $bannerTable);
	}

	/** Approved and active banners of one preset. */
	public function getApprovedActivePaginated(string $preset, int $limit, int $offset = 0): array {
		$query = "SELECT * FROM {$this->table} WHERE preset = :preset AND is_active = 1 AND is_approved = 1 ORDER BY date_submitted DESC";
		$params = [':preset' => $preset];
		$this->paginate($query, $params, $limit, $offset);

		return $this->queryAllAsClass($query, $params, bannerEntry::class);
	}

	public function countApprovedActive(string $preset): int {
		return $this->count('preset = :preset AND is_active = 1 AND is_approved = 1', [':preset' => $preset]);
	}

	public function hasApprovedActive(string $preset): bool {
		return $this->countApprovedActive($preset) > 0;
	}

	/**
	 * Banners submitted by readers that no one has approved or deleted yet — the queue behind
	 * the staff alerts widget's Banners row. Every preset counts towards it.
	 */
	public function countPending(): int {
		return $this->count('is_approved = 0');
	}

	/** Every banner, or only one preset's when $preset is given. */
	public function getAllPaginated(?string $preset, int $limit, int $offset = 0): array {
		$where = $preset === null ? '' : ' WHERE preset = :preset';
		$params = $preset === null ? [] : [':preset' => $preset];

		$query = "SELECT * FROM {$this->table}{$where} ORDER BY date_submitted DESC";
		$this->paginate($query, $params, $limit, $offset);

		return $this->queryAllAsClass($query, $params, bannerEntry::class);
	}

	public function countAll(?string $preset): int {
		return $preset === null
			? $this->count()
			: $this->count('preset = :preset', [':preset' => $preset]);
	}

	public function getRandomActive(string $preset): ?bannerEntry {
		$query = "SELECT * FROM {$this->table} WHERE preset = :preset AND is_active = 1 AND is_approved = 1 ORDER BY RAND() LIMIT 1";
		$result = $this->queryAsClass($query, [':preset' => $preset], bannerEntry::class);

		return $result ?: null;
	}

	public function insertBanner(string $preset, string $fileName, ?string $link, ?string $ipAddress, bool $isApproved, bool $isActive): void {
		$this->insert([
			'preset' => $preset,
			'banner_file_name' => $fileName,
			'link' => $link,
			'ip_address' => $ipAddress,
			'is_approved' => $isApproved ? 1 : 0,
			'is_active' => $isActive ? 1 : 0,
		]);
	}

	/** @return string[] every file name stored for a preset */
	public function getFileNames(string $preset): array {
		return $this->pluckAll('banner_file_name', 'preset', $preset);
	}

	public function approveBanners(array $ids): void {
		if (empty($ids)) return;
		$this->updateWhereIn(['is_approved' => 1, 'is_active' => 1], 'id', $ids);
	}

	/** @return string[] the file names of the deleted rows, for the caller to unlink */
	public function deleteBanners(array $ids): array {
		if (empty($ids)) return [];

		$banners = $this->findAllWhereIn('id', $ids, bannerEntry::class);
		$fileNames = array_map(fn ($b) => $b->banner_file_name, $banners);

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

	/** Last submission this address made towards one preset, for the flood check. */
	public function getLastSubmissionTimeForIp(string $preset, string $ip): ?string {
		$query = "SELECT date_submitted FROM {$this->table} WHERE preset = :preset AND ip_address = :ip ORDER BY date_submitted DESC LIMIT 1";
		$result = $this->queryValue($query, [':preset' => $preset, ':ip' => $ip]);

		return $result !== false && $result !== null ? (string) $result : null;
	}

	public function findById(int $id): ?bannerEntry {
		return $this->findBy('id', $id, bannerEntry::class);
	}
}
