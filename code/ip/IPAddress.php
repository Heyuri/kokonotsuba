<?php

class IPAddress {
	private string $ipAddress;

	public function __construct(string $ipAddress = '') {
		$this->ipAddress = $ipAddress ?: ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
	}

	public function __toString(): string {
		return $this->ipAddress;
	}

	public function isIPv6(): bool {
		return filter_var($this->ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
	}

	public function isIPv4(): bool {
		return filter_var($this->ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
	}
}