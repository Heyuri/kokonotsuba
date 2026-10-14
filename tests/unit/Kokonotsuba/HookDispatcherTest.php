<?php

namespace Koko\Tests\Unit\Kokonotsuba;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\error\BoardException;
use Kokonotsuba\module_classes\hookDispatcher;
use Kokonotsuba\userRole;

/** Hook dispatch: priorities, by-reference parameters and the role-protected wrapper. */
class HookDispatcherTest extends TestCase {

	/** @var list<string> */
	private array $warnings = [];

	protected function setUp(): void {
		$this->warnings = [];
		set_error_handler(function (int $no, string $message): bool {
			$this->warnings[] = $message;
			return true;
		});
	}

	protected function tearDown(): void {
		restore_error_handler();
	}

	public function testHigherPriorityRunsFirst(): void {
		$d = new hookDispatcher();
		$order = [];
		$d->addListener('X', function () use (&$order) { $order[] = 'low'; }, 0);
		$d->addListener('X', function () use (&$order) { $order[] = 'high'; }, 10);
		$d->dispatch('X');

		$this->assertSame(['high', 'low'], $order);
	}

	public function testByReferenceParametersAreMutated(): void {
		$d = new hookDispatcher();
		$d->addListener('X', function (string &$html, string $tag) { $html .= "<$tag>"; });
		$html = '';
		$d->dispatch('X', [&$html, 'b']);

		$this->assertSame('<b>', $html);
		$this->assertSame([], $this->warnings);
	}

	/** Dispatch sites pass some slots by value; the variadic by-reference wrapper must not warn on them. */
	public function testRoleProtectedListenerAcceptsByValueParameters(): void {
		$d = new hookDispatcher();
		$d->addRoleProtectedListener('LinksAboveBar', function (array &$links, string $page, bool $flag) {
			$links[] = "$page:" . ($flag ? '1' : '0');
		}, userRole::LEV_MODERATOR, userRole::LEV_ADMIN);
		$links = [];
		$d->dispatch('LinksAboveBar', [&$links, 'modules', true]);

		$this->assertSame(['modules:1'], $links);
		$this->assertSame([], $this->warnings);
	}

	public function testRoleProtectedListenerIsSkippedBelowRole(): void {
		$d = new hookDispatcher();
		$ran = false;
		$d->addRoleProtectedListener('X', function () use (&$ran) { $ran = true; }, userRole::LEV_ADMIN, userRole::LEV_USER);
		$d->dispatch('X');

		$this->assertFalse($ran);
	}

	public function testRoleProtectedListenerCanThrowBelowRole(): void {
		$d = new hookDispatcher();
		$d->addRoleProtectedListener('X', function () {}, userRole::LEV_ADMIN, userRole::LEV_USER, 0, true);

		$this->assertThrows(fn() => $d->dispatch('X'), BoardException::class);
	}
}
