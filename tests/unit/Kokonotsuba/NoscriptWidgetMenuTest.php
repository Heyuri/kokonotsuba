<?php

namespace Koko\Tests\Unit\Kokonotsuba;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\renderers\noscriptWidgetMenu;

/** The [Link] row a menu falls back to without JavaScript. */
final class NoscriptWidgetMenuTest extends TestCase {

	public function testRendersBracketedLinksSeparatedBySpaces(): void {
		$html = noscriptWidgetMenu::render([
			['href' => '/a', 'label' => 'EXIF'],
			['href' => 'http://imgops.com/x', 'label' => 'ImgOps', 'params' => ['target' => '_blank']],
		]);

		$this->assertSame('[<a href="/a">EXIF</a>] [<a href="http://imgops.com/x" target="_blank">ImgOps</a>]', $html);
	}

	public function testSkipsEntriesThatOnlyWorkWithJavascript(): void {
		$html = noscriptWidgetMenu::render([
			['href' => '#', 'label' => 'Hide image'],
			['href' => '', 'label' => 'Hide'],
			['href' => 'javascript:void(0)', 'label' => 'Toggle'],
		]);

		$this->assertSame('', $html);
	}

	public function testEscapesHrefAndLabel(): void {
		$html = noscriptWidgetMenu::render([['href' => '/x?a=1&b=2', 'label' => '<b>']]);

		$this->assertSame('[<a href="/x?a=1&amp;b=2">&lt;b&gt;</a>]', $html);
	}
}
