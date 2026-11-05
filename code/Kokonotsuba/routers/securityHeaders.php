<?php

namespace Kokonotsuba\routers;

/**
 * Security headers for every koko.php response.
 *
 * Staff sessions also get a script-focused CSP: inline scripts are still allowed (the pages carry
 * some), but scripts from outside the site's domain, plugins, <base> hijacks
 * and off-site form posts are blocked.
 * Images, media and frames are left open for embeds, ads and the upload CDN.
 */
final class securityHeaders {
	/**
	 * @param bool        $isStaff    Whether the viewer has a staff session.
	 * @param string      $staticUrl  STATIC_URL, where scripts are served from.
	 * @param string      $websiteUrl WEBSITE_URL, where every board's koko.php lives.
	 * @param string|null $cdnUrl     CDN_URL when USE_CDN is on.
	 * @param string      $pageHost   The request's Host header; its domain and subdomains may serve scripts.
	 */
	public static function send(bool $isStaff, string $staticUrl, string $websiteUrl, ?string $cdnUrl = null, string $pageHost = ''): void {
		if (headers_sent()) {
			return;
		}

		header('X-Content-Type-Options: nosniff');
		header('Referrer-Policy: strict-origin-when-cross-origin');

		if ($isStaff) {
			header('Content-Security-Policy: ' . self::staffPolicy($staticUrl, $websiteUrl, $cdnUrl, $pageHost));
		}
	}

	/** The staff CSP, with the configured origins added to 'self'. */
	public static function staffPolicy(string $staticUrl, string $websiteUrl, ?string $cdnUrl = null, string $pageHost = ''): string {
		// accepted URLs that scripts can be loaded from
		$scriptSources = self::sources(["'self'"], [$staticUrl, $cdnUrl ?? '']);
		
		// add the page's base domain and its subdomains to the accepted script sources (skipping any already listed)
		foreach (self::domainSources($pageHost) as $source) {
			if (!in_array($source, explode(' ', $scriptSources), true)) {
				$scriptSources .= ' ' . $source;
			}
		}
		
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
	 * The base domain of a host (its last two labels) and every subdomain of it, scheme-less so they
	 * follow the page's scheme. Empty for an IP, a single-label host or anything malformed.
	 * Two labels is wrong for suffixes like co.uk, where it would cover the whole suffix.
	 */
	private static function domainSources(string $host): array {
		$host = strtolower(preg_replace('/:\d+$/', '', trim($host)));
		if ($host === '' || filter_var($host, FILTER_VALIDATE_IP) || !preg_match('/^[a-z0-9-]+(\.[a-z0-9-]+)+$/', $host)) {
			return [];
		}

		$baseDomain = implode('.', array_slice(explode('.', $host), -2));
		return [$baseDomain, '*.' . $baseDomain];
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
