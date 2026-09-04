<?php

namespace Koko\Tests\Unit\Kokonotsuba;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\ban\banCheckpoint;
use Kokonotsuba\ban\banCheckpointRegistry;

/**
 * The checkpoint enum and the registry modules extend it through.
 *
 * A checkpoint key is written into the ban row verbatim and read back to decide whether a ban
 * blocks an action, so the key strings are part of the stored format and are pinned here.
 */
final class BanCheckpointTest extends TestCase {

	public function testKeysAreStableStrings(): void {
		$this->assertSame('post', banCheckpoint::POST->value);
		$this->assertSame('report', banCheckpoint::REPORT->value);
		$this->assertSame('banner', banCheckpoint::BANNER->value);
		$this->assertSame('soudane', banCheckpoint::SOUDANE->value);
		$this->assertSame('pm', banCheckpoint::PM->value);
	}

	/** Posting, reports and banners are the three the ban form ticks for you. */
	public function testDefaultCheckpoints(): void {
		$this->assertTrue(banCheckpoint::POST->isDefault());
		$this->assertTrue(banCheckpoint::REPORT->isDefault());
		$this->assertTrue(banCheckpoint::BANNER->isDefault());
		$this->assertFalse(banCheckpoint::SOUDANE->isDefault());
		$this->assertFalse(banCheckpoint::PM->isDefault());
	}

	public function testRegistryListsBuiltInsFirst(): void {
		$registry = new banCheckpointRegistry();
		$registry->register('tegaki', 'Drawing');

		$keys = array_column($registry->all(), 'key');

		$this->assertSame('post', $keys[0]);
		$this->assertSame('tegaki', $keys[count($keys) - 1]);
	}

	public function testRegistryDefaultKeysMatchTheEnum(): void {
		$registry = new banCheckpointRegistry();
		$registry->register('tegaki', 'Drawing', true);

		$this->assertSame(['post', 'report', 'banner', 'tegaki'], $registry->defaultKeys());
	}

	/** A module must not be able to redefine what a built-in checkpoint means. */
	public function testRegisteringOverABuiltInIsIgnored(): void {
		$registry = new banCheckpointRegistry();
		$registry->register('post', 'Something else', false);

		$this->assertSame(1, count(array_filter($registry->all(), fn(array $e): bool => $e['key'] === 'post')));
		$this->assertTrue(in_array('post', $registry->defaultKeys(), true));
	}

	public function testInvalidKeysAreRejected(): void {
		$registry = new banCheckpointRegistry();

		foreach (['', 'has space', 'Bad/Slash', 'semi;colon'] as $key) {
			$threw = false;

			try {
				$registry->register($key, 'Label');
			} catch (\InvalidArgumentException $e) {
				$threw = true;
			}

			$this->assertTrue($threw, "register('{$key}') should have been rejected");
		}
	}

	/**
	 * Checkpoint keys come off a submitted form, so anything unrecognised has to be dropped
	 * rather than stored - a stored key nothing enforces is a ban that silently blocks nothing.
	 */
	public function testFilterKnownDropsUnknownAndDuplicates(): void {
		$registry = new banCheckpointRegistry();
		$registry->register('tegaki', 'Drawing');

		$this->assertSame(
			['post', 'tegaki'],
			$registry->filterKnown(['post', 'nonsense', 'POST', 'tegaki', ''])
		);
	}

	/** A checkpoint whose module has since been switched off still has to render in the table. */
	public function testLabelForFallsBackToTheKey(): void {
		$registry = new banCheckpointRegistry();

		$this->assertSame('tegaki', $registry->labelFor('tegaki'));
	}

	public function testHasRecognisesBothSources(): void {
		$registry = new banCheckpointRegistry();
		$registry->register('tegaki', 'Drawing');

		$this->assertTrue($registry->has('post'));
		$this->assertTrue($registry->has('tegaki'));
		$this->assertFalse($registry->has('nope'));
	}
}
