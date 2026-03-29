<?php

namespace Kokonotsuba\cookie;

class cookieService {
	private array $cookies;

	public function __construct(array $cookies = []) {
		$this->cookies = $cookies;
	}

	public function has(string $name): bool {
		return array_key_exists($name, $this->cookies);
	}

	public function get(string $name, mixed $default = null): mixed {
		return $this->cookies[$name] ?? $default;
	}

	public function set(
		string $name,
		string $value,
		int $expires = 0,
		string $path = '',
		string $domain = '',
		bool $secure = false,
		bool $httpOnly = false
	): void {
		setcookie($name, $value, $expires, $path, $domain, $secure, $httpOnly);
		$this->cookies[$name] = $value;
		$_COOKIE[$name] = $value;
	}

	public function setRaw(
		string $name,
		string $value,
		int $expires = 0,
		string $path = '',
		string $domain = '',
		bool $secure = false,
		bool $httpOnly = false
	): void {
		setrawcookie($name, $value, $expires, $path, $domain, $secure, $httpOnly);
		$this->cookies[$name] = $value;
		$_COOKIE[$name] = $value;
	}

	public function delete(
		string $name,
		string $path = '',
		string $domain = '',
		bool $secure = false,
		bool $httpOnly = false
	): void {
		setcookie($name, '', time() - 3600, $path, $domain, $secure, $httpOnly);
		unset($this->cookies[$name], $_COOKIE[$name]);
	}
}