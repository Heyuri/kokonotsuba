<?php

namespace Kokonotsuba\Modules\tripcode;

class tripcodeRenderer {
	public function __construct(
		private array $userCapcodes,
		private array $staffCapcodes
	) {}

	public function renderTripcode(
		string $nameHtml, 
		string $tripcode, 
		string $secure_tripcode, 
		string $capcode
	): string {
		// generate the tripcode html
		$tripcodeHtml = $this->generateTripcode($tripcode, $secure_tripcode);

		// generate staff and user capcode html
		$capcodeHtml = $this->generateCapcodeHtml($tripcode, $secure_tripcode, $capcode);

		// build the name + trip html
		$nameHtml = $nameHtml . $tripcodeHtml;
		
		// then wrap it in the capcode html if its noe empty
		if(!empty($capcodeHtml)) {
			$nameHtml = sprintf($capcodeHtml, $nameHtml);
		}

		// return the nameHtml
		return $nameHtml;
	}
	
	private function generateTripcode(string $tripcode, string $secure_tripcode): string {
		// Check for secure tripcode first; use ★ symbol if present
		if($secure_tripcode) {
			return '<span class="postertrip">★' . $secure_tripcode . '</span>';
		}
		// Check for regular tripcode with ◆ symbol
		else if($tripcode) {
			return '<span class="postertrip">◆' . $tripcode . '</span>';
		}
	
		// return empty string if no conditions were met
		return '';
	}

	private function generateCapcodeHtml(string $tripcode = '', string $secure_tripcode = '', string $capcode = ''): string {
		// Check if either tripcode or secure tripcode has a defined capcode
		if (!empty($tripcode) || !empty($secure_tripcode)) {
			// generate user capcode html
			$capcodeHtml = $this->generateUserCapcodeHtml($tripcode, $secure_tripcode);
		}
	
		// If a capcode is provided, format the name accordingly
		elseif($capcode) {
			// generate the staff capcode
			$capcodeHtml = $this->generateStaffCapcodeWrapper($capcode);
		}

		// otherwise set it blank
		else {
			$capcodeHtml = '';
		}

		// return the html wrapper
		return $capcodeHtml;
	}

	private function generateUserCapcodeHtml(string $tripcode, string $secure_tripcode): string {
		// Retrieve the corresponding capcode
		$capcode = $this->findUserCapcode($tripcode, $secure_tripcode);
	
		// If there was no capcode, then return
		if(!$capcode) {
			return '';
		}

		// Extract the capcode color
		$capcodeColor = $capcode['color_hex'];
	
		// Extract the capcode text
		$capcodeText = $capcode['cap_text'];
	
		// Wrap the name HTML and append capcode text, applying the capcode color
		// %s is where the name goes
		$capcodeHtml = '<span class="capcodeSection" style="color:' . $capcodeColor . ';">%s<span class="postercap"> ## ' . $capcodeText . '</span> </span>';

		// return the user capcode html
		return $capcodeHtml;
	}

	private function generateStaffCapcodeWrapper(string $capcode): string {
		// Handle staff capcodes if defined in the config
		if(array_key_exists($capcode, $this->staffCapcodes)) {
			// Retrieve the corresponding capcode HTML template
			$capcodeMap = $this->staffCapcodes[$capcode];
			$capcodePlaceholder = $capcodeMap['capcodeHtml'];
		
			// Apply the capcode formatting (usually wraps or replaces nameHtml)
			$capcodeHtml = '<span class="postername">' . $capcodePlaceholder . '</span>';
		}
		
		// return the capcode html
		return $capcodeHtml;
	}

	private function findUserCapcode(string $tripcode, string $secure_tripcode): ?array {
		// check for a regular tripcode in the userCapcodes array
		$capcodeRow = find_row_by_key_value($this->userCapcodes, 'tripcode', $tripcode);

		// if no matching tripcode entry is found, stop here and return null
		if (!$capcodeRow) {
			return null;
		}

		// the capcode entry was found, so extract its relevant data
		$isSecure = !empty($capcodeRow['is_secure']); // flag indicating if this tripcode is secure
		$tripcodeFromCapcode = $capcodeRow['tripcode']; // the stored tripcode value from the capcode entry

		// if this capcode is marked as secure and the provided secure_tripcode matches it, it's valid
		if ($isSecure && $secure_tripcode === $tripcodeFromCapcode) {
			return $capcodeRow;
		}

		// if this capcode is not secure and the provided regular tripcode matches, it's valid
		if (!$isSecure && $tripcode === $tripcodeFromCapcode) {
			return $capcodeRow;
		}

		// if none of the above conditions match, the capcode is not valid for this user
		return null;
	}

}