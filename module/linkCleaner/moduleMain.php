<?php

namespace Kokonotsuba\Modules\linkCleaner;

use Kokonotsuba\module_classes\abstractModuleMain;
use Kokonotsuba\module_classes\traits\listeners\PostCommentListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\RegistBeforeCommitListenerTrait;
use Kokonotsuba\post\Post;

class moduleMain extends abstractModuleMain {
	use RegistBeforeCommitListenerTrait;
	use PostCommentListenerTrait;

	/** Matches a url anywhere in the comment - inside an attribute or bare. */
	private const URL_PATTERN = '~https?://[^\s"\'<>]+~i';

	/**
	 * Splits a query string into parameters
	 */
	private const QUERY_SEPARATOR_PATTERN = '~&amp;|&|\?(?=[^&=?#]+=)~i';

	/** Parameters stripped on every host. '*' works as a wildcard. */
	private const STRIP_PARAMS = [
		// google analytics campaign tags
		'utm_*',
		// facebook / instagram
		'fbclid', 'igshid', 'igsh',
		// ad click ids
		'gclid', 'gbraid', 'wbraid', 'dclid', 'msclkid', 'yclid',
		// twitter / tiktok
		'twclid', 'ttclid', '__twitter_impression',
		// mailchimp, yandex, reddit and friends
		'mc_cid', 'mc_eid', '_openstat', 'ref_src', 'ref_url',
		// generic "share id" style tracking (youtube, spotify, ...)
		'si', 'spm', 'share_id',
	];

	/**
	 * Per-host whitelists. On these hosts (and their subdomains) ONLY the
	 * listed parameters survive. Cheaper to maintain than chasing every new
	 * name youtube invents (si, pp, feature, ab_channel, source_ve_path, ...).
	 */
	private const KEEP_ONLY = [
		'youtube.com'          => ['v', 't', 'list', 'index', 'start', 'end'],
		'youtube-nocookie.com' => ['v', 't', 'list', 'index', 'start', 'end'],
		'youtu.be'             => ['t', 'list', 'index', 'start', 'end'],
	];

	/** The referrer-hiding prefix, so jump links don't get mangled. */
	private string $refUrl;

	public function getName(): string {
		return 'K! Link Cleaner';
	}

	public function getVersion(): string {
		return 'Koko BBS Release 1';
	}

	public function initialize(): void {
		// core config, not something this module adds
		$this->refUrl = (string)$this->getConfig('REF_URL', '');

		// High priority: clean the urls before word filters, bbcode or anything
		// else at this hook point starts wrapping them in extra markup.
		$this->listenRegistBeforeCommit('onBeforeCommit', 100);

		// Optional second pass at render time for posts made before this module
		// was enabled. Purely cosmetic - it does not rewrite the database.
		if ($this->getConfig('ModuleSettings.LINK_CLEANER_CLEAN_ON_RENDER', false)) {
			$this->listenPostComment('onRenderComment', 100);
		}
	}

	public function onBeforeCommit($name, &$email, &$emailForInsertion, &$sub, &$com): void {
		$com = $this->cleanLinksInText($com);
	}

	private function onRenderComment(string &$postComment, ?Post $post = null, bool $isThreadView = false): void {
		$postComment = $this->cleanLinksInText($postComment);
	}

	private function cleanLinksInText(string $text): string {
		// most comments have no links at all, so skip the regex entirely
		if (!str_contains($text, '://')) {
			return $text;
		}

		$cleanedText = preg_replace_callback(self::URL_PATTERN, function (array $match): string {
			return $this->cleanUrl($match[0]);
		}, $text);

		// preg_replace_callback returns null if the engine bails out (backtrack
		// limit, bad utf-8). Keep the original comment rather than blanking it.
		return $cleanedText ?? $text;
	}

