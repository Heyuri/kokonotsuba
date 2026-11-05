<?php

namespace Kokonotsuba\Modules\countryFlags;

require_once __DIR__ . "/geoip/geoip2.phar";
require_once __DIR__ . "/countryFlagRepository.php";

use Exception;
use GeoIp2\Database\Reader;

use Kokonotsuba\module_classes\abstractModuleMain;
use Kokonotsuba\module_classes\traits\listeners\ModuleHeaderListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\OpeningPostListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\PostListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\RegistPostInsertedListenerTrait;
use Kokonotsuba\post\Post;
use function Kokonotsuba\libraries\_T;
use function Puchiko\strings\sanitizeStr;

class moduleMain extends abstractModuleMain {
	use ModuleHeaderListenerTrait;
	use OpeningPostListenerTrait;
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

		$this->countryFlagRepository = new countryFlagRepository(
			$this->moduleContext->databaseConnection,
			$this->moduleContext->getTableName('COUNTRY_FLAG_TABLE')
		);

		$this->listenPost('onRenderPost');
		$this->listenOpeningPost('onRenderOpeningPost');
		$this->listenRegistPostInserted('onPostInserted');
		$this->listenModuleHeader('onModuleHeaderStylesheet');
	}

	/** The module's own stylesheet. */
	private function onModuleHeaderStylesheet(string &$moduleHeader): void {
		$this->includeStylesheet('countryFlags.css', $moduleHeader);
	}

	/** Mode 2: the OP turned flags on for the whole thread with "displayflag" in the email field. */
	private function isThreadOptedIn(Post $opPost): bool {
		return $this->getModuleConfig('DISPLAYFLAG_TOGGLE', true) && str_contains($opPost->getEmail(), 'displayflag');
	}

	/** Whether the OP of the thread these posts belong to turned flags on for every post. */
	private function isThreadForced(array $threadPosts): bool {
		$opPost = $this->findOpeningPost($threadPosts);
		return $opPost !== null && $this->isThreadOptedIn($opPost);
	}

	/** The thread's opening post, by its is_op flag rather than its position. */
	private function findOpeningPost(array $threadPosts): ?Post {
		foreach ($threadPosts as $threadPost) {
			if ($threadPost->isOp()) {
				return $threadPost;
			}
		}
		return $threadPosts[0] ?? null;
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
	public function onRenderPost(array &$arrLabels, Post $post, array $threadPosts): void {
		$flagMode = $this->getModuleConfig('FLAG_MODE');
		if ($flagMode == 1 && strstr($post->getEmail(), 'flag')) return;
		if ($flagMode == 2 && !strstr($post->getEmail(), 'flag') && !$this->isThreadForced($threadPosts)) return;

		$countryCode = $post->getCountryCode();

		if ($countryCode !== '' && $countryCode !== 'XX') {
			$arrLabels['{$NAME}'] .= ' <img class="countryFlag" src="' . $this->staticUrl . 'image/flag/' . strtolower($countryCode) . '.png" title="' . sanitizeStr($countryCode) . '" alt="' . sanitizeStr($countryCode) . '">';
		} else {
			$arrLabels['{$NAME}'] .= ' <img class="countryFlag" src="' . $this->staticUrl . 'image/flag/xx.png" title="Unknown" alt="XX">';
		}
	}

	/** Mode 2: tell readers of an opted-in thread that every post shows its country flag. */
	public function onRenderOpeningPost(array &$templateValues, Post $post): void {
		if ($this->getModuleConfig('FLAG_MODE') == 2 && $this->isThreadOptedIn($post)) {
			$templateValues['{$COM}'] .= '<p class="flagWarning">' . _T('posts_itt_display_flag') . '</p>';
		}
	}
}
