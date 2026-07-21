<?php

namespace Koko\Tests\Unit\Kokonotsuba;

use Koko\Tests\Framework\TestCase;

use function Kokonotsuba\libraries\coalescedAttachmentColumns;
use function Kokonotsuba\libraries\excludeDeletedPostsCondition;
use function Kokonotsuba\libraries\excludeDeletedThreadsCondition;
use function Kokonotsuba\libraries\getBasePostQuery;
use function Kokonotsuba\libraries\objectivePositionSubquery;
use function Kokonotsuba\libraries\openDeletionExistsCondition;
use function Kokonotsuba\libraries\openDeletionJoin;
use function Kokonotsuba\libraries\openFileDeletionExistsCondition;

/**
 * Tests for the deletion-state predicates and the query builders that use them.
 *
 * "Is this post deleted?" has one answer — an open post-level row exists in the deleted-posts
 * table — but the table also holds every past deletion and restore, so the question used to be
 * answered by grouping the whole table to find each post's newest row. That was written out by
 * hand in several places that drifted apart from each other. These tests pin the single spelling
 * that replaced them: a lookup on the open_key generated column, whose UNIQUE index guarantees at
 * most one open post-level row per post.
 *
 * Everything under test is a pure string builder, so no database is involved. The semantics those
 * strings produce once MariaDB evaluates them are covered separately by the integration suite.
 */
final class DeletionPredicateTest extends TestCase {

	/** Table names are passed in by the caller in the real app; fixed stand-ins keep assertions readable. */
	private const POSTS = 'posts';
	private const DELETED = 'deleted_posts';
	private const FILES = 'files';
	private const THREADS = 'threads';
	private const SOUDANE = 'soudane_votes';
	private const NOTES = 'notes';
	private const ACCOUNTS = 'accounts';

	protected function setUp(): void {
		// lib_query.php holds namespaced functions and is required explicitly by the app's
		// bootstrap rather than autoloaded, so the test does the same.
		require_once KOKO_TEST_ROOT . '/code/Kokonotsuba/libraries/lib_query.php';
	}

	/** Build the deletion-centric query used by the deleted-posts admin pages. */
	private function deletionCentricQuery(): string {
		return getBasePostQuery(
			self::POSTS, self::DELETED, self::FILES, self::THREADS,
			self::SOUDANE, self::NOTES, self::ACCOUNTS,
			true, true
		);
	}

	/** Build the post-centric query used for board indexes, threads and search. */
	private function postCentricQuery(bool $viewDeleted): string {
		return getBasePostQuery(
			self::POSTS, self::DELETED, self::FILES, self::THREADS,
			self::SOUDANE, self::NOTES, self::ACCOUNTS,
			$viewDeleted, false
		);
	}

	/** Collapse runs of whitespace so assertions do not depend on SQL indentation. */
	private function flatten(string $sql): string {
		return preg_replace('/\s+/', ' ', $sql);
	}

	// ---- the canonical primitives ------------------------------------------

	public function testOpenDeletionExistsTestsTheGivenPostAgainstOpenKey(): void {
		$sql = $this->flatten(openDeletionExistsCondition(self::DELETED, 'p.post_uid'));

		$this->assertStringContains('EXISTS (', $sql, 'should be an EXISTS test');
		$this->assertStringContains('FROM deleted_posts', $sql, 'should read the deleted-posts table');
		$this->assertStringContains('open_key = p.post_uid', $sql, 'should match on the open_key unique index');
	}

	public function testOpenDeletionExistsDoesNotSearchForALatestRow(): void {
		$sql = $this->flatten(openDeletionExistsCondition(self::DELETED, 'p.post_uid'));

		// The whole point of open_key is that no "newest row per post" step is needed: the unique
		// index means at most one open post-level row can exist, so the lookup is already exact.
		$this->assertStringNotContains('MAX(', $sql, 'must not scan for a latest row');
		$this->assertStringNotContains('GROUP BY', $sql, 'must not group the deletion table');
	}

	public function testOpenDeletionExistsCarriesNoLeadingBooleanOperator(): void {
		// Callers compose this into larger clauses themselves ("AND NOT " . condition), so a
		// leading operator baked into the string would produce invalid SQL at half the call sites.
		$sql = trim(openDeletionExistsCondition(self::DELETED, 'p.post_uid'));

		$this->assertTrue(str_starts_with($sql, 'EXISTS'), 'should start at EXISTS, with no AND/NOT prefix');
	}

