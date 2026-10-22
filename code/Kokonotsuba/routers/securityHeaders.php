<?php

namespace Kokonotsuba\routers;

/**
 * Security headers for every koko.php response.
 *
 * Staff sessions also get a script-focused CSP: inline scripts are still allowed (the pages carry
 * some), but foreign script hosts, plugins, <base> hijacks and off-site form posts are blocked.
 * Images, media and frames are left open for embeds, ads and the upload CDN.
 */
final class securityHeaders {
	/**
	 * @param bool        $isStaff    Whether the viewer has a staff session.
	 * @param string      $staticUrl  STATIC_URL, where scripts are served from.
	 * @param string      $websiteUrl WEBSITE_URL, where every board's koko.php lives.
	 * @param string|null $cdnUrl     CDN_URL when USE_CDN is on.
	 */
	public static function send(bool $isStaff, string $staticUrl, string $websiteUrl, ?string $cdnUrl = null): void {
		if (headers_sent()) {
			return;
		}

		header('X-Content-Type-Options: nosniff');
		header('Referrer-Policy: strict-origin-when-cross-origin');

		if ($isStaff) {
			header('Content-Security-Policy: ' . self::staffPolicy($staticUrl, $websiteUrl, $cdnUrl));
		}
	}

	/** The staff CSP, with the configured origins added to 'self'. */
	public static function staffPolicy(string $staticUrl, string $websiteUrl, ?string $cdnUrl = null): string {
		$scriptSources = self::sources(["'self'"], [$staticUrl, $cdnUrl ?? '']);
		$formTargets = self::sources(["'self'"], [$websiteUrl]);

		return "script-src $scriptSources 'unsafe-inline' 'wasm-unsafe-eval'; "
			. "object-src 'none'; base-uri 'self'; form-action $formTargets";
	}

	/** Keywords plus the distinct origins of any absolute URLs; relative ones are already 'self'. */
	private static function sources(array $keywords, array $urls): string {
		$sources = $keywords;

		foreach ($urls as $url) {
			$origin = self::originOf($url);
			if ($origin !== null && !in_array($origin, $sources, true)) {
				$sources[] = $origin;
			}
		}

		return implode(' ', $sources);
	}

	/**
	 * scheme://host[:port] of an absolute URL, or just host[:port] for a protocol-relative one
	 * (which CSP matches against the page's own scheme). Null for a relative or malformed URL.
	 */
	private static function originOf(string $url): ?string {
		$parts = parse_url($url);
		if (!is_array($parts) || empty($parts['host'])) {
			return null;
		}

		$hostPort = strtolower($parts['host']) . (isset($parts['port']) ? ':' . $parts['port'] : '');

		if (str_starts_with($url, '//')) {
			return $hostPort;
		}

		$scheme = strtolower($parts['scheme'] ?? '');
		if ($scheme !== 'http' && $scheme !== 'https') {
			return null;
		}

		return $scheme . '://' . $hostPort;
	}
}
