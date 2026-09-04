<?php

namespace Koko\Tests\Unit\Kokonotsuba;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\action_log\actionLogReferences;

/**
 * The references a log line carries and the links they turn into.
 *
 * A reference is written into the log row verbatim, so its token format is part of the stored
 * format: an entry written today has to still render years later, with or without the module
 * that resolves it.
 */
final class ActionLogReferencesTest extends TestCase {

	public function testReferenceBuildsAToken(): void {
		$this->assertSame('{{ban:12|Ban #12}}', actionLogReferences::reference('ban', 12, 'Ban #12'));
	}

	/** Nothing may put a second token's syntax inside a label. */
	public function testLabelCannotBreakOutOfTheToken(): void {
		$this->assertSame('{{ban:12|Ban12}}', actionLogReferences::reference('ban', 12, '{{Ban|12}}'));
	}

	/** A caller should never have to check: an unusable kind or id degrades to plain text. */
	public function testUnusableReferenceFallsBackToItsLabel(): void {
		$this->assertSame('Ban #12', actionLogReferences::reference('ba n', 12, 'Ban #12'));
		$this->assertSame('Ban #12', actionLogReferences::reference('ban', 'twelve/12', 'Ban #12'));
		$this->assertSame('', actionLogReferences::reference('ban', 12, ''));
	}

	public function testResolvedReferenceBecomesALink(): void {
		$references = new actionLogReferences();
		$references->register('ban', fn(string $id): string => '/koko.php?banId=' . $id);

		$this->assertSame(
			'<a href="/koko.php?banId=12">Ban #12</a> blocked a visitor',
			$references->toHtml('{{ban:12|Ban #12}} blocked a visitor', 0)
		);
	}

	/** An entry outlives the module that wrote it, so an unresolved reference is still readable. */
	public function testUnresolvedReferenceRendersAsItsLabel(): void {
		$references = new actionLogReferences();

		$this->assertSame('Ban #12 blocked a visitor', $references->toHtml('{{ban:12|Ban #12}} blocked a visitor', 0));
	}

	/** A resolver may decline - a row that has since been deleted has nowhere to point. */
	public function testNullFromAResolverRendersAsItsLabel(): void {
		$references = new actionLogReferences();
		$references->register('ban', fn(string $id): ?string => null);

		$this->assertSame('Ban #12', $references->toHtml('{{ban:12|Ban #12}}', 0));
	}

	/** The post-number pattern as the action log registers it. */
	private function postNumbers(): actionLogReferences {
		$references = new actionLogReferences();
		$references->registerPattern('post', '/\bNo\.\s?(\d+)\b/');
		$references->register('post', fn(string $id): string => '/post/' . $id);

		return $references;
	}

	public function testResolverIsGivenTheBoardUid(): void {
		$references = new actionLogReferences();
		$references->register('ban', fn(string $id, int $boardUid): string => "/{$boardUid}/ban/{$id}");

		$this->assertSame('<a href="/7/ban/12">Ban #12</a>', $references->toHtml('{{ban:12|Ban #12}}', 7));
	}

	/** Log lines are staff-written and visitor-influenced prose: none of it may reach the page raw. */
	public function testSurroundingTextAndLabelsAreEscaped(): void {
		$references = new actionLogReferences();
		$references->register('ban', fn(string $id): string => '/koko.php?a=1&b=2');

		$this->assertSame(
			'&lt;script&gt; <a href="/koko.php?a=1&amp;b=2">Ban &amp; #12</a>',
			$references->toHtml('<script> {{ban:12|Ban & #12}}', 0)
		);
	}

	/** Post numbers are prose the log has always written, so they are matched by their shape. */
	public function testRegisteredPatternLinksProse(): void {
		$references = $this->postNumbers();

		$this->assertSame(
			'Purged thread <a href="/post/1234">No.1234</a>',
			$references->toHtml('Purged thread No.1234', 3)
		);
		$this->assertSame(
			'Warned 1.2.3.4 for post <a href="/post/12">No. 12</a>',
			$references->toHtml('Warned 1.2.3.4 for post No. 12', 3)
		);
	}

	public function testEveryPostNumberInAListIsLinked(): void {
		$references = $this->postNumbers();

		$this->assertSame(
			'Deleted post <a href="/post/1">No.1</a>, <a href="/post/2">No.2</a>',
			$references->toHtml('Deleted post No.1, No.2', 3)
		);
	}

	/** A post that has since been purged has nowhere to point, and must still read normally. */
	public function testUnresolvedPatternKeepsItsText(): void {
		$references = new actionLogReferences();
		$references->registerPattern('post', '/\bNo\.\s?(\d+)\b/');

		$this->assertSame('Purged thread No.1234', $references->toHtml('Purged thread No.1234', 3));
	}

	/** A pattern must not reach inside a token's label, or one link ends up nested in another. */
	public function testPatternsDoNotMatchInsideAReference(): void {
		$references = $this->postNumbers();
		$references->register('ban', fn(string $id): string => '/ban/' . $id);

		$this->assertSame(
			'<a href="/ban/12">Ban #12 for No.7</a>',
			$references->toHtml('{{ban:12|Ban #12 for No.7}}', 3)
		);
	}

	public function testTextStripsReferencesToTheirLabels(): void {
		$references = new actionLogReferences();

		$this->assertSame('Ban #12 blocked a visitor', $references->toText('{{ban:12|Ban #12}} blocked a visitor'));
	}
}
