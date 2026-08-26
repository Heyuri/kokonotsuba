<?php

namespace Kokonotsuba\install;

/**
 * Fetches the backend's own sensitive files over HTTP to see whether the web server hands them
 * out.
 *
 * The fetcher is injectable; the default one uses curl, falling back to the stream wrapper.
 * A fetch returns ['status' => int, 'body' => string], or null when the request could not be
 * made at all (which is reported as "could not check", never as a pass).
 */
final class exposureProbe {
	public const GROUP = 'Web exposure';

	/** @var callable(string): (array{status: int, body: string}|null) */
	private $fetcher;

	public function __construct(
		private readonly string $baseUrl,
		?callable $fetcher = null
	) {
		$this->fetcher = $fetcher ?? static fn (string $url): ?array => self::fetch($url);
	}

	/** @return list<checkResult> */
	public function check(): array {
		$results = [];

		foreach (webServerRules::probeTargets() as $target) {
			$results[] = $this->checkOne($target['path'], $target['marker']);
		}

		return $results;
	}

	public function checkOne(string $relativePath, string $marker): checkResult {
		$url = rtrim($this->baseUrl, '/').'/'.ltrim($relativePath, '/');
		$response = ($this->fetcher)($url);

		if ($response === null) {
			return checkResult::warn(
				self::GROUP,
				$relativePath,
				'Could not be requested, so exposure is unverified. Check it by hand: '.$url
			);
		}

		$status = $response['status'];
		$body = $response['body'];

		if ($status === 403 || $status === 401 || $status === 404) {
			return checkResult::ok(self::GROUP, $relativePath, "Denied by the web server (HTTP {$status}).");
		}

		if ($status >= 300) {
			return checkResult::warn(
				self::GROUP,
				$relativePath,
				"HTTP {$status} — not obviously denied. Confirm it is unreachable: ".$url
			);
		}

		// Served with content that only exists in the source file: the file itself is being handed out.
		if ($marker !== '' && str_contains($body, $marker)) {
			return checkResult::fail(
				self::GROUP,
				$relativePath,
				'Served as plain text — its contents are public. Deny it in the web server before installing.'
			);
		}

		if (str_contains($body, '<?php')) {
			return checkResult::fail(
				self::GROUP,
				$relativePath,
				'PHP source is being served instead of executed. Deny it, and check that PHP is wired up.'
			);
		}

		if (trim($body) !== '') {
			return checkResult::fail(
				self::GROUP,
				$relativePath,
				'Reachable and returns content (HTTP 200). Deny it in the web server.'
			);
		}

		// PHP ran the file and it printed nothing. Nothing leaked, but it should still be denied.
		return checkResult::warn(
			self::GROUP,
			$relativePath,
			'Reachable (HTTP 200) but returned nothing — PHP executed it. Deny it anyway.'
		);
	}

	/**
	 * Best-effort HTTP GET of our own URL.
	 *
	 * Certificate verification is off on purpose: this asks a single question about our own
	 * server, and a self-signed or staging certificate should not turn into "unverified".
	 *
	 * @return array{status: int, body: string}|null
	 */
	private static function fetch(string $url): ?array {
		if (function_exists('curl_init')) {
			$handle = curl_init($url);
			if ($handle === false) {
				return null;
			}

			curl_setopt_array($handle, [
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_TIMEOUT => 5,
				CURLOPT_CONNECTTIMEOUT => 3,
				CURLOPT_FOLLOWLOCATION => false,
				CURLOPT_SSL_VERIFYPEER => false,
				CURLOPT_SSL_VERIFYHOST => 0,
				CURLOPT_USERAGENT => 'kokonotsuba-installer',
			]);

			$body = curl_exec($handle);
			$status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
			curl_close($handle);

			if ($body === false || $status === 0) {
				return null;
			}

			return ['status' => $status, 'body' => (string)$body];
		}

		if (!filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOL)) {
			return null;
		}

		$context = stream_context_create([
			'http' => ['timeout' => 5, 'ignore_errors' => true, 'follow_location' => 0],
			'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
		]);

		$body = @file_get_contents($url, false, $context);
		if ($body === false) {
			return null;
		}

		$status = 0;
		foreach ($http_response_header ?? [] as $header) {
			if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $matches)) {
				$status = (int)$matches[1];
			}
		}

		return $status === 0 ? null : ['status' => $status, 'body' => $body];
	}
}
