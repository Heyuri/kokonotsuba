<?php

namespace Kokonotsuba\Modules\segregator;

use Kokonotsuba\cookie\cookieService;
use Kokonotsuba\module_classes\abstractModuleMain;
use Kokonotsuba\module_classes\traits\listeners\FileUrlListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\IncludeScriptTrait;
use Kokonotsuba\module_classes\traits\listeners\ModuleHeaderListenerTrait;
use Kokonotsuba\module_classes\traits\listeners\RegistAfterCommitListenerTrait;

use function Kokonotsuba\libraries\getAttachmentBoard;
use function Puchiko\strings\sanitizeStr;

class moduleMain extends abstractModuleMain {
	use FileUrlListenerTrait;
	use RegistAfterCommitListenerTrait;
	use ModuleHeaderListenerTrait;
	use IncludeScriptTrait;

	private readonly string $subDomain;
	private readonly string $cookieName;
	private readonly string $cookieDomain;
	private readonly int    $cookieExpiry;
	private readonly cookieService $cookieService;

	public function getName(): string {
		return 'Segregator';
	}

	public function getVersion(): string {
		return 'Koko 2026';
	}

	/**
	 * Resolves config values and registers all event listeners.
	 *
	 * @return void
	 */
	public function initialize(): void {
		$this->subDomain     = $this->getConfig('ModuleSettings.SEGREGATOR_SUB_DOMAIN', '');
		$this->cookieName    = $this->getConfig('ModuleSettings.SEGREGATOR_COOKIE_NAME', 'viewAllContent');
		$this->cookieDomain  = $this->getConfig('ModuleSettings.SEGREGATOR_COOKIE_DOMAIN', '');
		$this->cookieExpiry  = time() + (10 * 365 * 24 * 60 * 60); // 10 years
		$this->cookieService = $this->moduleContext->cookieService;

		if (!empty($this->subDomain)) {
			$this->listenFileUrl('onFileUrl');
		}

		$this->listenRegistAfterCommit('onRegistAfterCommit');

		// inject config before the script include so the JS can read it
		$this->listenModuleHeader('onModuleHeader');
		$this->registerScript('segregator.js', false);
	}

	/**
	 * Rewrites the file URL so its host becomes `SEGREGATOR_SUB_DOMAIN.originalHost`,
	 * leaving the scheme, port, and path (board id + /src/…) untouched.
	 * The per-attachment board URL is used as the source of truth for the original host.
	 * Both the board URL and the file URL are normalised to absolute form before
	 * comparison, so relative WEBSITE_URL values (e.g. '/boards/') are handled correctly.
	 *
	 * @param string $url        The resolved file URL, passed by reference.
	 * @param array  $attachment The attachment data array for the file being rendered.
	 * @param bool   $isThumb    Whether the URL is for a thumbnail rather than the full file.
	 * @return void
	 */
	public function onFileUrl(string &$url, array $attachment, bool $isThumb): void {
		if (empty($url)) return;

		$boardUrl   = $this->toAbsoluteUrl(getAttachmentBoard($attachment)->getBoardUploadedFilesURL() ?? '');
		$parsedBase = parse_url($boardUrl);
		if (!is_array($parsedBase)) return;

		$scheme = $parsedBase['scheme'] ?? '';
		$host   = $parsedBase['host']   ?? '';
		if (empty($scheme) || empty($host)) return;

		$port           = isset($parsedBase['port']) ? ':' . (string) $parsedBase['port'] : '';
		$originalOrigin = $scheme . '://' . $host . $port;
		$absoluteUrl    = $this->toAbsoluteUrl($url);

		if (str_starts_with($absoluteUrl, $originalOrigin)) {
			$parts      = explode('.', $host);
			$baseDomain = count($parts) > 2 ? implode('.', array_slice($parts, 1)) : $host;
			$newOrigin  = $scheme . '://' . $this->subDomain . '.' . $baseDomain . $port;
			$url        = $newOrigin . substr($absoluteUrl, strlen($originalOrigin));
		}
	}

	/**
	 * Ensures a URL is absolute. If it is already absolute it is returned unchanged.
	 * Protocol-relative URLs (//host/…) are given the current request scheme.
	 * Relative paths (/path or path) are prefixed with the current request origin.
	 *
	 * @param string $url The URL to resolve.
	 * @return string The absolute URL.
	 */
	private function toAbsoluteUrl(string $url): string {
		if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
			return $url;
		}

		$request = $this->moduleContext->request;
		$scheme  = $request->isHttps() ? 'https' : 'http';

		if (str_starts_with($url, '//')) {
			return $scheme . ':' . $url;
		}

		$host = $request->getHttpHost();
		$path = str_starts_with($url, '/') ? $url : '/' . $url;

		return $scheme . '://' . $host . $path;
	}

	/**
	 * Sets the long-lived access cookie after a post is committed.
	 * Only fires if the cookie is not already present and headers haven't been sent.
	 *
	 * @param int    $postNo    The post number assigned to the new post.
	 * @param string $threadUid The UID of the thread the post was added to.
	 * @param string $name      Poster name.
	 * @param string $email     Poster email.
	 * @param string $sub       Post subject.
	 * @param string $comment   Post comment body.
	 * @return void
	 */
	public function onRegistAfterCommit(int $postNo, string $threadUid, string $name, string $email, string $sub, string $comment): void {
		if (headers_sent()) return;
		if ($this->cookieService->has($this->cookieName)) return;

		$this->cookieService->set(
			$this->cookieName,
			'1',
			$this->cookieExpiry,
			'/',
			$this->cookieDomain,
			false,
			false
		);
	}

	/**
	 * Injects a <meta> tag carrying cookie config as data-* attributes so segregator.js
	 * can read them without an inline script.
	 *
	 * @param string $moduleHeader The accumulated module header HTML, passed by reference.
	 * @return void
	 */
	public function onModuleHeader(string &$moduleHeader): void {
		$cookieMaxAge = 10 * 365 * 24 * 60 * 60;

		$moduleHeader .= '<meta name="segregatorConfig"'
			. ' data-cookie-name="' . sanitizeStr($this->cookieName) . '"'
			. ' data-cookie-domain="' . sanitizeStr($this->cookieDomain) . '"'
			. ' data-cookie-max-age="' . $cookieMaxAge . '">' . "\n";
	}
}