	public function testOpenDeletionJoinAttachesAtMostOneRowViaOpenKey(): void {
		$sql = $this->flatten(openDeletionJoin(self::DELETED, 't.post_op_post_uid', 'dp'));

		$this->assertSame(
			'LEFT JOIN deleted_posts dp ON dp.open_key = t.post_op_post_uid',
			trim($sql),
			'join should be a plain unique-index lookup'
		);
	}

	public function testOpenFileDeletionIsADifferentQuestionFromPostDeletion(): void {
		$fileLevel = $this->flatten(openFileDeletionExistsCondition(self::DELETED, 'p.post_uid'));
		$postLevel = $this->flatten(openDeletionExistsCondition(self::DELETED, 'p.post_uid'));

		// Attachment-level deletions leave the post visible, so they must not be found by the
		// post-level predicate, and vice versa.
		$this->assertStringContains('file_id IS NOT NULL', $fileLevel, 'should select attachment-level rows');
		$this->assertStringNotContains('open_key', $fileLevel, 'open_key deliberately excludes attachment-level rows');
		$this->assertNotSame($postLevel, $fileLevel, 'the two predicates must not be interchangeable');
	}

	public function testExcludeDeletedThreadsTestsTheThreadsOpPost(): void {
		$sql = $this->flatten(excludeDeletedThreadsCondition(self::DELETED));

		$this->assertTrue(str_starts_with($sql, ' AND NOT EXISTS'), 'should be appendable to a WHERE clause');
		$this->assertStringContains('open_key = t.post_op_post_uid', $sql, 'a thread is deleted when its OP is');
	}

	public function testExcludeDeletedPostsReadsTheJoinedRow(): void {
		// Expects a row already attached by openDeletionJoin(); that join only ever matches open
		// post-level deletions, so its absence is exactly the post being visible.
		$this->assertSame(' AND dp.open_key IS NULL', excludeDeletedPostsCondition(), 'default alias is dp');
		$this->assertSame(' AND d.open_key IS NULL', excludeDeletedPostsCondition('d'), 'alias should be honoured');
	}

	// ---- deletion-centric query (the deleted-posts pages) -------------------

	public function testDeletionCentricJoinsTheFileTableOnceForEachWayAnAttachmentIsReached(): void {
		$sql = $this->flatten($this->deletionCentricQuery());

		// A file-only deletion names one attachment by id; a post-level deletion takes all of the
		// post's attachments. Expressed as a single OR'd join these two keys cannot both be
		// indexed, so they are split into two ref joins.
		$this->assertStringContains('LEFT JOIN files fk ON fk.id = dp.file_id', $sql, 'should join by file id');
		$this->assertStringContains('LEFT JOIN files pk ON pk.post_uid = dp.post_uid', $sql, 'should join by post uid');
		$this->assertStringNotContains('dp.file_id IS NOT NULL AND', $sql, 'the OR-ed join should be gone');
	}

	public function testDeletionCentricSelectsAttachmentColumnsFromWhicheverFileJoinMatched(): void {
		$sql = $this->flatten($this->deletionCentricQuery());

		$this->assertStringContains('COALESCE(fk.id, pk.id) AS attachment_id', $sql, 'should coalesce the two aliases');
		$this->assertStringContains(
			'COALESCE(fk.stored_filename, pk.stored_filename) AS attachment_stored_filename',
			$sql,
			'every attachment column should be coalesced, not just the id'
		);
	}

	public function testBothQueryModesExposeIdenticalAttachmentColumnAliases(): void {
		// Row-merging (mergeRowIntoPost/buildAttachment) reads attachments by alias and is shared
		// by both modes, so the two column lists have to stay in lockstep. Splitting the
		// deletion-centric join into two aliases is exactly the kind of change that could drift.
		preg_match_all('/AS (attachment_\w+)/', $this->deletionCentricQuery(), $deletionCentric);
		preg_match_all('/AS (attachment_\w+)/', $this->postCentricQuery(true), $postCentric);

		$a = $deletionCentric[1];
		$b = $postCentric[1];
		sort($a);
		sort($b);

		$this->assertSame($b, $a, 'attachment aliases must match between the two query modes');
	}

