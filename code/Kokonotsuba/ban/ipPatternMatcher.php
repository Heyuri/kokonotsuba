<?php

namespace Kokonotsuba\ban;

/**
 * Wildcard matching for ban patterns, e.g. '127.0.*' or '2001:db8:*'.
 *
 * A pattern with no '*' is an exact match and never reaches here — the repository looks those up
 * by index. Only the handful of range bans get matched in PHP.
 *
 * One rule decides how wide a pattern reaches, and it is deliberately the narrow reading: a '*'
 * at the end stands for the rest of the address, and a '*' anywhere else stands for exactly one
 * octet or hextet, with everything after it still having to line up. A pattern is a moderator's
 * shorthand for a range, never licence to widen past what they wrote — '*:db8::1' is a ban on
 * one address whoever the first hextet belongs to, not a ban on every address there is.
 */
class ipPatternMatcher {
	public static function isWildcard(string $pattern): bool {
		return str_contains($pattern, '*');
	}

	public static function matches(string $ip, string $pattern): bool {
		if (!filter_var($ip, FILTER_VALIDATE_IP)) {
			return false;
		}

		if ($ip === $pattern) {
			return true;
		}

		if (str_contains($ip, ':') && str_contains($pattern, ':')) {
			return self::matchesIpv6($ip, $pattern);
		}

		if (str_contains($ip, '.') && str_contains($pattern, '.')) {
			return preg_match(self::ipv4Regex($pattern), $ip) === 1;
		}

		return false;
	}

	/**
	 * Build the pattern's regex under the narrow reading of '*'.
	 *
	 * Only a trailing '*' may swallow dots; one in the middle is a single octet, so '1.2.*.4'
	 * cannot quietly stretch across the rest of the address.
	 */
	private static function ipv4Regex(string $pattern): string {
		$segments = explode('.', $pattern);
		$last = count($segments) - 1;
		$parts = [];

		foreach ($segments as $i => $segment) {
			$rest = $i === $last;
			$parts[] = str_replace('\*', $rest ? '[0-9.]+' : '[0-9]+', preg_quote($segment, '/'));
		}

		return '/^' . implode('\.', $parts) . '$/';
	}

	/** Compare hextet by hextet, both sides expanded. */
	private static function matchesIpv6(string $ip, string $pattern): bool {
		$ipParts = self::expandHextets(explode(':', (string) inet_ntop(inet_pton($ip))));
		$patternParts = self::expandHextets(explode(':', $pattern));

		$count = min(count($patternParts), count($ipParts));

		$last = count($patternParts) - 1;

		for ($i = 0; $i < $count; $i++) {
			if ($patternParts[$i] === '*') {
				// Trailing: the rest of the address, whatever it is. Anywhere else: this hextet
				// only, and the ones after it are still compared.
				if ($i === $last) {
					return true;
				}

				continue;
			}

			if (strcasecmp($patternParts[$i], $ipParts[$i]) !== 0) {
				return false;
			}
		}

		if (end($patternParts) === '*') {
			return true;
		}

		return count($patternParts) === count($ipParts);
	}

	/**
	 * Blow a '::' run out into the zero hextets it stands for, so both sides line up positionally.
	 *
	 * @param list<string> $parts
	 * @return list<string>
	 */
	private static function expandHextets(array $parts): array {
		if (count($parts) >= 8 || !in_array('', $parts, true)) {
			return array_values(array_filter($parts, fn(string $part): bool => $part !== ''));
		}

		$missing = 8 - count($parts) + 1;
		$expanded = [];
		$expandedOnce = false;

		foreach ($parts as $part) {
			if ($part === '' && !$expandedOnce) {
				$expanded = array_merge($expanded, array_fill(0, $missing, '0'));
				$expandedOnce = true;
				continue;
			}

			if ($part !== '') {
				$expanded[] = $part;
			}
		}

		return $expanded;
	}
}
