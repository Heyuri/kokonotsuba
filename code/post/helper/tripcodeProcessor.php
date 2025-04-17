<?php

class tripcodeProcessor {
	private readonly array $config;
	private readonly globalHTML $globalHTML;
	private readonly staffAccountFromSession $staffSession;

	public function __construct(array $config, globalHTML $globalHTML, staffAccountFromSession $staffSession) {
		$this->config = $config;
		$this->globalHTML = $globalHTML;
		$this->staffSession = $staffSession;
	}

	public function apply(string &$name, string &$email, string &$dest): void {
		if (preg_match('/[◆◇♢♦⟡★]/u', $name)) {
			$name .= " (fraudster)";
		}

		$name = str_replace('&#', '&&', $name);
		list($name, $trip, $sectrip) = str_replace('&%', '&#', explode('#', $name . '##'));
		$name = str_replace('&&', '&#', $name);

		$roleLevel = $this->staffSession->getRoleLevel();

		if ($roleLevel >= $this->config['roles']['LEV_JANITOR'] && $sectrip) {
			$roleMap = [
				'LEV_JANITOR'		=> 'JCAPCODE_FMT',
				'LEV_MODERATOR'	=> 'MCAPCODE_FMT',
				'LEV_ADMIN'			=> 'ACAPCODE_FMT',
			];

			foreach ($roleMap as $key => $format) {
				if ($roleLevel >= $this->config['roles'][$key] && $sectrip === ' ' . $this->globalHTML->roleNumberToRoleName($this->config['roles'][$key])) {
					if (!empty($this->config[$format])) {
						$name = sprintf($this->config[$format], $name);
						$name = "<span class=\"postername\">$name</span>";
						return;
					}
				}
			}
		}

		if ($trip) {
			$trip = mb_convert_encoding($trip, 'Shift_JIS', 'UTF-8');
			$salt = strtr(preg_replace('/[^\.-z]/', '.', substr($trip . 'H.', 1, 2)), ':;<=>?@[\\]^_`', 'ABCDEFGabcdef');
			$tripcodeCrypt = substr(crypt($trip, $salt), -10);
			$trip = "◆$tripcodeCrypt";
		}

		if ($sectrip) {
			$sha = str_rot13(base64_encode(pack("H*", sha1($sectrip . $this->config['TRIPSALT']))));
			$trip = "★" . substr($sha, 0, 10);
		}

		if (!$name || preg_match("/^[ |　|]*$/", $name)) {
			if ($this->config['ALLOW_NONAME']) {
				$name = $this->config['DEFAULT_NONAME'];
			} else {
				$this->globalHTML->error(_T('regist_withoutname'), $dest);
			}
		}

		$name = "<span class=\"postername\">$name</span><span class=\"postertrip\">$trip</span>";

		if (isset($this->config['CAPCODES'][$trip])) {
			$cap = $this->config['CAPCODES'][$trip];
			$name = '<span class="capcodeSection" style="color:' . $cap['color'] . ';">' . $name . '<span class="postercap">' . $cap['cap'] . '</span></span>';
		}

		if (stristr($email, 'vipcode') && defined('VIPDEF')) {
			$name .= ' <img src="' . $this->config['STATIC_URL'] . 'vip.gif" title="This user is a VIP user" style="vertical-align: middle;margin-top: -2px;" alt="VIP">';
		}

		$email = preg_replace('/^vipcode$/i', '', $email);
	}
}
