<?php

namespace Kokonotsuba\debug;

use Kokonotsuba\request\request;

/**
 * Samples the whole request with Excimer and hands the reader a speedscope JSON file of it.
 *
 * Profiling has to start before the bootstrap to be worth anything, long before any module can
 * say whether the reader may have it. So the front controller starts the sampler for any
 * request carrying the parameter and buffers the page; the debug bar module then calls
 * authorize() once it has checked its config and the reader's role. At shutdown an authorized
 * request gets the profile as a download in place of the page, and any other request gets the
 * page it would have had anyway. Sampling one request costs next to nothing, which is what
 * makes starting it on trust acceptable.
 *
 * Needs the excimer extension (1.1 or newer for getSpeedscopeData()); without it nothing here
 * does anything and the debug bar says so.
 */
final class requestProfiler {
	public const PARAMETER = 'kokoProfile';

	/** Sampling period in seconds. */
	private const PERIOD = 0.001;

	private static ?object $profiler = null;
	private static bool $authorized = false;
	private static int $bufferLevel = 0;

	public static function isAvailable(): bool {
		return class_exists('ExcimerProfiler') && method_exists('ExcimerLog', 'getSpeedscopeData');
	}

	/** Begin sampling when the request asks for a profile and the extension is present. */
	public static function startIfRequested(request $request): void {
		if (self::$profiler !== null || !self::isAvailable() || !$request->hasParameter(self::PARAMETER, 'GET')) {
			return;
		}

		$profiler = new \ExcimerProfiler();
		$profiler->setPeriod(self::PERIOD);
		if (defined('EXCIMER_REAL')) {
			$profiler->setEventType(\EXCIMER_REAL);
		}
		$profiler->start();
		self::$profiler = $profiler;

		// the profile lists the request's statements alongside the samples
		requestMetrics::keepStatements(true);

		ob_start();
		self::$bufferLevel = ob_get_level();
		register_shutdown_function([self::class, 'finish']);
	}

	public static function isRunning(): bool {
		return self::$profiler !== null;
	}

	/** Let this request's profile be handed out. Called by the debug bar once it has checked the reader. */
	public static function authorize(): void {
		self::$authorized = true;
	}

	/** The page's own URL with the profile parameter added, or replaced if already present. */
	public static function profileUrlFor(string $requestUri): string {
		$parts = explode('?', $requestUri, 2);
		$query = [];
		if (isset($parts[1])) {
			parse_str($parts[1], $query);
		}
		$query[self::PARAMETER] = '1';

		return $parts[0] . '?' . http_build_query($query);
	}

	/** Shutdown: swap the buffered page for the profile when allowed, otherwise release the page. */
	public static function finish(): void {
		if (self::$profiler === null) {
			return;
		}

		$profiler = self::$profiler;
		self::$profiler = null;
		$profiler->stop();

		$redirected = in_array(http_response_code(), [301, 302, 303, 307, 308], true);
		if (!self::$authorized || $redirected || headers_sent()) {
			self::releaseBuffers(false);
			return;
		}

		$json = json_encode(self::buildDocument(
			$profiler->getLog()->getSpeedscopeData(),
			requestMetrics::snapshot(),
			requestMetrics::statements(),
			(string)($_SERVER['REQUEST_URI'] ?? '')
		));
		if ($json === false) {
			self::releaseBuffers(false);
			return;
		}

		self::releaseBuffers(true);
		http_response_code(200);
		header_remove('Content-Encoding');
		header('Content-Type: application/json');
		header('Content-Disposition: attachment; filename="koko-profile-' . date('Y-m-d_His') . '.json"');
		header('Cache-Control: no-store');
		echo $json;
	}

	/**
	 * The file handed out: the speedscope profile with the request's timings and statements
	 * under a "koko" key, which speedscope ignores and a person reading the file does not.
	 *
	 * @param array $speedscope As ExcimerLog::getSpeedscopeData() returns it.
	 * @param array $snapshot   As requestMetrics::snapshot() returns it.
	 * @param list<array{sql: string, seconds: float}> $statements
	 */
	public static function buildDocument(array $speedscope, array $snapshot, array $statements, string $requestUri): array {
		$speedscope['name'] = $requestUri === '' ? 'kokonotsuba' : $requestUri;
		$speedscope['koko'] = [
			'requestUri' => $requestUri,
			'generatedAt' => date('c'),
			'wallMs' => round((float)($snapshot['wallSeconds'] ?? 0) * 1000, 2),
			'cpuMs' => round((float)($snapshot['cpuSeconds'] ?? 0) * 1000, 2),
			'peakMemoryBytes' => (int)($snapshot['peakMemoryBytes'] ?? 0),
			'queries' => [
				'count' => (int)($snapshot['queryCount'] ?? 0),
				'ms' => round((float)($snapshot['querySeconds'] ?? 0) * 1000, 2),
				'statements' => array_map(static fn(array $statement): array => [
					'sql' => (string)($statement['sql'] ?? ''),
					'ms' => round((float)($statement['seconds'] ?? 0) * 1000, 3),
				], array_values($statements)),
			],
		];

		return $speedscope;
	}

	/** Close every buffer opened since ours, then ours: flushed to the client, or discarded. */
	private static function releaseBuffers(bool $discard): void {
		while (ob_get_level() > self::$bufferLevel) {
			ob_end_flush();
		}
		if (ob_get_level() === self::$bufferLevel && self::$bufferLevel > 0) {
			$discard ? ob_end_clean() : ob_end_flush();
		}
		self::$bufferLevel = 0;
	}
}
