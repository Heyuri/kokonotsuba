<?php

namespace Koko\Tests\Unit\Kokonotsuba;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\config\configArrayMove;

/** The no-JS up/down arrows on an array setting's rows. */
final class ConfigArrayMoveTest extends TestCase {

	public function testMapEntryMovesUpKeepingItsKey(): void {
		$submitted = ['modulesEmotesEmotes' => '{"angry":"a.gif","cry":"c.gif","wink":"w.gif"}', 'other' => 'x'];
		$moved = configArrayMove::apply($submitted, 'modulesEmotesEmotes|2|up');

		$this->assertSame('{"angry":"a.gif","wink":"w.gif","cry":"c.gif"}', $moved['modulesEmotesEmotes']);
		$this->assertSame('x', $moved['other']);
	}

	public function testListEntryMovesDown(): void {
		$moved = configArrayMove::apply(['list' => '["a","b","c"]'], configArrayMove::buttonValue('list', 0, configArrayMove::DOWN));

		$this->assertSame('["b","a","c"]', $moved['list']);
	}

	public function testUnicodeSurvivesTheMove(): void {
		$moved = configArrayMove::apply(['kao' => '{"(;´Д`)":"[kao](;´Д`)[/kao]","(´ー`)":"x"}'], 'kao|1|up');

		$this->assertSame('{"(´ー`)":"x","(;´Д`)":"[kao](;´Д`)[/kao]"}', $moved['kao']);
	}

	/** Numeric-looking keys must not turn the map into a list on the way back out. */
	public function testMapWithNumericKeysStaysAnObject(): void {
		$moved = configArrayMove::apply(['m' => '{"0":"a","1":"b"}'], 'm|0|down');

		$this->assertSame('{"1":"b","0":"a"}', $moved['m']);
	}

	public function testMovesOffEitherEndChangeNothing(): void {
		$submitted = ['list' => '["a","b"]'];

		$this->assertSame($submitted, configArrayMove::apply($submitted, 'list|0|up'));
		$this->assertSame($submitted, configArrayMove::apply($submitted, 'list|1|down'));
		$this->assertSame($submitted, configArrayMove::apply($submitted, 'list|9|up'));
	}

	public function testMalformedMovesAreIgnored(): void {
		$submitted = ['list' => '["a","b"]', 'text' => 'not json'];

		foreach ([null, '', ['list|1|up'], 'list|1|sideways', 'list|-1|down', 'missing|1|up', 'text|1|up', 'li-st|1|up'] as $move) {
			$this->assertSame($submitted, configArrayMove::apply($submitted, $move));
		}

		$this->assertSame(null, configArrayMove::fieldOf('list|1|sideways'));
		$this->assertSame('list', configArrayMove::fieldOf('list|1|up'));
	}
}
