<?php

namespace Kokonotsuba\request;

class request {
	private array $get;
	private array $post;
	private array $server;
	private array $files;

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

	public static function fromGlobals(): self {
		return new self($_GET, $_POST, $_SERVER, $_FILES);
	}

	/**
	 * Get a parameter from the specified source (GET, POST) or both.
	 *
	 * @param string $name Parameter name
	 * @param string|null $source 'GET', 'POST', or null for REQUEST-like behavior (POST takes priority)
	 * @param mixed $default Default value if parameter is not found
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
	 */
	public function getServer(string $name, mixed $default = null): mixed {
		return $this->server[$name] ?? $default;
	}

	/**
	 * Check whether a $_SERVER key exists.
	 */
	public function hasServer(string $name): bool {
		return isset($this->server[$name]);
	}

	/**
	 * Get file upload data by input name.
	 */
	public function getFile(string $name): ?array {
		return $this->files[$name] ?? null;
	}

	public function hasFile(string $name): bool {
		return isset($this->files[$name]);
	}

	// ─── Convenience: request method ───

	public function getMethod(): string {
		return $this->server['REQUEST_METHOD'] ?? '';
	}

	public function isPost(): bool {
		return $this->getMethod() === 'POST';
	}

	public function isGet(): bool {
		return $this->getMethod() === 'GET';
	}

	// ─── Convenience: commonly used server values ───

	public function getReferer(string $default = ''): string {
		return $this->server['HTTP_REFERER'] ?? $default;
	}

	public function getUserAgent(string $default = ''): string {
		return $this->server['HTTP_USER_AGENT'] ?? $default;
	}

	public function getRemoteAddr(string $default = '127.0.0.1'): string {
		return $this->server['REMOTE_ADDR'] ?? $default;
	}

	public function getHttpHost(string $default = ''): string {
		return $this->server['HTTP_HOST'] ?? $default;
	}

	public function getRequestTime(): int {
		return (int) ($this->server['REQUEST_TIME'] ?? time());
	}

	public function getRequestTimeFloat(): float {
		return (float) ($this->server['REQUEST_TIME_FLOAT'] ?? microtime(true));
	}

	public function isHttps(): bool {
		return !empty($this->server['HTTPS']) && $this->server['HTTPS'] !== 'off';
	}

	public function isAjax(): bool {
		return isset($this->server['HTTP_X_REQUESTED_WITH'])
			&& strtolower($this->server['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
	}

	public function getScriptName(): string {
		return $this->server['SCRIPT_NAME'] ?? '';
	}

	public function getServerName(): string {
		return $this->server['SERVER_NAME'] ?? '';
	}

	public function getAcceptEncoding(): string {
		return $this->server['HTTP_ACCEPT_ENCODING'] ?? '';
	}

	// ─── Bulk access ───

	public function allGet(): array {
		return $this->get;
	}

	public function allPost(): array {
		return $this->post;
	}

	public function allFiles(): array {
		return $this->files;
	}

	public function allServer(): array {
		return $this->server;
	}
}
