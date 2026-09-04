<?php

namespace Koko\Tests\Unit\Kokonotsuba\Renderers;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\renderers\post\postMenu;

/** The post dropdown: hidden refs for the JS plus the bracketed row for browsers without it. */
final class PostMenuTest extends TestCase {

	public function testEmptyMenuHasNoNoscriptRow(): void {
		$html = (new postMenu())->toHtml();

		$this->assertStringContains('<div class="postMenu">', $html);
		$this->assertStringContains('<div class="widgetRefs" hidden></div>', $html);
		$this->assertStringNotContains('<noscript>', $html);
	}

	public function testEntryBecomesARefAndANoscriptLink(): void {
		$menu = new postMenu();
		$menu->append([['href' => '/x?a=1', 'action' => 'go', 'label' => 'Go', 'subMenu' => 'more', 'params' => ['id' => 7]]]);

		$html = $menu->toHtml();

		$this->assertStringContains('<a href="/x?a=1" data-action="go" data-label="Go" data-subMenu="more" data-param-id="7"></a>', $html);
		$this->assertStringContains('<noscript><span class="noscriptMenu">[<a href="/x?a=1">Go</a>]</span></noscript>', $html);
	}

	public function testJsOnlyEntryHasNoNoscriptLink(): void {
		$menu = new postMenu();
		$menu->append([['href' => '#', 'action' => 'hide', 'label' => 'Hide']]);

		$html = $menu->toHtml();

		$this->assertStringContains('data-action="hide"', $html);
		$this->assertStringNotContains('<noscript>', $html);
	}

	public function testLaterAppendsFollowEarlierOnes(): void {
		$menu = new postMenu();
		$menu->append([['href' => '/a', 'action' => 'a', 'label' => 'A']]);
		$menu->append([['href' => '/b', 'action' => 'b', 'label' => 'B']]);

		$html = $menu->toHtml();

		$this->assertStringContains('[<a href="/a">A</a>] [<a href="/b">B</a>]', $html);
		$this->assertTrue(strpos($html, 'data-action="a"') < strpos($html, 'data-action="b"'));
	}

	public function testRefAttributesAreEscaped(): void {
		$menu = new postMenu();
		$menu->append([['href' => '/x?a=1&b=2', 'action' => 'a', 'label' => '<b>', 'params' => ['q' => '"']]]);

		$html = $menu->toHtml();

		$this->assertStringContains('href="/x?a=1&amp;b=2"', $html);
		$this->assertStringContains('data-label="&lt;b&gt;"', $html);
		$this->assertStringContains('data-param-q="&quot;"', $html);
	}
}
