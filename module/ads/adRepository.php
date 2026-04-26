<?php

namespace Kokonotsuba\Modules\ads;

use Kokonotsuba\database\baseRepository;
use Kokonotsuba\database\databaseConnection;

class adRepository extends baseRepository {
	public function __construct(databaseConnection $databaseConnection, string $adsTable) {
		parent::__construct($databaseConnection, $adsTable);
	}

	/**
	 * Return all enabled ads for the given slot, ordered by ID.
	 */
	public function getEnabledAdsForSlot(string $slot): array {
		$query = "SELECT * FROM {$this->table} WHERE slot = :slot AND enabled = 1 ORDER BY id ASC";
		return $this->queryAllAsClass($query, [':slot' => $slot], adEntry::class);
	}

	/**
	 * Return a paginated page of ads, optionally filtered by slot.
	 */
	public function getPagedAds(int $limit, int $offset, ?string $slot = null): array {
		if ($slot !== null) {
			$query = "SELECT * FROM {$this->table} WHERE slot = :slot ORDER BY id DESC";
			$params = [':slot' => $slot];
		} else {
			$query = "SELECT * FROM {$this->table} ORDER BY id DESC";
			$params = [];
		}
		$this->paginate($query, $params, $limit, $offset);
		return $this->queryAllAsClass($query, $params, adEntry::class);
	}

	/**
	 * Count ads, optionally filtered by slot.
	 */
	public function countAll(?string $slot = null): int {
		if ($slot !== null) {
			$row = $this->queryOne("SELECT COUNT(*) AS cnt FROM {$this->table} WHERE slot = :slot", [':slot' => $slot]);
		} else {
			$row = $this->queryOne("SELECT COUNT(*) AS cnt FROM {$this->table}");
		}
		return $row ? (int)$row['cnt'] : 0;
	}

	/**
	 * Insert a new ad entry.
	 */
	public function insertAd(
		string $slot,
		string $type,
		?string $src,
		?string $href,
		?string $alt,
		?string $html,
	): void {
		$this->insert([
			'slot'    => $slot,
			'type'    => $type,
			'src'     => $src,
			'href'    => $href,
			'alt'     => $alt,
			'html'    => $html,
			'enabled' => 1,
		]);
	}

	/**
	 * Delete an ad by its primary key.
	 */
	public function deleteAd(int $id): void {
		$this->deleteWhere('id', $id);
	}

	/**
	 * Enable or disable an ad.
	 */
	public function setEnabled(int $id, bool $enabled): void {
		$this->updateWhere(['enabled' => (int)$enabled], 'id', $id);
	}

	/**
	 * Fetch a single ad by primary key, or null if not found.
	 */
	public function getAdById(int $id): ?adEntry {
		$result = $this->queryAllAsClass(
			"SELECT * FROM {$this->table} WHERE id = :id LIMIT 1",
			[':id' => $id],
			adEntry::class
		);
		return $result[0] ?? null;
	}

	/**
	 * Update all mutable fields of an existing ad.
	 */
	public function updateAd(int $id, string $slot, string $type, ?string $src, ?string $href, ?string $alt, ?string $html): void {
		$this->updateWhere(
			['slot' => $slot, 'type' => $type, 'src' => $src, 'href' => $href, 'alt' => $alt, 'html' => $html],
			'id',
			$id
		);
	}
}
