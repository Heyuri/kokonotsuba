<?php

namespace Kokonotsuba\Modules\tripcode;

use Kokonotsuba\error\BoardException;
use Kokonotsuba\userRole;
use function Kokonotsuba\libraries\_T;
use function Kokonotsuba\libraries\generateTripcode;

class tripcodeProcessor {

	// Constructor to initialize config instance
	public function __construct(
		private readonly array $config) {}

	/**
	 * Main method to apply tripcode processing to a given name.
	 *
	 * @param string $capcodeInput What was typed after " ## ", when anything was. A staff capcode
	 *                             asked for this way by too low a role is refused outright.
	 */
	public function apply(string &$name, string &$tripcode, string &$secure_tripcode, string &$capcode, userRole $roleLevel, string $capcodeInput = ''): void {
		// Check for fraud symbols in the name and append " (fraudster)" if found
		if ($this->containsFraudSymbol($name)) {
			$name .= " (fraudster)";

			// replace symbols for good measure
			$name = $this->replaceFullWithHollow($name);

			return;
		}

		// another module (thread capcodes) already decided the capcode
		if ($capcode !== '') {
			$this->generateTripcodeFromInput($tripcode, $secure_tripcode);
			return;
		}

		$this->assertCapcodeAllowed($capcodeInput, $roleLevel);

		// Apply role capcode if conditions are met
		$capcode = $this->setCapcodeIfExists($roleLevel, $secure_tripcode);

		// now return
		if($capcode) {
			// Staff capcode found — clear secure_tripcode but still generate the regular tripcode
			$secure_tripcode = '';
			$this->generateTripcodeFromInput($tripcode, $secure_tripcode);
			return;
		}

		// Generate tripcode based on extracted trip parts
		$this->generateTripcodeFromInput($tripcode, $secure_tripcode);

	}

	// Check if name contains fraud symbols
	private function containsFraudSymbol(string $name): bool {
		return preg_match('/[◆◇♢♦⟡★]/u', $name);
	}

	private function replaceFullWithHollow(string $text) {
	    // Define the replacements in an associative array
	    $replacements = [
	        '★' => '☆',  // Full star to hollow star
	        '♦' => '♢',  // Full diamond to hollow diamond
	        '◆' => '◇'  // Full black diamond to hollow diamond
	    ];

	    // Replace full symbols with hollow ones
	    foreach ($replacements as $full => $hollow) {
	        $text = str_replace($full, $hollow, $text);
	    }

	    return $text;
	}

	/** An explicit "## Mod" from someone who is not one is an error, not a tripcode. */
	private function assertCapcodeAllowed(string $capcodeInput, userRole $roleLevel): void {
		if ($capcodeInput === '' || !array_key_exists($capcodeInput, $this->config['staffCapcodes'])) {
			return;
		}

		if ($roleLevel->isLessThan($this->config['staffCapcodes'][$capcodeInput]['requiredRole'])) {
			throw new BoardException(_T('threadmod_staff_capcode_refused', $capcodeInput), 403);
		}
	}

	// Set the capcode if it exists in the array key
	private function setCapcodeIfExists(userRole $roleLevel, string $secure_tripcode): string {
		if(array_key_exists($secure_tripcode, $this->config['staffCapcodes']) && $roleLevel->isAtLeast($this->config['staffCapcodes'][$secure_tripcode]['requiredRole'])) {
			return $secure_tripcode;
		}
		return '';
	}

	// Generate regular and/or secure tripcodes
	private function generateTripcodeFromInput(string &$tripcode, string &$secure_tripcode): void {
		generateTripcode($tripcode, $secure_tripcode, $this->config['TRIPSALT']);
	}

}
