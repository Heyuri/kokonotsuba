<?php

namespace Koko\Tests\Framework;

/**
 * A tiny property-based fuzzer for the pure helper functions.
 *
 * You register "targets": a function under test, a generator that produces a
 * random argument list, and zero or more invariants that must hold for every
 * (input, output) pair. The fuzzer then hammers each target with many random
 * inputs and reports two kinds of failure:
 *
 *   - CRASH:     the function threw / emitted a warning (warnings are promoted
 *                to exceptions by the test bootstrap).
 *   - INVARIANT: the function returned, but a stated property was violated.
 *
 * Every run is seeded (printed at the top) so any failure is reproducible with
 * `--seed=`. Failing inputs are var_export'd so they can be pasted straight
 * into a regression test.
 */
class Fuzzer {

	/** @var array<int,array{name:string,fn:callable,gen:callable,invariants:array<int,array{0:string,1:callable}>}> */
	private array $targets = [];

	private int $iterations;
	private int $seed;
	private bool $colour;
	private string $filter;

	/** @var array<int,array{target:string,kind:string,iteration:int,input:mixed,message:string}> */
	private array $failures = [];

	public function __construct(int $iterations, int $seed, string $filter = '', ?bool $colour = null) {
		$this->iterations = max(1, $iterations);
		$this->seed = $seed;
		$this->filter = $filter;
		$this->colour = $colour ?? (function_exists('posix_isatty') ? @posix_isatty(STDOUT) : false);
	}

	/**
	 * Register a fuzz target.
	 *
	 * @param string   $name       Human label for reporting.
	 * @param callable $fn         The function under test.
	 * @param callable $gen        () => array  — produces the argument list for one call.
	 * @param array    $invariants List of [description, fn($result, $args): bool].
	 *                             An invariant returning false (or throwing) is a failure.
	 */
	public function target(string $name, callable $fn, callable $gen, array $invariants = []): void {
		$this->targets[] = [
			'name'       => $name,
			'fn'         => $fn,
			'gen'        => $gen,
			'invariants' => $invariants,
		];
	}

	/** Run all registered targets. Returns a process exit code (0 = clean). */
	public function run(): int {
		mt_srand($this->seed);

		echo $this->paint("Fuzzing with seed {$this->seed}, {$this->iterations} iterations/target.", 'cyan'), "\n";
		echo "Reproduce this run with: ", $this->paint("--seed={$this->seed}", 'cyan'), "\n\n";

		$start = microtime(true);
		$totalRuns = 0;

		foreach ($this->targets as $target) {
			if ($this->filter !== '' && !str_contains($target['name'], $this->filter)) {
				continue;
			}
			$totalRuns += $this->runTarget($target);
		}

		$elapsed = microtime(true) - $start;
		return $this->report($totalRuns, $elapsed);
	}

	/** @return int Number of iterations actually executed for this target. */
	private function runTarget(array $target): int {
		$name = $target['name'];
		echo str_pad($name, 34, '.'), ' ';

		$failedHere = 0;
		for ($i = 0; $i < $this->iterations; $i++) {
			$args = ($target['gen'])();
			if (!is_array($args)) {
				$args = [$args];
			}

			try {
				$result = ($target['fn'])(...$args);
			} catch (\Throwable $e) {
				$this->record($name, 'CRASH', $i, $args, get_class($e) . ': ' . $e->getMessage());
				$failedHere++;
				continue;
			}

			foreach ($target['invariants'] as [$desc, $check]) {
				try {
					$ok = (bool)$check($result, $args);
				} catch (\Throwable $e) {
					$this->record($name, 'INVARIANT', $i, $args, "$desc — check threw " . get_class($e) . ': ' . $e->getMessage());
					$failedHere++;
					continue 2;
				}
				if (!$ok) {
					$this->record($name, 'INVARIANT', $i, $args, $desc);
					$failedHere++;
					continue 2;
				}
			}
		}

		echo $failedHere === 0
			? $this->paint("ok", 'green')
			: $this->paint("$failedHere failing input" . ($failedHere === 1 ? '' : 's'), 'red');
		echo "\n";

		return $this->iterations;
	}

	private function record(string $target, string $kind, int $iteration, $input, string $message): void {
		// Cap stored failures per target so a systematically broken target
		// doesn't bury the report.
		$forThisTarget = 0;
		foreach ($this->failures as $f) {
			if ($f['target'] === $target) {
				$forThisTarget++;
			}
		}
		if ($forThisTarget >= 5) {
			return;
		}
		$this->failures[] = compact('target', 'kind', 'iteration', 'input', 'message');
	}