	public function testDeletionCentricDoesNotAggregateTheWholeVotesTable(): void {
		$sql = $this->flatten($this->deletionCentricQuery());

		// The shared soudane join is an unfiltered aggregate that MariaDB cannot merge, so it gets
		// materialised in full on every call. These pages show one page of posts at a time.
		$this->assertStringNotContains('GROUP BY post_uid', $sql, 'must not group the entire votes table');
	}

	public function testDeletionCentricStillReportsVoteCounts(): void {
		$sql = $this->flatten($this->deletionCentricQuery());

		// Deleted posts render through the same postRenderer as live ones, and the soudane module
		// reads these off the Post object, so dropping them would silently zero every score.
		foreach (['votes_total_count', 'votes_yeah_count', 'votes_nope_count'] as $column) {
			$this->assertStringContains($column, $sql, "should still select {$column}");
		}
		$this->assertStringContains('FROM soudane_votes sv WHERE sv.post_uid = dp.post_uid', $sql, 'counts should be correlated per post');
	}

	// ---- post-centric query (board index, threads, search) ------------------

	public function testPostCentricHidesPostsWithAnOpenPostLevelDeletion(): void {
		$sql = $this->flatten($this->postCentricQuery(false));

		$this->assertStringContains('WHERE NOT EXISTS', $sql, 'visible posts are those with no open deletion');
		$this->assertStringContains('open_key = p1.post_uid', $sql, 'should filter via the open_key index');
	}

	public function testPostCentricIncludesEverythingWhenViewingDeleted(): void {
		$sql = $this->flatten($this->postCentricQuery(true));

		$this->assertStringContains('SELECT * FROM posts', $sql, 'admin views should not filter at all');
	}

	public function testPostCentricKeepsItsOwnVoteAggregate(): void {
		$sql = $this->flatten($this->postCentricQuery(false));

		// Only the deletion-centric branch was converted to correlated subqueries; the post-centric
		// aggregate feeds mergeMultiplePostRows and is deliberately left as-is.
		$this->assertStringContains('GROUP BY post_uid', $sql, 'the soudane aggregate should be untouched');
		$this->assertStringContains('votes_yeah_count', $sql, 'should still select vote counts');
	}

	public function testNeitherQueryModeSearchesForALatestDeletionRow(): void {
		foreach (['deletion-centric' => $this->deletionCentricQuery(),
		          'post-centric visible' => $this->postCentricQuery(false),
		          'post-centric all' => $this->postCentricQuery(true)] as $label => $sql) {
			$this->assertStringNotContains('MAX(id)', $this->flatten($sql), "{$label} must not rank deletion rows");
			$this->assertStringNotContains('max_id', $this->flatten($sql), "{$label} must not rank deletion rows");
		}
	}

	// ---- objective position -------------------------------------------------

	public function testObjectivePositionSkipsDeletedRepliesViaOpenKey(): void {
		$sql = $this->flatten(objectivePositionSubquery(self::POSTS, self::DELETED, 'p', false));

		// Pagination slices on this ordinal, so it has to count the same replies the page renders.
		$this->assertStringContains('open_key = objpos.post_uid', $sql, 'should exclude deleted replies');
		$this->assertStringNotContains('MAX(id)', $sql, 'must not rank deletion rows');
	}

	public function testObjectivePositionCountsEveryReplyWhenViewingDeleted(): void {
		$sql = $this->flatten(objectivePositionSubquery(self::POSTS, self::DELETED, 'p', true));

		$this->assertStringNotContains('deleted_posts', $sql, 'admin pagination counts deleted replies too');
	}

	// ---- the column-list helper --------------------------------------------

	public function testCoalescedAttachmentColumnsCoversEveryAttachmentField(): void {
		$sql = coalescedAttachmentColumns('fk', 'pk');

		// 16 attachment columns are read by buildAttachment(); a short list here would surface as
		// missing thumbnails or file sizes rather than an error.
		$this->assertSame(16, substr_count($sql, 'COALESCE('), 'every attachment column should be present');
		$this->assertStringNotContains('f.', $sql, 'should not reference the old single-alias join');
	}
}
