<?php
/*
* Post html functions for Kokonotsuba!
* To avoid having to define html in multiple places
*/

/* Generate html for the post name dynamically */
function generatePostNameHtml(array $staffCapcodes, array $userCapcodes, string $name = '', string $tripcode = '', string $secure_tripcode = '', string $capcode = ''): string {
    // For compatability reasons, names already containing html will just be displayed without any further processing.
	// Because kokonotsuba previously stored name/trip/capcode html all in the name column, and this can cause double wrapped html
	if(containsHtmlTags($name)) return $name;

	$nameHtml = '<span class="postername">'.$name.'</span>';
	
	// Check for secure tripcode first; use ★ symbol if present
	if($secure_tripcode) {
		$nameHtml = $nameHtml.'<span class="postertrip">★'.$secure_tripcode.'</span>';
	}
	// Check for regular tripcode with ◆ symbol
	else if($tripcode) {
		$nameHtml = $nameHtml.'<span class="postertrip">◆'.$tripcode.'</span>';
	}

	// Check if either tripcode or secure tripcode has a defined capcode
	if (array_key_exists($tripcode, $userCapcodes) || array_key_exists($secure_tripcode, $userCapcodes)) {
		// Retrieve the corresponding capcode mapping (tripcode first, fallback to secure tripcode)
		$capcodeMap = $userCapcodes[$tripcode] ?? $userCapcodes[$secure_tripcode];
	
        // Extract the capcode color
		$capcodeColor = $capcodeMap['color'];

		// Extract the capcode text
		$capcodeText = $capcodeMap['cap'];

		// Wrap the name HTML and append capcode text, applying the capcode color
		$nameHtml = '<span class="capcodeSection" style="color:'.$capcodeColor.';">'.$nameHtml. '<span class="postercap">' .$capcodeText.'</span> </span>';
	}


	// If a capcode is provided, format the name accordingly
	if($capcode) {
		// Handle staff capcodes if defined in the config
		if(array_key_exists($capcode,$staffCapcodes)) {
			// Retrieve the corresponding capcode HTML template
			$capcodeMap = $staffCapcodes[$capcode];
			$capcodeHtml = $capcodeMap['capcodeHtml'];

			// Apply the capcode formatting (usually wraps or replaces nameHtml)
			$nameHtml = '<span class="postername">'.sprintf($capcodeHtml, $name).'</span>';
		}

	}

	return $nameHtml;
}