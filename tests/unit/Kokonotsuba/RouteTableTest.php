<?php

namespace Koko\Tests\Unit\Kokonotsuba;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\containers\appContainer;
use Kokonotsuba\routers\csrfRule;
use Kokonotsuba\routers\frontPageRouteTable;
use Kokonotsuba\routers\route;
use Kokonotsuba\routers\routeTable;

/** The mode table: which modes exist, what modeHandler checks around them, and that every row builds. */
class RouteTableTest extends TestCase {

	public function testModes(): void {
		$modes = array_keys(routeTable::routes());
		sort($modes);

		$this->assertSame([
			'', 'account', 'actionLog', 'admin', 'api', 'boards', 'globalConfig', 'handleAccountAction',
			'handleBoardRequests', 'managePosts', 'module', 'moduleloaded', 'overboard', 'rebuild',
			'regist', 'status', 'usrdel', 'viewStaffAccount',
		], $modes);
	}

	public function testCsrfRules(): void {
		$rules = [];
		foreach (routeTable::routes() as $mode => $entry) {
			if ($entry->csrf !== csrfRule::NONE) {
				$rules[$mode] = $entry->csrf->name;
			}
		}
		ksort($rules);

		$this->assertSame([
			'globalConfig' => 'ON_POST',
			'handleAccountAction' => 'ON_POST',
			'handleBoardRequests' => 'POST_ONLY',
			'managePosts' => 'ON_POST',
			'rebuild' => 'POST_ONLY',
		], $rules);
	}

	public function testProfiledModes(): void {
		$routes = routeTable::routes();

		$this->assertSame('posting', $routes['regist']->profile);
		$this->assertSame('rebuild', $routes['rebuild']->profile);
		$this->assertNull($routes['']->profile);
	}

	/**
	 * Each factory runs against a container holding, under every id it asks for, a bare instance of the
	 * type the route's constructor takes at that position. A wrong count fails the build, and a swapped
	 * pair shows up as one id wanted as two types across the table.
	 */
	public function testEveryFactoryBuildsARoute(): void {
		$this->assertEveryFactoryBuildsARoute(routeTable::routes());
	}

	public function testFrontPageModes(): void {
		$routes = frontPageRouteTable::routes();
		$this->assertSame(['', 'admin', 'logo', 'editNews', 'editPage'], array_keys($routes));
		$this->assertSame(csrfRule::ON_POST, $routes['editNews']->csrf);
		$this->assertSame(csrfRule::ON_POST, $routes['editPage']->csrf);
		$this->assertSame(csrfRule::POST_ONLY, $routes['logo']->csrf);
		$this->assertEveryFactoryBuildsARoute($routes);
	}

	/** @param array<string, \Kokonotsuba\routers\routeEntry> $routes */
	private function assertEveryFactoryBuildsARoute(array $routes): void {
		$wiring = [];
		$typeOfId = [];
		foreach ($routes as $mode => $entry) {
			$class = $this->builtClass($entry->factory);

			// api passes its endpoints in an array, so its ids do not line up with constructor positions
			if ($mode === 'api') {
				$this->assertTrue(is_subclass_of($class, route::class), "api: $class is not a route");
				continue;
			}

			$ids = $this->idsAskedFor($entry->factory);
			$params = (new \ReflectionClass($class))->getConstructor()->getParameters();
			$this->assertCount(count($params), $ids, "mode '$mode' passes the wrong number of arguments to $class");

			foreach ($ids as $i => $id) {
				$type = (string) $params[$i]->getType();
				$typeOfId[$id] ??= $type;
				$this->assertSame($typeOfId[$id], $type, "mode '$mode' passes '$id' where $class takes \${$params[$i]->getName()}");
			}
			$wiring[$mode] = [$entry->factory, $ids, $params];
		}

		foreach ($wiring as $mode => [$factory, $ids, $params]) {
			$container = new appContainer();
			foreach ($ids as $i => $id) {
				$container->set($id, $this->stubFor($params[$i]));
			}

			$this->assertTrue($factory($container) instanceof route, "mode '$mode' did not build a route");
		}
	}

	public function testEveryRouteClassImplementsTheInterface(): void {
		foreach (glob(dirname(__DIR__, 3) . '/code/Kokonotsuba/routers/routes/*.php') as $file) {
			$class = 'Kokonotsuba\\routers\\routes\\' . basename($file, '.php');
			$this->assertTrue(is_subclass_of($class, route::class), "$class does not implement route");
		}
	}

	/** Container ids the factory reads, in call order. */
	private function idsAskedFor(\Closure $factory): array {
		$recorder = new class extends appContainer {
			public array $ids = [];

			public function get(string $id): mixed {
				$this->ids[] = $id;
				return null;
			}
		};

		try {
			$factory($recorder);
		} catch (\TypeError) {
			// expected: every argument was null
		}
		return $recorder->ids;
	}

	/** The class the factory's arrow function constructs, resolved against the routes namespace. */
	private function builtClass(\Closure $factory): string {
		$fn = new \ReflectionFunction($factory);
		$lines = file($fn->getFileName());
		$source = implode('', array_slice($lines, $fn->getStartLine() - 1, $fn->getEndLine() - $fn->getStartLine() + 1));

		preg_match('/fn\(appContainer \$c\) => new (\w+)\(/', $source, $match);
		return 'Kokonotsuba\\routers\\routes\\' . $match[1];
	}

	private function stubFor(\ReflectionParameter $param): mixed {
		$type = $param->getType();
		if ($type !== null && $type->allowsNull()) {
			return null;
		}
		$name = $type instanceof \ReflectionNamedType ? $type->getName() : 'mixed';

		return match ($name) {
			'array' => [],
			'int' => 0,
			'string' => '',
			'bool' => false,
			'mixed' => null,
			default => (new \ReflectionClass($name))->newInstanceWithoutConstructor(),
		};
	}
}