	private function report(int $totalRuns, float $elapsed): int {
		echo "\n";
		if (!$this->failures) {
			echo $this->paint(sprintf('All clear — %d executions in %.3fs, no failures.', $totalRuns, $elapsed), 'green'), "\n";
			return 0;
		}

		echo $this->paint(count($this->failures) . " failing case(s) (showing up to 5 per target):", 'red'), "\n\n";
		foreach ($this->failures as $f) {
			echo $this->paint("[{$f['kind']}] {$f['target']} (iteration {$f['iteration']})", 'yellow'), "\n";
			echo "  reason: {$f['message']}\n";
			echo "  input:  " . $this->compactExport($f['input']) . "\n\n";
		}

		echo $this->paint(sprintf('Fuzzing found problems — %d executions in %.3fs.', $totalRuns, $elapsed), 'red'), "\n";
		return 1;
	}

	/** var_export, but single-line and length-capped for readability. */
	private function compactExport($value): string {
		$out = var_export($value, true);
		$out = preg_replace('/\s+/', ' ', $out);
		return mb_strlen($out) > 300 ? mb_substr($out, 0, 297) . '...' : $out;
	}

	private function paint(string $text, string $colour): string {
		if (!$this->colour) {
			return $text;
		}
		$codes = ['red' => 31, 'green' => 32, 'yellow' => 33, 'cyan' => 36];
		return "\033[" . ($codes[$colour] ?? 0) . "m{$text}\033[0m";
	}

	// ---- Input generators ---------------------------------------------------
	// Static helpers usable from target generators. All draw from mt_rand(),
	// which the run seeds, so generated sequences are reproducible.

	public static function int(int $min, int $max): int {
		return mt_rand($min, $max);
	}

	/** Pick a random element from a list. */
	public static function pick(array $choices) {
		return $choices[mt_rand(0, count($choices) - 1)];
	}

	public static function bool(): bool {
		return mt_rand(0, 1) === 1;
	}

	/**
	 * A deliberately hostile string: mixes ASCII, multibyte UTF-8, emoji,
	 * control characters, zero-width/invisible marks, HTML and the empty string.
	 */
	public static function nastyString(int $maxLen = 40): string {
		$pools = [
			// plain ASCII
			'abcdefghijklmnopqrstuvwxyz ABCDEFGHIJKLMNOPQRSTUVWXYZ 0123456789 .,!?-_/',
			// punctuation / shell-ish
			'<>&"\'`{}[]()$#%^*|\\;:@~+=',
			// multibyte: CJK, accents, RTL, cyrillic
			'日本語のテキスト ファイル 你好世界 café résumé Ñoño Привет مرحبا',
			// emoji & astral plane
			'😀🎌🔥👍🏽🇯🇵🧑‍💻🏴‍☠️',
			// invisible / control / format characters
			"\u{200B}\u{200E}\u{FEFF}\u{00AD}\u{202E}\t\r\n\0",
		];

		$len = mt_rand(0, $maxLen);
		$out = '';
		for ($i = 0; $i < $len; $i++) {
			$pool = $pools[mt_rand(0, count($pools) - 1)];
			$chars = preg_split('//u', $pool, -1, PREG_SPLIT_NO_EMPTY);
			$out .= $chars[mt_rand(0, count($chars) - 1)];
		}
		return $out;
	}

	/** A random lowercase hex string of the given length (for hash helpers). */
	public static function hex(int $length = 16): string {
		$digits = '0123456789abcdef';
		$out = '';
		for ($i = 0; $i < $length; $i++) {
			$out .= $digits[mt_rand(0, 15)];
		}
		return $out;
	}

	/** A random-ish URL, sometimes malformed, for link/query helpers. */
	public static function url(): string {
		$schemes = ['http://', 'https://', 'ftp://', '//', '', 'javascript:'];
		$hosts   = ['example.com', 'a.b.c.test', 'xn--n3h.example', 'localhost:8080', '192.168.0.1', ''];
		$paths   = ['', '/', '/a/b', '/p?x=1&y=2', '/%20%zz', '/#frag', '/a b c'];
		return self::pick($schemes) . self::pick($hosts) . self::pick($paths);
	}

	/** A small random associative array of string=>scalar, for query builders. */
	public static function assoc(int $maxKeys = 4): array {
		$out = [];
		$n = mt_rand(0, $maxKeys);
		for ($i = 0; $i < $n; $i++) {
			$key = self::pick(['a', 'mode', 'page', 'q', 'search[]', 'x.y', '']);
			$out[$key] = self::bool() ? self::nastyString(12) : mt_rand(-5, 100);
		}
		return $out;
	}
}
