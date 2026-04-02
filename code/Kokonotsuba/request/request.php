<?php

namespace Kokonotsuba\request;

use Kokonotsuba\ip\IPAddress;

class request {
	private array $get;
	private array $post;
	private array $server;
	private array $files;

	/**
	 * Build a request wrapper from explicit GET/POST/SERVER/FILES arrays.
	 *
	 * @param array<string, mixed> $get GET values.
	 * @param array<string, mixed> $post POST values.
	 * @param array<string, mixed> $server SERVER values.
	 * @param array<string, mixed> $files FILES values.
	 * @return void
	 */
	public function __construct(
		array $get = [],
		array $post = [],
		array $server = [],
		array $files = []
	) {
		$this->get = $get;
		$this->post = $post;
		$this->server = $server;
		$this->files = $files;
	}

	/**
	 * Build a request wrapper from PHP superglobals.
	 *
	 * @return self New request instance.
	 */
	public static function fromGlobals(): self {
		return new self($_GET, $_POST, $_SERVER, $_FILES);
	}

	/**
	 * Get a parameter from the specified source (GET, POST) or both.
	 *
	 * @param string $name Parameter name.
	 * @param string|null $source 'GET', 'POST', or null for GET-then-POST lookup.
	 * @param mixed $default Default value if not found.
	 * @return mixed Resolved parameter value or default.
	 */
	public function getParameter(string $name, ?string $source = null, mixed $default = null): mixed {
		return match (strtoupper($source ?? '')) {
			'GET' => array_key_exists($name, $this->get) ? $this->get[$name] : $default,
			'POST' => array_key_exists($name, $this->post) ? $this->post[$name] : $default,
			default => array_key_exists($name, $this->get)
				? $this->get[$name]
				: (array_key_exists($name, $this->post) ? $this->post[$name] : $default),
		};
	}

	/**
	 * Check whether a parameter exists in the specified source.
	 *
	 * @param string $name Parameter name.
	 * @param string|null $source 'GET', 'POST', or null for either source.
	 * @return bool True when parameter exists.
	 */
	public function hasParameter(string $name, ?string $source = null): bool {
		return match (strtoupper($source ?? '')) {
			'GET' => array_key_exists($name, $this->get),
			'POST' => array_key_exists($name, $this->post),
			default => array_key_exists($name, $this->get) || array_key_exists($name, $this->post),
		};
	}

	/**
	 * Get a $_SERVER value.
	 *
	 * @param string $name Server key.
	 * @param mixed $default Default value when key is missing.
	 * @return mixed Server value or default.
	 */
	public function getServer(string $name, mixed $default = null): mixed {
		return $this->server[$name] ?? $default;
	}

	/**
	 * Check whether a $_SERVER key exists.
	 *
	 * @param string $name Server key.
	 * @return bool True when key exists.
	 */
	public function hasServer(string $name): bool {
		return isset($this->server[$name]);
	}

	/**
	 * Get file upload data by input name.
	 *
	 * @param string $name File input name.
	 * @return array<string, mixed>|null Upload metadata array or null.
	 */
	public function getFile(string $name): ?array {
		return $this->files[$name] ?? null;
	}

	/**
	 * Check whether a file upload entry exists by input name.
	 *
	 * @param string $name File input name.
	 * @return bool True when upload entry exists.
	 */
	public function hasFile(string $name): bool {
		return isset($this->files[$name]);
	}

	// ─── Convenience: request method ───

	/**
	 * Get the HTTP request method.
	 *
	 * @return string Request method (for example GET/POST).
	 */
	public function getMethod(): string {
		return $this->server['REQUEST_METHOD'] ?? '';
	}

	/**
	 * Check whether the current request method is POST.
	 *
	 * @return bool True for POST requests.
	 */
	public function isPost(): bool {
		return $this->getMethod() === 'POST';
	}

	/**
	 * Check whether the current request method is GET.
	 *
	 * @return bool True for GET requests.
	 */
	public function isGet(): bool {
		return $this->getMethod() === 'GET';
	}

	// ─── Convenience: commonly used server values ───

