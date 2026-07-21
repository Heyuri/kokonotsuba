<?php

namespace Koko\Tests\Unit\Kokonotsuba;

use Koko\Tests\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Guards the deletion predicates against re-fragmenting.
 *
 * Post visibility was once decided by half a dozen hand-written variations on "find this post's
 * newest deletion row and look at it", scattered across repositories that had quietly drifted into
 * disagreeing with each other — one counted proxy-deleted replies as visible, another let a later
 * attachment deletion un-hide a deleted post. They were replaced by the shared helpers in
 * lib_query.php.
 *
 * Nothing stops the next person from writing a fresh one, so this scans the source for the shapes
 * that were removed. It is a lint, not a behavioural test: if it fails, the fix is normally to call
 * openDeletionExistsCondition() or openDeletionJoin() rather than to relax the assertion.
 */
final class DeletionPredicateUsageTest extends TestCase {

	/** Directories holding application SQL. */
	private const SCANNED_DIRS = ['/code', '/module'];

	/** lib_query.php defines the canonical helpers and documents the removed patterns in comments. */
	private const CANONICAL_SOURCE = 'code/Kokonotsuba/libraries/lib_query.php';

	/**
	 * Files allowed to spell out a deletion test themselves, with the reason.
	 *
	 * These are not asking "is this post visible?", so routing them through the shared predicate
	 * would change what they mean. Add to this list only when that is genuinely true — a longhand
	 * visibility test belongs in the helpers instead.
	 */
	private const EXEMPT = [
		// Asks which *files* in a thread already have an open deletion, counting both a deletion
		// of the file itself and a deletion of its parent post, while ignoring proxy deletions and
		// OP posts. It is a compound file-level question, not post visibility.
		'code/Kokonotsuba/post/attachment/fileRepository.php',
	];

	/**
	 * @return array<string, string> Relative path => file contents, for every scanned PHP file.
	 */
	private function sourceFiles(): array {
		$files = [];

		foreach (self::SCANNED_DIRS as $dir) {
			$root = KOKO_TEST_ROOT . $dir;
			$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

			foreach ($iterator as $file) {
				if ($file->isFile() && $file->getExtension() === 'php') {
					$relative = ltrim(str_replace(KOKO_TEST_ROOT, '', $file->getPathname()), '/');
					$files[$relative] = file_get_contents($file->getPathname());
				}
			}
		}

		return $files;
	}

	/**
	 * Strip // and # line comments and /* *\/ blocks, so prose describing a removed pattern does
	 * not read as a use of it.
	 */
	private function stripComments(string $source): string {
		$out = '';

		foreach (token_get_all($source) as $token) {
			if (is_array($token) && ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT)) {
				continue;
			}
			$out .= is_array($token) ? $token[1] : $token;
		}

		return $out;
	}

	public function testNoFileRanksDeletionRowsToFindTheLatestOne(): void {
		$offenders = [];

		foreach ($this->sourceFiles() as $path => $source) {
			$code = $this->stripComments($source);

			// The removed shape: group the deletion table by post_uid to pick each post's newest
			// row. Matching on the deleted-posts table specifically keeps unrelated MAX(id) uses
			// (post numbering, importers) out of scope.
			if (!str_contains($code, 'deletedPostsTable') && !str_contains($code, 'deleted_posts')) {
				continue;
			}

			if (preg_match('/MAX\(\s*(id|deleted_at)\s*\)\s*AS\s+max_/i', $code)) {
				$offenders[] = $path;
			}
		}

		$this->assertSame(
			[],
			$offenders,
			"These files rank deletion rows to find a post's current state. Use openDeletionExistsCondition() "
				. 'or openDeletionJoin(), which get the same answer from the uq_open_post unique index.'
		);
	}

	public function testTheRemovedLatestDeletionHelperIsGone(): void {
		$offenders = [];

		foreach ($this->sourceFiles() as $path => $source) {
			if (str_contains($this->stripComments($source), 'sqlLatestDeletionEntry')) {
				$offenders[] = $path;
			}
		}

		$this->assertSame([], $offenders, 'sqlLatestDeletionEntry() was removed; call openDeletionJoin() instead');
	}

	public function testOnlyTheCanonicalSourceSpellsOutThePostLevelDeletionTest(): void {
		$offenders = [];

		foreach ($this->sourceFiles() as $path => $source) {
			if ($path === self::CANONICAL_SOURCE || in_array($path, self::EXEMPT, true)) {
				continue;
			}

			$code = preg_replace('/\s+/', ' ', $this->stripComments($source));

			// "open_flag = 1 AND ... file_id IS NULL" in either order is the post-level deletion
			// test written out longhand — precisely what open_key encodes.
			$longhand = preg_match('/open_flag\s*=\s*1[^;]{0,120}?file_id IS NULL/i', $code)
				|| preg_match('/file_id IS NULL[^;]{0,120}?open_flag\s*=\s*1/i', $code);

			if ($longhand) {
				$offenders[] = $path;
			}
		}

		$this->assertSame(
			[],
			$offenders,
			'These files re-derive "open post-level deletion" by hand. Call openDeletionExistsCondition().'
		);
	}
}
