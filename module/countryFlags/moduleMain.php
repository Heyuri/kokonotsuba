<?php

namespace Kokonotsuba\Modules\countryFlags;

require_once __DIR__ . "/geoip/geoip2.phar";
require_once __DIR__ . "/countryFlagRepository.php";

use Exception;
use GeoIp2\Database\Reader;

use Kokonotsuba\module_classes\abstractModuleMain;
use Kokonotsuba\module_classes\traits\listeners\PostListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\RegistPostInsertedListenerTrait;
use Kokonotsuba\post\Post;

class moduleMain extends abstractModuleMain {
	use PostListenerTrait;
	use RegistPostInsertedListenerTrait;

	private readonly string $staticUrl;
	private readonly countryFlagRepository $countryFlagRepository;

	public function getName(): string {
		return 'Kokonotsuba country flags';
	}

	public function getVersion(): string {
		return '7th dev v140606';
	}

	public function initialize(): void {
		$this->staticUrl = $this->getConfig('STATIC_URL');

		$dbSettings = $this->moduleContext->dbSettings;
		$this->countryFlagRepository = new countryFlagRepository(
			$this->moduleContext->databaseConnection,
			$dbSettings['COUNTRY_FLAG_TABLE']
		);

		$this->listenPost('onRenderPost');
		$this->listenRegistPostInserted('onPostInserted');
	}

	/**
	 * Resolve an ISO 3166-1 alpha-2 country code from an IP or hostname string.
	 * Returns an empty string when the country cannot be determined.
	 */
	private function resolveCountryCode(string $ip): string {
		try {
			$reader = new Reader(__DIR__ . '/geoip/GeoLite2-Country.mmdb');
			$record = $reader->country(gethostbyname($ip));
			return $record->country->isoCode ?? '';
		} catch (Exception $e) {
			return '';
		}
	}

	/**
	 * Called once when a post is registered. Resolves and persists the country code.
	 */
	public function onPostInserted(int $postUid, string $ip): void {
		$countryCode = $this->resolveCountryCode($ip);
		$this->countryFlagRepository->insertFlag($postUid, $countryCode !== '' ? $countryCode : 'XX');
	}

	/**
	 * Called at render time. Reads the pre-computed country code from the post and appends the flag img.
	 */
	public function onRenderPost(array &$arrLabels, Post $post): void {
		if ($this->getConfig('ModuleSettings.FLAG_MODE') == 1 && strstr($post->getEmail(), 'flag')) return;
		if ($this->getConfig('ModuleSettings.FLAG_MODE') == 2 && !strstr($post->getEmail(), 'flag')) return;

		$countryCode = $post->getCountryCode();

		if ($countryCode !== '' && $countryCode !== 'XX') {
			$arrLabels['{$NAME}'] .= ' <img class="countryFlag" src="' . $this->staticUrl . 'image/flag/' . strtolower($countryCode) . '.png" title="' . htmlspecialchars($countryCode) . '" alt="' . htmlspecialchars($countryCode) . '">';
		} else {
			$arrLabels['{$NAME}'] .= ' <img class="countryFlag" src="' . $this->staticUrl . 'image/flag/xx.png" title="Unknown" alt="XX">';
		}
	}
}

