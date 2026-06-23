<?php

namespace Koko\Tests\Framework;

/**
 * Discovers and runs TestCase subclasses, then prints a coloured report.
 *
 * Discovery: every `*Test.php` file under the given directories is required,
 * then every declared class that extends TestCase is run. Each public method
 * named `test*` becomes one test; the class is re-instantiated per method so
 * tests stay isolated.
 */
class TestRunner {

	/** @var string[] Directories to scan for *Test.php files. */
	private array $directories;

	/** Substring filter; only test "Class::method" names containing it run. */
	private string $filter;

	private bool $colour;

	/** @var array<int,array{name:string,status:string,message:string,time:float}> */
	private array $results = [];

	public function __construct(array $directories, string $filter = '', ?bool $colour = null) {
		$this->directories = $directories;
		$this->filter = $filter;
		$this->colour = $colour ?? (function_exists('posix_isatty') ? @posix_isatty(STDOUT) : false);
	}

	/** Run everything. Returns a process exit code (0 = all passed). */
	public function run(): int {
		$classes = $this->discover();

		if (!$classes) {
			fwrite(STDERR, "No test classes found.\n");
			return 1;
		}

		$start = microtime(true);
		foreach ($classes as $class) {
			$this->runClass($class);
		}
		$elapsed = microtime(true) - $start;

		return $this->report($elapsed);
	}

	/** @return string[] Fully-qualified TestCase subclass names. */
	private function discover(): array {
		$before = get_declared_classes();

		foreach ($this->directories as $dir) {
			if (!is_dir($dir)) {
				continue;
			}
			$it = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
			);
			foreach ($it as $file) {
				if ($file->isFile() && str_ends_with($file->getFilename(), 'Test.php')) {
					require_once $file->getPathname();
				}
			}
		}

		$new = array_diff(get_declared_classes(), $before);
		$tests = [];
		foreach ($new as $class) {
			if (is_subclass_of($class, TestCase::class)) {
				$tests[] = $class;
			}
		}
		sort($tests);
		return $tests;
	}

	private function runClass(string $class): void {
		$methods = array_filter(
			get_class_methods($class),
			fn($m) => str_starts_with($m, 'test')
		);
		sort($methods);

		foreach ($methods as $method) {
			$label = $this->shortName($class) . '::' . $method;
			if ($this->filter !== '' && !str_contains($label, $this->filter)) {
				continue;
			}
			$this->runMethod($class, $method, $label);
		}
	}

	private function runMethod(string $class, string $method, string $label): void {
		$t0 = microtime(true);
		$status = 'pass';
		$message = '';

		try {
			/** @var TestCase $instance */
			$instance = new $class();
			$reflection = new \ReflectionMethod($instance, 'setUp');
			$reflection->setAccessible(true);
			$reflection->invoke($instance);

			try {
				$instance->$method();
			} finally {
				$td = new \ReflectionMethod($instance, 'tearDown');
				$td->setAccessible(true);
				$td->invoke($instance);
			}
		} catch (AssertionFailedException $e) {
			$status = 'fail';
			$message = $e->getMessage();
		} catch (\Throwable $e) {
			$status = 'error';
			$message = get_class($e) . ': ' . $e->getMessage()
				. "\n      at " . $this->relPath($e->getFile()) . ':' . $e->getLine();
		}

		$this->results[] = [
			'name'    => $label,
			'status'  => $status,
			'message' => $message,
			'time'    => microtime(true) - $t0,
		];

		$this->printTick($status);
	}

	private function printTick(string $status): void {
		$mark = match ($status) {
			'pass'  => $this->paint('.', 'green'),
			'fail'  => $this->paint('F', 'red'),
			'error' => $this->paint('E', 'yellow'),
			default => '?',
		};
		echo $mark;
	}

	private function report(float $elapsed): int {
		$pass = $fail = $error = 0;
		foreach ($this->results as $r) {
			match ($r['status']) {
				'pass'  => $pass++,
				'fail'  => $fail++,
				'error' => $error++,
				default => null,
			};
		}

		echo "\n\n";

		// Detail every non-pass.
		foreach ($this->results as $i => $r) {
			if ($r['status'] === 'pass') {
				continue;
			}
			$head = $r['status'] === 'fail'
				? $this->paint('FAIL', 'red')
				: $this->paint('ERROR', 'yellow');
			echo sprintf("%s  %s\n      %s\n\n", $head, $r['name'], $r['message']);
		}

		$total = count($this->results);
		$summary = sprintf(
			"Ran %d test%s in %.3fs  —  %d passed, %d failed, %d errored",
			$total, $total === 1 ? '' : 's', $elapsed, $pass, $fail, $error
		);

		$ok = ($fail === 0 && $error === 0);
		echo $this->paint($summary, $ok ? 'green' : 'red'), "\n";

		return $ok ? 0 : 1;
	}

	private function shortName(string $class): string {
		$pos = strrpos($class, '\\');
		return $pos === false ? $class : substr($class, $pos + 1);
	}

	private function relPath(string $path): string {
		$root = dirname(__DIR__, 2);
		return str_starts_with($path, $root) ? ltrim(substr($path, strlen($root)), '/') : $path;
	}

	private function paint(string $text, string $colour): string {
		if (!$this->colour) {
			return $text;
		}
		$codes = ['red' => 31, 'green' => 32, 'yellow' => 33, 'cyan' => 36];
		$code = $codes[$colour] ?? 0;
		return "\033[{$code}m{$text}\033[0m";
	}
}