	private function cleanUrl(string $url): string {
		// A REF_URL jump prefix is itself a url, so the pattern above matches
		// prefix + target as one blob. Peel the prefix off and clean the target.
		$prefix = '';
		if ($this->refUrl !== '' && stripos($url, $this->refUrl) === 0) {
			$prefix = substr($url, 0, strlen($this->refUrl));
			$url = substr($url, strlen($this->refUrl));

			// remainder isn't a plain url (encoded, or the prefix was the whole link)
			if (!preg_match('~^https?://~i', $url)) {
				return $prefix . $url;
			}
		}

		// don't swallow sentence punctuation sitting after a bare url.
		// it gets re-appended verbatim, so nothing is lost either way.
		$trailingPunctuation = '';
		if (preg_match('~[.,!?)\]]+$~', $url, $punctuationMatch)) {
			$trailingPunctuation = $punctuationMatch[0];
			$url = substr($url, 0, -strlen($trailingPunctuation));
		}

		// split the fragment off before touching the query string
		$fragment = '';
		$hashPosition = strpos($url, '#');
		if ($hashPosition !== false) {
			$fragment = substr($url, $hashPosition);
			$url = substr($url, 0, $hashPosition);
		}

		$queryPosition = strpos($url, '?');
		if ($queryPosition === false) {
			return $prefix . $url . $fragment . $trailingPunctuation;
		}

		$baseUrl = substr($url, 0, $queryPosition);
		$query = substr($url, $queryPosition + 1);

		$cleanedQuery = $this->filterQuery($baseUrl, $query);

		// drop the '?' as well if every parameter was junk
		$rebuiltUrl = $baseUrl . ($cleanedQuery !== '' ? '?' . $cleanedQuery : '');

		return $prefix . $rebuiltUrl . $fragment . $trailingPunctuation;
	}

	private function filterQuery(string $baseUrl, string $query): string {
		if ($query === '') {
			return '';
		}

		// the comment is usually already escaped, so preserve whichever
		// separator style this url was written with
		$separator = stripos($query, '&amp;') !== false ? '&amp;' : '&';

		// this also breaks apart "?v=XXXXXXXXXXX?si=123" style urls, and
		// rebuilding with $separator turns any surviving glued parameter
		// into a valid one (eg. "?v=a?t=42" comes back out as "?v=a&t=42")
		$parameters = preg_split(self::QUERY_SEPARATOR_PATTERN, $query);

		$whitelist = $this->findWhitelist($this->extractHost($baseUrl));

		$keptParameters = [];
		foreach ($parameters as $parameter) {
			if ($parameter === '') {
				continue;
			}

			// the key is everything before the first '=' (or the whole flag)
			$key = strtolower(substr($parameter, 0, strcspn($parameter, '=')));

			// on a whitelisted host, anything not named is dropped
			if ($whitelist !== null) {
				if (in_array($key, $whitelist, true)) {
					$keptParameters[] = $parameter;
				}
				continue;
			}

			// everywhere else, drop only the known-bad names
			if (!$this->matchesPattern($key)) {
				$keptParameters[] = $parameter;
			}
		}

		return implode($separator, $keptParameters);
	}

	private function extractHost(string $url): string {
		if (!preg_match('~^https?://([^/?#]+)~i', $url, $authorityMatch)) {
			return '';
		}

		$authority = $authorityMatch[1];

		// strip any user:pass@ portion
		$atPosition = strrpos($authority, '@');
		if ($atPosition !== false) {
			$authority = substr($authority, $atPosition + 1);
		}

		// strip the port, while leaving bracketed ipv6 literals intact
		if (preg_match('~^(\[[^\]]*\]|[^:]*)~', $authority, $hostMatch)) {
			$authority = $hostMatch[1];
		}

		return strtolower($authority);
	}

	private function findWhitelist(string $host): ?array {
		if ($host === '') {
			return null;
		}

		foreach (self::KEEP_ONLY as $ruleHost => $allowedParameters) {
			// match the host itself and any subdomain of it
			if ($host === $ruleHost || str_ends_with($host, '.' . $ruleHost)) {
				return $allowedParameters;
			}
		}

		return null;
	}

	private function matchesPattern(string $key): bool {
		foreach (self::STRIP_PARAMS as $pattern) {
			// plain name, no wildcard
			if (!str_contains($pattern, '*')) {
				if ($key === $pattern) {
					return true;
				}
				continue;
			}

			// glob style, eg. 'utm_*'
			$patternRegex = '~^' . str_replace('\*', '.*', preg_quote($pattern, '~')) . '$~';
			if (preg_match($patternRegex, $key)) {
				return true;
			}
		}

		return false;
	}
}