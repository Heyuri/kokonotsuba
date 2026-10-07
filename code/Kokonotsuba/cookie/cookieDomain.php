<?php

namespace Kokonotsuba\cookie;

/** Works out the domain a cookie shared by every board on the site is scoped to. */
final class cookieDomain {
	/**
	 * The shared domain, or '' for a host-only cookie.
	 *
	 * Without an explicit domain it is derived the way static/html/yay.html derives its own:
	 * 'boards.example.net' gives 'example.net', a two-label host is used as it is. A domain the
	 * request is not on would be refused by the browser, so that falls back to host-only.
	 *
	 * @param string $configured  Explicit domain, '' to derive one.
	 * @param string $websiteUrl  WEBSITE_URL, whose host is derived from when it has one.
	 * @param string $requestHost The Host header, port and all.
	 * @return string
	 */
	public static function shared(string $configured, string $websiteUrl, string $requestHost): string {
		$requestHost = self::normalize(preg_replace('/:\d+$/', '', $requestHost));

		if ($requestHost === '' || filter_var(trim($requestHost, '[]'), FILTER_VALIDATE_IP) !== false) {
			return '';
		}

		$domain = self::normalize($configured);

		if ($domain === '') {
			$host = self::normalize((string) parse_url($websiteUrl, PHP_URL_HOST));
			$domain = self::derive($host !== '' ? $host : $requestHost);
		}

		if ($domain === '' || ($requestHost !== $domain && !str_ends_with($requestHost, '.' . $domain))) {
			return '';
		}

		return $domain;
	}

	private static function derive(string $host): string {
		if (filter_var(trim($host, '[]'), FILTER_VALIDATE_IP) !== false) {
			return '';
		}

		$labels = explode('.', $host);

		if (count($labels) < 2) {
			return '';
		}

		return count($labels) > 2 ? implode('.', array_slice($labels, 1)) : $host;
	}

	private static function normalize(string $host): string {
		return strtolower(trim(trim($host), '.'));
	}
}
