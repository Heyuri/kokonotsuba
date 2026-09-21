<?php

namespace Koko\Tests\Unit\Kokonotsuba;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\cache\thread_fragment\threadFragmentCache;
use Kokonotsuba\thread\Thread;

/**
 * The per-thread markup store behind the overboard. Files are the whole contract: a miss is
 * null, a write is atomic, a stale or hostile key can never leave the directory.
 */
final class ThreadFragmentCacheTest extends TestCase {
	private string $dir;

	protected function setUp(): void {
		$this->dir = sys_get_temp_dir() . '/koko-fragments-' . bin2hex(random_bytes(4)) . '/';
	}

	protected function tearDown(): void {
		foreach (array_merge(glob($this->dir . 'epochs/*') ?: [], glob($this->dir . '*') ?: []) as $file) {
			is_dir($file) ? @rmdir($file) : @unlink($file);
		}
		@rmdir($this->dir);
	}

	public function testAMissIsNull(): void {
		$cache = new threadFragmentCache($this->dir);

		$this->assertNull($cache->get('000e5efb', 'overboard-5', 's1'));
	}

	public function testStoredMarkupComesBackUnchanged(): void {
		$cache = new threadFragmentCache($this->dir);
		$html = "<div class=\"thread\">\r\n<!--koko:threadnav--> 日本語 & <b>x</b></div>";

		$this->assertTrue($cache->put('000e5efb', 'overboard-5', 's1', $html));
		$this->assertSame($html, $cache->get('000e5efb', 'overboard-5', 's1'));
	}

	public function testVariantsOfOneThreadAreSeparate(): void {
		$cache = new threadFragmentCache($this->dir);
		$cache->put('t1', 'overboard-5', 's1', 'five');
		$cache->put('t1', 'overboard-3', 's1', 'three');

		$this->assertSame('five', $cache->get('t1', 'overboard-5', 's1'));
		$this->assertSame('three', $cache->get('t1', 'overboard-3', 's1'));
		$this->assertNull($cache->get('t1', 'overboard-1', 's1'));
	}

	public function testForgetDropsEveryVariantOfThatThreadOnly(): void {
		$cache = new threadFragmentCache($this->dir);
		$cache->put('t1', 'a', 's1', 'x');
		$cache->put('t1', 'b', 's1', 'y');
		$cache->put('t2', 'a', 's1', 'z');

		$cache->forget('t1');

		$this->assertNull($cache->get('t1', 'a', 's1'));
		$this->assertNull($cache->get('t1', 'b', 's1'));
		$this->assertSame('z', $cache->get('t2', 'a', 's1'));
	}

	public function testClearEmptiesTheDirectoryAndTolerateAMissingOne(): void {
		$cache = new threadFragmentCache($this->dir);
		$cache->clear();

		$cache->put('t1', 'a', 's1', 'x');
		$cache->put('t2', 'a', 's1', 'y');
		file_put_contents($this->dir . 'leftover.tmp', '');
		$cache->clear();

		$this->assertNull($cache->get('t1', 'a', 's1'));
		$this->assertSame([], glob($this->dir . '*.html'));
		$this->assertSame([], glob($this->dir . '*.tmp'));
	}

	/** A thread uid is a database value; a hostile one must still land inside the directory. */
	public function testUnsafeKeysStayInsideTheDirectory(): void {
		$cache = new threadFragmentCache($this->dir);

		$this->assertTrue($cache->put('../../etc/passwd', 'x/y', 's1', 'safe'));
		$this->assertSame('safe', $cache->get('../../etc/passwd', 'x/y', 's1'));
		$this->assertCount(1, glob($this->dir . '*.html'));
		$this->assertFalse(is_file(dirname($this->dir, 3) . '/etc/passwd.html'));
	}

	/** A fragment drawn from an older row must never be served for the current one. */
	public function testAStaleStampIsAMiss(): void {
		$cache = new threadFragmentCache($this->dir);
		$cache->put('t1', 'index-5', '5-20260918100000', 'five posts');

		$this->assertSame('five posts', $cache->get('t1', 'index-5', '5-20260918100000'));
		$this->assertNull($cache->get('t1', 'index-5', '6-20260918100500'));
		$this->assertNull($cache->get('t1', 'index-5', '4-20260918100000'));
	}

