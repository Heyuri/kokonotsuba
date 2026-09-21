<?php

namespace Koko\Tests\Unit\Kokonotsuba;

use Koko\Tests\Framework\InMemoryTextQuoteRepository;
use Koko\Tests\Framework\TestCase;
use Kokonotsuba\quote_link\textQuoteResolver;

/** Which post a quote resolves to, against a thread held in memory. */
final class TextQuoteResolverTest extends TestCase {

	private function post(int $uid, string $com, bool $isOp = false, array $files = [], bool $deleted = false, int $format = 1): array {
		return ['post_uid' => $uid, 'no' => $uid + 1000, 'is_op' => $isOp, 'com' => $com, 'text_format' => $format, 'files' => $files, 'deleted' => $deleted];
	}

	private function resolve(array $posts, int $beforeUid, string $needle, bool $quoted = false): ?int {
		return (new textQuoteResolver(new InMemoryTextQuoteRepository($posts)))->resolve('t', $beforeUid, $needle, $quoted);
	}

	/** The post the quote is typed in; every lookup is made from a post that exists. */
	private function quotingPost(int $uid = 20): array {
		return $this->post($uid, 'zzz nothing to match here zzz');
	}

	private function thread(): array {
		return [
			$this->post(10, 'the opening post mentions cats', true, [['op image', 'png']]),
			$this->post(11, 'cats are fine'),
			$this->post(12, ">cats are fine\nno they are not"),
			$this->post(13, 'dogs though', false, [['dog', 'jpg']]),
			$this->post(14, 'cats are fine', false, [], true),
			$this->post(15, '>dogs though'),
			$this->quotingPost(),
		];
	}

	public function testAnOrdinaryLookupIsOneStatement(): void {
		foreach ([['cats are fine', false], ['dog.jpg', false], ['No.1011', false], ['nothing at all', false]] as [$needle, $quoted]) {
			$repository = new InMemoryTextQuoteRepository($this->thread());
			(new textQuoteResolver($repository))->resolve('t', 20, $needle, $quoted);

			$this->assertSame(1, $repository->queries, "lookup of " . var_export($needle, true));
		}
	}

	public function testNearestEarlierPostWins(): void {
		$this->assertSame(11, $this->resolve($this->thread(), 20, 'cats are fine'));
		$this->assertSame(13, $this->resolve($this->thread(), 20, 'dogs though'));
	}

	public function testLaterPostsAreIgnored(): void {
		$this->assertNull($this->resolve($this->thread(), 13, 'dogs though'));
		$this->assertSame(10, $this->resolve($this->thread(), 11, 'cats'));
	}

	public function testOpeningPostIsTheLastResort(): void {
		$this->assertSame(10, $this->resolve($this->thread(), 20, 'opening post'));
		$this->assertSame(11, $this->resolve($this->thread(), 20, 'cats'));
	}

	public function testDeletedPostsAreInvisible(): void {
		$this->assertSame(11, $this->resolve($this->thread(), 15, 'cats are fine'));
	}

	public function testQuoteOfAQuote(): void {
		$this->assertSame(12, $this->resolve($this->thread(), 20, 'cats are fine', true));
		$this->assertSame(15, $this->resolve($this->thread(), 20, 'dogs though', true));
	}

	public function testFileNames(): void {
		$this->assertSame(13, $this->resolve($this->thread(), 20, 'dog.jpg'));
		$this->assertSame(10, $this->resolve($this->thread(), 20, 'op image.png'));
		$this->assertNull($this->resolve($this->thread(), 20, 'DOG.jpg'), 'file names are compared exactly');
		$this->assertNull($this->resolve($this->thread(), 20, 'dog.png'));
	}

	public function testANearerCommentBeatsAFartherFile(): void {
		$posts = $this->thread();
		$posts[] = $this->post(16, 'someone typed dog.jpg here');

		$this->assertSame(16, $this->resolve($posts, 20, 'dog.jpg'));
	}

	public function testALookupFromAPostOfAnotherThreadFindsNothing(): void {
		$resolver = new textQuoteResolver(new InMemoryTextQuoteRepository($this->thread()));

		$this->assertNull($resolver->resolve('another-thread', 20, 'cats are fine', false));
	}