	/**
	 * Get the HTTP referrer value.
	 *
	 * @param string $default Fallback value when header is missing.
	 * @return string Referrer URL or fallback.
	 */
	public function getReferer(string $default = ''): string {
		return $this->server['HTTP_REFERER'] ?? $default;
	}

	/**
	 * Get the user-agent string.
	 *
	 * @param string $default Fallback value when header is missing.
	 * @return string User-agent string or fallback.
	 */
	public function getUserAgent(string $default = ''): string {
		return $this->server['HTTP_USER_AGENT'] ?? $default;
	}

	/**
	 * Get the request remote IP address.
	 *
	 * @param string $default Fallback IP when key is missing.
	 * @return string Client IP address or fallback.
	 */
	public function getRemoteAddr(string $default = '127.0.0.1'): string {
		return $this->server['REMOTE_ADDR'] ?? $default;
	}

	/**
	 * Get the user's IP address as an IPAddress object.
	 *
	 * @return IPAddress User's IP address.
	 */
	public function userIp(): IPAddress {
		return new IPAddress($this->getRemoteAddr());
	}

	/**
	 * Get the HTTP host header, including port when present.
	 *
	 * @param string $default Fallback host when header is missing.
	 * @return string Host header value or fallback.
	 */
	public function getHttpHost(string $default = ''): string {
		return $this->server['HTTP_HOST'] ?? $default;
	}

	/**
	 * Get the request timestamp as an integer.
	 *
	 * @return int Unix timestamp.
	 */
	public function getRequestTime(): int {
		return (int) ($this->server['REQUEST_TIME'] ?? time());
	}

	/**
	 * Get the request timestamp with microsecond precision.
	 *
	 * @return float Unix timestamp with microseconds.
	 */
	public function getRequestTimeFloat(): float {
		return (float) ($this->server['REQUEST_TIME_FLOAT'] ?? microtime(true));
	}

	/**
	 * Check whether the request is served over HTTPS.
	 *
	 * @return bool True when HTTPS is enabled.
	 */
	public function isHttps(): bool {
		return !empty($this->server['HTTPS']) && $this->server['HTTPS'] !== 'off';
	}

	/**
	 * Check whether the request is an XMLHttpRequest/AJAX request.
	 *
	 * @return bool True when X-Requested-With indicates XMLHttpRequest.
	 */
	public function isAjax(): bool {
		return isset($this->server['HTTP_X_REQUESTED_WITH'])
			&& strtolower($this->server['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
	}

	/**
	 * Get the current script path from server data.
	 *
	 * @return string Script path (for example /index.php).
	 */
	public function getScriptName(): string {
		return $this->server['SCRIPT_NAME'] ?? '';
	}

	/**
	 * Get the server hostname.
	 *
	 * @return string Server name.
	 */
	public function getServerName(): string {
		return $this->server['SERVER_NAME'] ?? '';
	}

	/**
	 * Get the raw Accept-Encoding header value.
	 *
	 * @return string Accept-Encoding header.
	 */
	public function getAcceptEncoding(): string {
		return $this->server['HTTP_ACCEPT_ENCODING'] ?? '';
	}

	/**
	 * Build the current absolute URL without query parameters.
	 *
	 * @return string Absolute URL without query string.
	 */
	public function getCurrentUrlNoQuery(): string {
		$scheme = $this->isHttps() ? 'https' : 'http';
		$host = $this->getHttpHost();
		$path = $this->getServer('SCRIPT_NAME', '');

		return $scheme . '://' . $host . $path;
	}

	/**
	 * Return all GET parameters.
	 *
	 * @return array<string, mixed> GET parameter map.
	 */
	public function allGet(): array {
		return $this->get;
	}

	/**
	 * Return all POST parameters.
	 *
	 * @return array<string, mixed> POST parameter map.
	 */
	public function allPost(): array {
		return $this->post;
	}

	/**
	 * Return all uploaded file metadata.
	 *
	 * @return array<string, mixed> FILES metadata map.
	 */
	public function allFiles(): array {
		return $this->files;
	}

	/**
	 * Return all server/environment values.
	 *
	 * @return array<string, mixed> SERVER data map.
	 */
	public function allServer(): array {
		return $this->server;
	}
}
