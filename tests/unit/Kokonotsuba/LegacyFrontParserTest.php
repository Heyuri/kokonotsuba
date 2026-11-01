<?php

namespace Koko\Tests\Unit\Kokonotsuba;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\front_pages\legacyFrontParser;

/** Reading the old Heyuri front page's news and page files. */
final class LegacyFrontParserTest extends TestCase {
	private static function newsTable(string $id, string $headline, string $body = '<p>Body</p>'): string {
		return <<<HTML
		<table id="{$id}" width="100%" cellpadding="0" cellspacing="0">
		<thead><tr><th align="left">
		<h4 class="headline"><span class="barctl"><a href="#{$id}" title="Link to this post">#</a>&nbsp;</span>
		{$headline}
		</h4>
		</th></tr></thead>
		<tbody><tr><td class="newsbody">
		{$body}
		</td></tr></tbody>
		</table>
		HTML;
	}

	public function testNewsComesBackOldestFirstWithBothDateFormats(): void {
		$html = '<div id="news">'
			. self::newsTable('news2', 'NEW<small> by <a href="mailto:a@example.net">kaguya</a> - 2025/08/29 @ 19:17 UTC</small>', '<p>New &amp; shiny</p>')
			. self::newsTable('news1_0', 'OLD<small> by <a href="mailto:b@example.net">kuz</a> - 04/26/22 @ 11:03AM EDT</small>')
			. '</div>';

		$errors = [];
		$entries = legacyFrontParser::parseNews($html, $errors);

		$this->assertSame([], $errors);
		$this->assertSame(['OLD', 'NEW'], array_column($entries, 'subject'));
		$this->assertSame('2022-04-26 15:03', $entries[0]['createdAt']->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i'));
		$this->assertSame('2025-08-29 19:17', $entries[1]['createdAt']->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i'));
		$this->assertSame('<p>New &amp; shiny</p>', $entries[1]['body']);
		$this->assertSame(['kaguya', 'a@example.net'], [$entries[1]['byline'], $entries[1]['email']]);
	}

	public function testOddBylinesAndSubjectsBecomePlainText(): void {
		$html = self::newsTable('news3', '<a href="https://example.net/">HOO</a> <small>by hardgay_tomo -  12/02/20 @ 12:54AM EDT</small>')
			. self::newsTable('news2', 'TWO<small> by <a href="mailto:a@example.net">kuz</a> and <a href="mailto:b@example.net">Yaril</a> - 12/28/20 @ 11:58PM UTC</small>')
			. self::newsTable('news1', 'TEAM<small> by <a href="mailto:heyuri.net">&quot;The Team&quot;</a> - 07/16/20 @ 2:50AM EDT</small>');

		$entries = legacyFrontParser::parseNews($html);

		$this->assertSame(['TEAM', 'TWO', 'HOO'], array_column($entries, 'subject'));
		$this->assertSame(['"The Team"', 'kuz and Yaril', 'hardgay_tomo'], array_column($entries, 'byline'));
		$this->assertSame(['', '', ''], array_column($entries, 'email'));
	}

	public function testAnUnreadableHeadlineIsReportedAndLeftOut(): void {
		$html = self::newsTable('news2', 'NO DATE<small> by kuz</small>')
			. self::newsTable('news1', 'FINE<small> by kuz - 2020/01/01 @ 00:00 UTC</small>');

		$errors = [];
		$entries = legacyFrontParser::parseNews($html, $errors);

		$this->assertSame(['news2'], $errors);
		$this->assertSame(['FINE'], array_column($entries, 'subject'));
	}

	public function testPageTitleIsTheFirstHeading(): void {
		$this->assertSame('Privacy Disclaimer', legacyFrontParser::pageTitle('privacy', "<h3 class=\"headline\">Privacy <b>Disclaimer</b></h3>\n<h4 class=\"headline\">What</h4>"));
		$this->assertSame('TOP TIPS', legacyFrontParser::pageTitle('tips', '<div class="newsbody"><h4 class="headline">TOP TIPS</h4></div>'));
		$this->assertSame('oc', legacyFrontParser::pageTitle('oc', '<p>No heading</p>'));
	}

	/** Every literal form the old index.php could use, checked against what PHP itself makes of it. */
	public function testSubtitlesMatchWhatPhpEvaluates(): void {
		$literals = <<<'PHP'
	'single \'quoted\' \\ \n',
	"double \"quoted\" \d \$x \x41 \u{3042} \101 \8",
	<<<EOF
heredoc "kept" \"also kept\" \d \x41
<script>var i=0;
setInterval(function(){}, 200);</script>
EOF
,
	<<<'EOF'
nowdoc \n $raw
EOF
,
PHP;
		$expected = eval("return [{$literals}];");
		$expected = array_map(static fn(string $text): string => trim((string)preg_replace('/\s*\R\s*/', ' ', $text)), $expected);

		$php = "<?php\n// define('FRONTTEXTS', ['commented out']);\ndefine('LOGO', 'x.png');\ndefine('FRONTTEXTS', [\n{$literals}\n]);\n\$fronttext = FRONTTEXTS[0];\n";

		$this->assertSame($expected, legacyFrontParser::parseSubtitles($php));
	}

	public function testAnInterpolatingSubtitleIsLeftOut(): void {
		$php = "<?php\ndefine('FRONTTEXTS', [\n'kept',\n<<<EOF\nhello {\$name}\nEOF\n,\n\"also kept\",\n]);\n";

		$this->assertSame(['kept', 'also kept'], legacyFrontParser::parseSubtitles($php));
	}

	public function testNoFronttextsMeansNoSubtitles(): void {
		$this->assertSame([], legacyFrontParser::parseSubtitles("<?php\ndefine('TITLE', 'Heyuri');\n"));
	}
}
