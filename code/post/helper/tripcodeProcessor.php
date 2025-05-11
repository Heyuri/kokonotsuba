<?php

class tripcodeProcessor {
	private readonly array $config;
	private readonly globalHTML $globalHTML;

	// Constructor to initialize config and globalHTML instances
	public function __construct(array $config, globalHTML $globalHTML) {
		$this->config = $config;
		$this->globalHTML = $globalHTML;
	}

	// Main method to apply tripcode processing to a given name
	public function apply(string &$name, string &$tripcode, string &$secure_tripcode, string &$capcode, \Kokonotsuba\Root\Constants\userRole $roleLevel): void {
		// Check for fraud symbols in the name and append " (fraudster)" if found
		if ($this->containsFraudSymbol($name)) {
			$name .= " (fraudster)";
			return;
		}

		// Apply role capcode if conditions are met
		$capcode = $this->setCapcodeIfExists($roleLevel, $secure_tripcode);
		// now return
		if($capcode) {
			$tripcode = '';
			$secure_tripcode = '';
			return;
		}

		// Generate tripcode based on extracted trip parts
		$this->generateTripcode($tripcode, $secure_tripcode);

		// Ensure name is not empty or whitespace
		$this->ensureNameSet($name);

	}

	// Check if name contains fraud symbols
	private function containsFraudSymbol(string $name): bool {
		return preg_match('/[◆◇♢♦⟡★]/u', $name);
	}

	// Set the capcode if it exists in the array key
	private function setCapcodeIfExists(\Kokonotsuba\Root\Constants\userRole $roleLevel, string $secure_tripcode): string {
		if(array_key_exists($secure_tripcode, $this->config['staffCapcodes']) && $roleLevel->isAtLeast($this->config['staffCapcodes'][$secure_tripcode]['requiredRole'])) {
			return $secure_tripcode;
		}
		return '';
	}

	// Generate regular and/or secure tripcodes
	private function generateTripcode(string &$tripcode, string &$secure_tripcode): void {
		if ($tripcode) {
			// Convert trip to Shift_JIS and generate a salt for crypt
			$tripcode = mb_convert_encoding($tripcode, 'Shift_JIS', 'UTF-8');
			$salt = strtr(preg_replace('/[^\.-z]/', '.', substr($tripcode . 'H.', 1, 2)), ':;<=>?@[\\]^_`', 'ABCDEFGabcdef');
			// Generate tripcode using crypt
			$tripcode = substr(crypt($tripcode, $salt), -10);
		}

		if ($secure_tripcode) {
			// Generate secure tripcode by hashing and encoding
			$sha = str_rot13(base64_encode(pack("H*", sha1($secure_tripcode . $this->config['TRIPSALT']))));
			$secure_tripcode = substr($sha, 0, 10);
		}

	}

	// Ensure the name is set; use default or trigger error if not
	private function ensureNameSet(string &$name): void {
		if (!$name || preg_match("/^[ |　|]*$/", $name)) {
			if ($this->config['ALLOW_NONAME']) {
				// Assign default name if allowed
				$name = $this->config['DEFAULT_NONAME'];
			} else {
				// Otherwise, trigger an error
				$this->globalHTML->error(_T('regist_withoutname'));
			}
		}
	}

	// Apply capcode overrides if specific tripcode mappings exist
	private function applyCapcodeOverrides(string $name, string $trip): string {
		if (isset($this->config['CAPCODES'][$trip])) {
			$cap = $this->config['CAPCODES'][$trip];
			// Wrap name with capcode style information
			return '<span class="capcodeSection" style="color:' . $cap['color'] . ';">' . $name . '<span class="postercap">' . $cap['cap'] . '</span></span>';
		}
		return $name;
	}
}