	public function testStoringANewStampDropsTheOldFileOfThatVariantOnly(): void {
		$cache = new threadFragmentCache($this->dir);
		$cache->put('t1', 'index-5', '5-1', 'old');
		$cache->put('t1', 'thread-1', '5-1', 'page');
		$cache->put('t1', 'index-5', '6-2', 'new');

		$this->assertNull($cache->get('t1', 'index-5', '5-1'));
		$this->assertSame('new', $cache->get('t1', 'index-5', '6-2'));
		$this->assertSame('page', $cache->get('t1', 'thread-1', '5-1'));
		$this->assertCount(2, glob($this->dir . 't1.*.html'));
	}

	/** An edit or a vote leaves the stamp alone, so the draw it interrupts must not be stored. */
	public function testAFragmentDrawnAcrossAForgetIsNotStored(): void {
		$reader = new threadFragmentCache($this->dir);
		$this->assertNull($reader->get('t1', 'a', 's1'));

		(new threadFragmentCache($this->dir))->forget('t1');

		$this->assertFalse($reader->put('t1', 'a', 's1', 'drawn from the old rows'));
		$this->assertNull($reader->get('t1', 'a', 's1'));
		$this->assertTrue($reader->put('t1', 'a', 's1', 'drawn again'));
		$this->assertSame('drawn again', $reader->get('t1', 'a', 's1'));
	}

	public function testAFragmentDrawnAcrossAClearIsNotStored(): void {
		$reader = new threadFragmentCache($this->dir);
		$reader->get('t1', 'a', 's1');

		(new threadFragmentCache($this->dir))->clear();

		$this->assertFalse($reader->put('t1', 'a', 's1', 'drawn under the old config'));
		$this->assertSame([], glob($this->dir . '*.html'));
	}

	public function testForgettingAnotherThreadDoesNotRefuseTheStore(): void {
		$shard = static fn(string $uid): string => substr(sha1($uid), 0, 2);
		$other = 'u0';
		for ($i = 1; $shard($other) === $shard('t1'); $i++) {
			$other = 'u' . $i;
		}

		$reader = new threadFragmentCache($this->dir);
		$reader->get('t1', 'a', 's1');
		(new threadFragmentCache($this->dir))->forget($other);

		$this->assertTrue($reader->put('t1', 'a', 's1', 'x'));
	}

	public function testForgettingWhereNothingWasEverReadLeavesNoFiles(): void {
		(new threadFragmentCache($this->dir))->forget('t1');

		$this->assertFalse(is_dir($this->dir));
	}

	public function testTheStampFollowsThePostCountAndLastReplyTime(): void {
		$thread = new Thread(['number_of_posts' => 7, 'last_reply_time' => '2026-09-18 10:11:12']);

		$this->assertSame('7-20260918101112', threadFragmentCache::stampFor($thread));
		$this->assertMatchesRegex('/^[A-Za-z0-9_-]+$/', threadFragmentCache::stampFor($thread));
	}

	public function testAStoreCanBeReachedByStorageDirectoryName(): void {
		$byName = threadFragmentCache::forStorageDirName('storage-9', $this->dir . 'boards');
		$byName->put('t1', 'index-5', 's1', 'x');

		$this->assertSame('x', (new threadFragmentCache($this->dir . 'boards/storage-9/cache/threads/'))->get('t1', 'index-5', 's1'));
		array_map('unlink', glob($this->dir . 'boards/storage-9/cache/threads/*'));
		rmdir($this->dir . 'boards/storage-9/cache/threads'); rmdir($this->dir . 'boards/storage-9/cache'); rmdir($this->dir . 'boards/storage-9'); rmdir($this->dir . 'boards');
	}

	public function testVariantNamesAreDistinctPerViewAndCount(): void {
		$names = [
			threadFragmentCache::indexVariant(5),
			threadFragmentCache::indexVariant(3),
			threadFragmentCache::overboardVariant(5, 1),
			threadFragmentCache::overboardVariant(5, 2),
			threadFragmentCache::threadVariant(null),
			threadFragmentCache::threadVariant(1),
			threadFragmentCache::threadVariant(2),
		];

		$this->assertCount(7, array_unique($names));
		foreach ($names as $name) {
			$this->assertMatchesRegex('/^[A-Za-z0-9_-]+$/', $name);
		}
	}

	public function testAnUnwritableDirectoryFailsQuietly(): void {
		// a regular file where the directory should be: mkdir cannot succeed
		file_put_contents(rtrim($this->dir, '/'), '');
		$cache = new threadFragmentCache($this->dir);

		$this->assertFalse($cache->put('t1', 'a', 's1', 'x'));
		$this->assertNull($cache->get('t1', 'a', 's1'));
		@unlink(rtrim($this->dir, '/'));
	}
}
