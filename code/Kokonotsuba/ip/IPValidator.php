<?php

namespace Kokonotsuba\ip;

use Kokonotsuba\ip\IPAddress;
use Kokonotsuba\request\request;
use function Kokonotsuba\libraries\_T;
use function Kokonotsuba\libraries\getRemoteAddrOpenShift;

class IPValidator {
	private $config;
	private IPAddress $ip;
	private request $request;

	public function __construct(array $config, IPAddress $ip, request $request) {
		$this->config = $config;
		$this->ip = $ip;
		$this->request = $request;
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

		$ipOpenShift = getRemoteAddrOpenShift($this->request);
		if (!empty($ipOpenShift)) {
			return $ipOpenShift;
		}

		return (string) $this->ip;
	}

	private function getRemoteAddrThroughProxy(): string {
		foreach ($this->config['PROXYHEADERlist'] as $key) {
			$value = $this->request->getServer($key);
			if ($value !== null) {
				foreach (explode(',', $value) as $ip) {
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
					return $this->request->getServer('HTTP_CF_CONNECTING_IP', '');
				}
			}
		} else {
			foreach ($cloudflare_v6 as $cidr) {
				if ($this->matchCIDRv6($addr, $cidr)) {
					return $this->request->getServer('HTTP_CF_CONNECTING_IP', '');
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
