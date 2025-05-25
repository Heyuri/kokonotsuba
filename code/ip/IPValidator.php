<?php

class IPValidator {
	private $config;
	private IPAddress $ip;

	public function __construct(array $config, IPAddress $ip) {
		$this->config = $config;
		$this->ip = $ip;
	}

	public function getValidatedIPAddress(): string {
		$ipCloudFlare = $this->getRemoteAddrCloudFlare();
		if (!empty($ipCloudFlare)) {
			return $ipCloudFlare;
		}
	
		$ipProxy = $this->getRemoteAddrThroughProxy();
		if (!empty($ipProxy)) {
			return $ipProxy;
		}

		$ipOpenShift = getRemoteAddrOpenShift();
		if (!empty($ipOpenShift)) {
			return $ipOpenShift;
		}

		return (string) $this->ip;
	}

	public function isBanned(&$baninfo): bool {
		if (!$this->config['BAN_CHECK']) return false; // Disabled

		$IP = (string) $this->ip;
		$HOST = strtolower(gethostbyaddr($IP));
		$checkTwice = ($IP !== $HOST);
		$IsBanned = false;

		foreach ($this->config['BANPATTERN'] as $pattern) {
			$slash = substr_count($pattern, '/');

			if ($slash == 2) { // RegExp
				$pattern .= 'i';
			} elseif ($slash == 1) { // CIDR Notation
				if ($this->matchCIDR($IP, $pattern)) {
					$IsBanned = true;
					break;
				}
				continue;
			} elseif (strpos($pattern, '*') !== false || strpos($pattern, '?') !== false) { // Wildcard
				$pattern = '/^' . str_replace(['.', '*', '?'], ['\.', '.*', '.?'], $pattern) . '$/i';
			} else { // Full-text
				if ($IP == $pattern || ($checkTwice && $HOST == strtolower($pattern))) {
					$IsBanned = true;
					break;
				}
				continue;
			}

			if (preg_match($pattern, $HOST) || ($checkTwice && preg_match($pattern, $IP))) {
				$IsBanned = true;
				break;
			}
		}

		if ($IsBanned) {
			$baninfo = _T('ip_banned');
			return true;
		}

		return $this->checkDNSBL($IP, $baninfo);
	}

	private function checkDNSBL(string $IP, &$baninfo): bool {
		if (!isset($this->config['DNSBLservers'][0]) || !$this->config['DNSBLservers'][0]) return false;
		if (array_search($IP, $this->config['DNSBLWHlist']) !== false) return false;

		$rev = implode('.', array_reverse(explode('.', $IP)));
		$lastPoint = count($this->config['DNSBLservers']) - 1;
		if ($this->config['DNSBLservers'][0] < $lastPoint) $lastPoint = $this->config['DNSBLservers'][0];

		for ($i = 1; $i <= $lastPoint; $i++) {
			$query = $rev . '.' . $this->config['DNSBLservers'][$i] . '.';
			$result = gethostbyname($query);
			if ($result && ($result != $query)) {
				$baninfo = _T('ip_dnsbl_banned', $this->config['DNSBLservers'][$i]);
				return true;
			}
		}

		return false;
	}

	private function getRemoteAddrThroughProxy(): string {
		foreach ($this->config['PROXYHEADERlist'] as $key) {
			if (isset($_SERVER[$key])) {
				foreach (explode(',', $_SERVER[$key]) as $ip) {
					$ip = trim($ip);
					if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
						return $ip;
					}
				}
			}
		}

		return (string) $this->ip;
	}

	private function getRemoteAddrCloudFlare(): string {
		$addr = (string) $this->ip;
		$cloudflare_v4 = [
			'199.27.128.0/21', '173.245.48.0/20', '103.21.244.0/22',
			'103.22.200.0/22', '103.31.4.0/22', '141.101.64.0/18'
		];
		$cloudflare_v6 = [
			'2400:cb00::/32', '2606:4700::/32', '2803:f800::/32'
		];

		if ($this->ip->isIPv4()) {
			foreach ($cloudflare_v4 as $cidr) {
				if ($this->matchCIDR($addr, $cidr)) {
					return $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '';
				}
			}
		} else {
			foreach ($cloudflare_v6 as $cidr) {
				if ($this->matchCIDRv6($addr, $cidr)) {
					return $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '';
				}
			}
		}
		return '';
	}

	private function matchCIDR(string $ip, string $cidr): bool {
		list($subnet, $bits) = explode('/', $cidr);
		
		$ip_bin = inet_pton($ip);
		$subnet_bin = inet_pton($subnet);
		if (!$ip_bin || !$subnet_bin) return false;

		$mask = str_repeat("\xff", $bits >> 3);
		if ($bits % 8) {
			$mask .= chr(0xff << (8 - ($bits % 8)));
		}
		$mask = str_pad($mask, strlen($ip_bin), "\x00");

		return ($ip_bin & $mask) === ($subnet_bin & $mask);
	}

	private function matchCIDRv6(string $ip, string $cidr): bool {
		list($net, $mask) = explode('/', $cidr);
		$binaryip = $this->inetToBits(inet_pton($ip));
		$binarynet = $this->inetToBits(inet_pton($net));

		return substr($binaryip, 0, $mask) === substr($binarynet, 0, $mask);
	}

	private function inetToBits($inet): string {
		$unpacked = unpack('H*', $inet);
		$hex = reset($unpacked);
		$bin = '';
		foreach (str_split($hex) as $char) {
			$bin .= str_pad(base_convert($char, 16, 2), 4, '0', STR_PAD_LEFT);
		}
		return $bin;
	}
}
