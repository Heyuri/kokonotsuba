<?php
/**
 * This library is for functions (often optional) that utilize or integrate features from separate scripts
 * 
 */

/**
 * Get "last post" information for multiple sources.
 * 
 * Supports poti-board and KuzuhaScriptPHP+
 *
 * Each source must define:
 * - 'label' (string) Display name
 * - 'url' (string) Link to the board
 * - 'logfile' (string) Path to log file
 * - 'type' (string) Either 'oekaki' or 'timestamp'
 *   - 'oekaki'    → file logs contain a datetime string at index 1 (Y/m/d(D) H:i)
 *   - 'timestamp' → file logs contain a UNIX timestamp at index 0
 * - 'suffix' (string) HTML appended after the time result (icon, emoji, etc.)
 *
 * @param array $sources Array of source definitions.
 * @return string Combined HTML output for all sources.
 */
function getLatestPosts(array $sources) {
	$output = '';

	// Iterate over each source definition
	foreach ($sources as $source) {
		$handle = fopen($source['logfile'], 'r');
		if (!$handle) {
			$output .= 'Error opening log file for ' . htmlspecialchars($source['label']) . '<br>';
			continue;
		}

		$line = fgets($handle);
		fclose($handle);

		$parts = explode(',', $line);

		if ($source['type'] === 'oekaki') {
			$dateString = rtrim($parts[1], " *");
			$date = DateTime::createFromFormat('Y/m/d(D) H:i', $dateString);
		} else {
			$date = (new DateTime())->setTimestamp($parts[0]);
		}
		
		//  calculate time difference and generate string
		if($date) {
			$now = new DateTime();

			$diff = $date->diff($now);
	
			$differenceString = buildTimeDiffString($diff);
		} 
		// time difference couldn't be calculated
		else {
			$differenceString = 'N/A';
		}

		$output .= 'Last <a href="' . $source['url'] . '">' . $source['label'] . '</a> post was <b>' .
			$differenceString .
			'</b> ago ' . $source['suffix'] . '<br>';
	}

	$output = substr($output, 0, -4) . '<hr class="hrThin">';

	return $output;
}

/**
 * Builds a human-readable time difference string.
 *
 * @param DateInterval $diff
 * @return string
 */
function buildTimeDiffString(DateInterval $diff) {
	if ($diff->i == 0 && $diff->h == 0 && $diff->d == 0) {
		return 'a few seconds';
	}

	return implode(', ', array_filter([
		$diff->d ? $diff->d . ' day' . ($diff->d > 1 ? 's' : '') : '',
		$diff->h ? $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') : '',
		$diff->i ? $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') : ''
	]));
}