	public function testALookupFromAPostThatDoesNotExistFindsNothing(): void {
		$this->assertNull($this->resolve($this->thread(), 9999, 'cats are fine'));
	}

	public function testPostNumbers(): void {
		$this->assertSame(11, $this->resolve($this->thread(), 20, '1011'));
		$this->assertSame(10, $this->resolve($this->thread(), 20, 'No.1010'));
		$this->assertNull($this->resolve($this->thread(), 12, 'No.1013'), 'a later post');
		$this->assertNull($this->resolve($this->thread(), 20, '1014'), 'a deleted post');
		$this->assertNull($this->resolve($this->thread(), 20, '0'));
	}

	public function testANumberNeverFallsBackToText(): void {
		$posts = [$this->post(10, 'op', true), $this->post(11, 'the year 1999 was good')];

		$this->assertNull($this->resolve($posts, 20, '1999'));
	}

	public function testNothingComesBeforeAnOpeningPost(): void {
		// after a merge the OP can hold a higher uid than its replies
		$posts = [$this->post(50, 'merged op', true), $this->post(11, 'older reply text'), $this->quotingPost(60)];

		$this->assertNull($this->resolve($posts, 50, 'older reply'));
		$this->assertSame(50, $this->resolve($posts, 11, 'merged op'));
	}

	public function testCandidatesThatOnlyRepeatTheQuoteAreSkippedAcrossBatches(): void {
		$posts = [$this->post(1, 'op', true), $this->post(2, 'the original line')];
		for ($uid = 3; $uid < 70; $uid++) {
			$posts[] = $this->post($uid, '>the original line');
		}
		$posts[] = $this->quotingPost(100);

		$this->assertSame(2, $this->resolve($posts, 100, 'the original line'));
	}

	public function testALookupGivesUpAfterItsBatches(): void {
		$posts = [$this->post(1, 'op', true), $this->post(2, 'the original line')];
		for ($uid = 3; $uid < 400; $uid++) {
			$posts[] = $this->post($uid, '>the original line');
		}
		$posts[] = $this->quotingPost(1000);

		$repository = new InMemoryTextQuoteRepository($posts);
		$this->assertNull((new textQuoteResolver($repository))->resolve('t', 1000, 'the original line', false));
		$this->assertSame(textQuoteResolver::MAX_STATEMENTS, $repository->queries, 'the worst case is still a handful');
	}

	public function testRepliesBeyondTheWindowAreNotSearched(): void {
		$posts = [$this->post(1, 'op', true), $this->post(2, 'needle in the distant past')];
		for ($uid = 3; $uid < textQuoteResolver::REPLY_WINDOW + 10; $uid++) {
			$posts[] = $this->post($uid, 'filler');
		}
		$posts[] = $this->quotingPost(5000);

		$this->assertNull($this->resolve($posts, 5000, 'distant past'));
		$this->assertSame(2, $this->resolve($posts, 500, 'distant past'));
	}

	public function testLikeWildcardsInANeedleAreLiteral(): void {
		$posts = [$this->post(1, 'op', true), $this->post(2, 'completely unrelated'), $this->post(3, '100% sure_thing'), $this->quotingPost(9)];

		$this->assertSame(3, $this->resolve($posts, 9, '100% sure_thing'));
		$this->assertNull($this->resolve($posts, 9, '%'.'unrelated'));
		$this->assertNull($this->resolve($posts, 9, 'c_mpletely'));
	}

	public function testLegacyRowsAreFoundThroughTheirEscapedForm(): void {
		$posts = [$this->post(1, 'op', true), $this->post(2, 'Tom &amp; Jerry &lt;3', false, [], false, 0), $this->quotingPost(9)];

		$this->assertSame(2, $this->resolve($posts, 9, 'Tom & Jerry <3'));
	}

	public function testBadArgumentsResolveToNothing(): void {
		$this->assertNull($this->resolve($this->thread(), 0, 'cats'));
		$this->assertNull($this->resolve($this->thread(), 20, ''));
		$this->assertNull((new textQuoteResolver(new InMemoryTextQuoteRepository($this->thread())))->resolve('', 20, 'cats', false));
	}
}
