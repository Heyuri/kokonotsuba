<?php

namespace Kokonotsuba\cookie;

/** Manages reading and writing app-level cookies via a request-scoped snapshot. */
class cookieService {
	private array $cookies;

	/**
	 * @param array $cookies Initial cookie snapshot, typically from $_COOKIE.
	 */
	public function __construct(array $cookies = []) {
		$this->cookies = $cookies;
	}

	/**
	 * Check whether a cookie with the given name exists.
	 *
	 * @param string $name Cookie name.
	 * @return bool
	 */
	public function has(string $name): bool {
		return array_key_exists($name, $this->cookies);
	}

	/**
	 * Get the value of a cookie, with an optional default.
	 *
	 * @param string $name    Cookie name.
	 * @param mixed  $default Value to return when the cookie is not set.
	 * @return mixed Cookie value or the default.
	 */
	public function get(string $name, mixed $default = null): mixed {
		return $this->cookies[$name] ?? $default;
	}

	/**
	 * Set a cookie and update the in-memory snapshot.
	 *
	 * @param string $name     Cookie name.
	 * @param string $value    Cookie value.
	 * @param int    $expires  Unix timestamp of expiry (0 = session cookie).
	 * @param string $path     Cookie path scope.
	 * @param string $domain   Cookie domain scope.
	 * @param bool   $secure   Transmit over HTTPS only.
	 * @param bool   $httpOnly Inaccessible to JavaScript.
	 * @return void
	 */
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

	/**
	 * Set a cookie without URL-encoding the value, and update the in-memory snapshot.
	 *
	 * @param string $name     Cookie name.
	 * @param string $value    Raw (unencoded) cookie value.
	 * @param int    $expires  Unix timestamp of expiry (0 = session cookie).
	 * @param string $path     Cookie path scope.
	 * @param string $domain   Cookie domain scope.
	 * @param bool   $secure   Transmit over HTTPS only.
	 * @param bool   $httpOnly Inaccessible to JavaScript.
	 * @return void
	 */
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

	/**
	 * Expire and remove a cookie from both the browser and the in-memory snapshot.
	 *
	 * @param string $name     Cookie name.
	 * @param string $path     Cookie path scope.
	 * @param string $domain   Cookie domain scope.
	 * @param bool   $secure   Must match the scope used when the cookie was set.
	 * @param bool   $httpOnly Must match the scope used when the cookie was set.
	 * @return void
	 */
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