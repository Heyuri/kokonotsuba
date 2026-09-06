<?php

namespace Kokonotsuba\Modules\banner;

use InvalidArgumentException;

/**
 * The preset list.
 *
 * Which presets exist and where each one is drawn is fixed here, because that is a property of
 * the page rather than of a board. Everything an upload is measured against comes from module
 * config, so a board retunes a preset without a new kind of banner appearing.
 */
final class bannerPresetRegistry {
	public const AD = 'ad';
	public const BOARD = 'board';

	private const DEFINITIONS = [
		self::AD => [
			'prefix' => 'BANNER_AD',
			'label' => 'banner_preset_ad',
			'width' => 468,
			'height' => 60,
			'maxFileSize' => 204800,
			'submissions' => true,
			'cooldown' => 300,
			'usesLink' => true,
		],
		self::BOARD => [
			'prefix' => 'BOARD_BANNER',
			'label' => 'banner_preset_board',
			'width' => 300,
			'height' => 100,
			'maxFileSize' => 204800,
			'submissions' => true,
			'cooldown' => 300,
			'usesLink' => false,
		],
	];

	/** @param array<string, bannerPreset> $presets */
	private function __construct(private readonly array $presets) {}

	/**
	 * @param callable(string, mixed): mixed $config module config reader, e.g. getModuleConfig()
	 */
	public static function fromConfig(callable $config): self {
		$presets = [];

		foreach (self::DEFINITIONS as $key => $definition) {
			$prefix = $definition['prefix'];

			$presets[$key] = new bannerPreset(
				$key,
				$definition['label'],
				(int) $config("{$prefix}_WIDTH", $definition['width']),
				(int) $config("{$prefix}_HEIGHT", $definition['height']),
				(int) $config("{$prefix}_MAX_FILE_SIZE", $definition['maxFileSize']),
				(bool) $config("{$prefix}_ALLOW_SUBMISSIONS", $definition['submissions']),
				(int) $config("{$prefix}_SUBMISSION_COOLDOWN", $definition['cooldown']),
				$definition['usesLink'],
			);
		}

		return new self($presets);
	}

	/** @return array<string, bannerPreset> */
	public function all(): array {
		return $this->presets;
	}

	public function has(string $key): bool {
		return isset($this->presets[$key]);
	}

	public function get(string $key): bannerPreset {
		if (!isset($this->presets[$key])) {
			throw new InvalidArgumentException("Unknown banner preset: {$key}");
		}

		return $this->presets[$key];
	}

	public function defaultPreset(): bannerPreset {
		return $this->presets[self::AD];
	}

	/** The preset a request named, falling back to the default when it named none or an unknown one. */
	public function resolve(?string $key): bannerPreset {
		return $this->presets[(string) $key] ?? $this->defaultPreset();
	}

	/** @return array<string, bannerPreset> presets currently accepting reader submissions */
	public function submittable(): array {
		return array_filter($this->presets, fn (bannerPreset $preset): bool => $preset->allowSubmissions);
	}
}
