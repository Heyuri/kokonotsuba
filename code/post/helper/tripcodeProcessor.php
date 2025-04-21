<?php

class tripcodeProcessor {
	private readonly array $config;
	private readonly globalHTML $globalHTML;

	public function __construct(array $config, globalHTML $globalHTML) {
		$this->config = $config;
		$this->globalHTML = $globalHTML;
	}

	public function apply(string &$name, int $roleLevel = 0): void {
		if ($this->containsFraudSymbol($name)) {
			$name .= " (fraudster)";
		}

		$name = $this->sanitizeNameBeforeTripParsing($name);
		[$name, $trip, $sectrip] = $this->extractTripParts($name);
		$name = $this->sanitizeNameAfterTripParsing($name);

		if ($this->shouldApplyRoleCapcode($roleLevel, $sectrip)) {
			if ($this->applyRoleCapcode($name, $roleLevel, $sectrip)) {
				$name = "<span class=\"postername\">$name</span>";
				return;
			}
		}

		$trip = $this->generateTripcode($trip, $sectrip);

		$this->ensureNameSet($name);
		$name = "<span class=\"postername\">$name</span><span class=\"postertrip\">$trip</span>";

		$name = $this->applyCapcodeOverrides($name, $trip);

	}

	private function containsFraudSymbol(string $name): bool {
		return preg_match('/[◆◇♢♦⟡★]/u', $name);
	}

	private function sanitizeNameBeforeTripParsing(string $name): string {
		return str_replace('&#', '&&', $name);
	}

	private function extractTripParts(string $name): array {
		return str_replace('&%', '&#', explode('#', $name . '##'));
	}

	private function sanitizeNameAfterTripParsing(string $name): string {
		return str_replace('&&', '&#', $name);
	}

	private function shouldApplyRoleCapcode(int $roleLevel, ?string $sectrip): bool {
		return $roleLevel >= ($this->config['roles']['LEV_JANITOR'] ?? 0) && !empty($sectrip);
	}

	private function applyRoleCapcode(string &$name, int $roleLevel, string $sectrip): bool {
		$roleMap = [
			'LEV_JANITOR'		=> 'JCAPCODE_FMT',
			'LEV_MODERATOR'	=> 'MCAPCODE_FMT',
			'LEV_ADMIN'			=> 'ACAPCODE_FMT',
			'LEV_SYSTEM'		=> 'SCAPCODE_FMT',
		];

		foreach ($roleMap as $key => $format) {
			if (
				$roleLevel >= $this->config['roles'][$key] &&
				$sectrip === ' ' . $this->globalHTML->roleNumberToRoleName($this->config['roles'][$key]) &&
				!empty($this->config[$format])
			) {
				$name = sprintf($this->config[$format], $name);
				return true;
			}
		}

		return false;
	}

	private function generateTripcode(?string $trip, ?string $sectrip): string {
		if ($trip) {
			$trip = mb_convert_encoding($trip, 'Shift_JIS', 'UTF-8');
			$salt = strtr(preg_replace('/[^\.-z]/', '.', substr($trip . 'H.', 1, 2)), ':;<=>?@[\\]^_`', 'ABCDEFGabcdef');
			$trip = "◆" . substr(crypt($trip, $salt), -10);
		}

		if ($sectrip) {
			$sha = str_rot13(base64_encode(pack("H*", sha1($sectrip . $this->config['TRIPSALT']))));
			$trip = "★" . substr($sha, 0, 10);
		}

		return $trip;
	}

	private function ensureNameSet(string &$name): void {
		if (!$name || preg_match("/^[ |　|]*$/", $name)) {
			if ($this->config['ALLOW_NONAME']) {
				$name = $this->config['DEFAULT_NONAME'];
			} else {
				$this->globalHTML->error(_T('regist_withoutname'));
			}
		}
	}

	private function applyCapcodeOverrides(string $name, string $trip): string {
		if (isset($this->config['CAPCODES'][$trip])) {
			$cap = $this->config['CAPCODES'][$trip];
			return '<span class="capcodeSection" style="color:' . $cap['color'] . ';">' . $name . '<span class="postercap">' . $cap['cap'] . '</span></span>';
		}
		return $name;
	}

}
