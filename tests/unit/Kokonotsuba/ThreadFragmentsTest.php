<?php

namespace Koko\Tests\Unit\Kokonotsuba;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\cache\thread_fragment\threadFragmentCache;
use Kokonotsuba\cache\thread_fragment\threadFragments;

/** Row-driven invalidation needs no board objects, so it works from any context that has the database. */
final class ThreadFragmentsTest extends TestCase {
	private string $dir;

	protected function setUp(): void {
		$this->dir = sys_get_temp_dir() . '/koko-storages-' . bin2hex(random_bytes(4)) . '/';
		$GLOBALS['kokoTestStoragesDir'] = $this->dir;

		// the invalidator finds a board's store through the app's global path helper, which the
		// unit bootstrap does not load; declared globally, outside this file's namespace
		if (!function_exists('getBoardStoragesDir')) {
			eval('function getBoardStoragesDir(): string { return $GLOBALS["kokoTestStoragesDir"]; }');
		}
	}

	protected function tearDown(): void {
		foreach (glob($this->dir . '*/cache/threads/*') ?: [] as $file) {
			@unlink($file);
		}
		foreach (glob($this->dir . '*') ?: [] as $board) {
			@rmdir($board . '/cache/threads'); @rmdir($board . '/cache'); @rmdir($board);
		}
		@rmdir($this->dir);
	}

	public function testRowsWithAStorageNameForgetThatThreadOnThatBoard(): void {
		$a = threadFragmentCache::forStorageDirName('storage-1', $this->dir);
		$b = threadFragmentCache::forStorageDirName('storage-2', $this->dir);
		$a->put('t1', 'index-5', 's', 'a1');
		$a->put('t2', 'index-5', 's', 'a2');
		$b->put('t1', 'index-5', 's', 'b1');

		threadFragments::forgetThreadPairs([
			['thread_uid' => 't1', 'boardUID' => 1, 'storage_directory_name' => 'storage-1'],
		]);

		$this->assertNull($a->get('t1', 'index-5', 's'));
		$this->assertSame('a2', $a->get('t2', 'index-5', 's'));
		$this->assertSame('b1', $b->get('t1', 'index-5', 's'));
	}

	/** Without a board list (a CLI run) a row lacking the name is skipped rather than fatal. */
	public function testRowsWithoutAStorageNameAreIgnoredWhenNoBoardListIsLoaded(): void {
		$a = threadFragmentCache::forStorageDirName('storage-1', $this->dir);
		$a->put('t1', 'index-5', 's', 'a1');

		threadFragments::forgetThreadPairs([['thread_uid' => 't1', 'boardUID' => 1]]);
		threadFragments::forgetThreadPairs(false);

		$this->assertSame('a1', $a->get('t1', 'index-5', 's'));
	}
}
