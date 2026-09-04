<?php

namespace Koko\Tests\Unit\Modules;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\Modules\banner\bannerPresetRegistry;

/** The preset list: config drives the values, the definitions drive what exists. */
final class BannerPresetRegistryTest extends TestCase {

	protected function setUp(): void {
		requireModuleFile('banner/bannerPreset.php');
		requireModuleFile('banner/bannerPresetRegistry.php');
	}

	/** @param array<string, mixed> $overrides */
	private function registry(array $overrides = []): bannerPresetRegistry {
		return bannerPresetRegistry::fromConfig(
			fn (string $key, mixed $default): mixed => $overrides[$key] ?? $default
		);
	}

	public function testEachPresetTakesItsOwnDimensions(): void {
		$presets = $this->registry();
		$this->assertSame(468, $presets->get(bannerPresetRegistry::AD)->width);
		$this->assertSame(300, $presets->get(bannerPresetRegistry::BOARD)->width);
		$this->assertSame(100, $presets->get(bannerPresetRegistry::BOARD)->height);
	}

	public function testConfigOverridesOnlyThePresetItNames(): void {
		$presets = $this->registry(['BOARD_BANNER_WIDTH' => 250]);
		$this->assertSame(250, $presets->get(bannerPresetRegistry::BOARD)->width);
		$this->assertSame(468, $presets->get(bannerPresetRegistry::AD)->width);
	}

	public function testUnknownAndEmptyKeysResolveToTheDefaultPreset(): void {
		$presets = $this->registry();
		$this->assertSame(bannerPresetRegistry::AD, $presets->resolve('nonsense')->key);
		$this->assertSame(bannerPresetRegistry::AD, $presets->resolve('')->key);
		$this->assertSame(bannerPresetRegistry::AD, $presets->resolve(null)->key);
	}

	public function testSubmittableFollowsTheAllowSubmissionsSetting(): void {
		// Both presets take submissions unless a board turns one off.
		$this->assertSame(
			[bannerPresetRegistry::AD, bannerPresetRegistry::BOARD],
			array_keys($this->registry()->submittable())
		);

		$open = $this->registry(['BOARD_BANNER_ALLOW_SUBMISSIONS' => true, 'BANNER_AD_ALLOW_SUBMISSIONS' => false]);
		$this->assertSame([bannerPresetRegistry::BOARD], array_keys($open->submittable()));

		$closed = $this->registry(['BOARD_BANNER_ALLOW_SUBMISSIONS' => false]);
		$this->assertSame([bannerPresetRegistry::AD], array_keys($closed->submittable()));
	}

	public function testUnknownPresetIsRefusedByGet(): void {
		$this->assertThrows(fn () => $this->registry()->get('nope'), \InvalidArgumentException::class);
	}
}
