<?php

// Automatic Article Deletion Mechanism
class PIOSensor {
	
	/**
	 * Check if any condition requires processing.
	 *
	 * @param string $type The type of check to perform.
	 * @param array $conditions Array of objects and their corresponding parameters.
	 * @return bool True if any condition passes the check, otherwise false.
	 */
	public static function check($board, $type, array $conditions) {
		foreach ($conditions as $object => $params) {
			// Call the 'check' method on the object/class with given parameters
			if (call_user_func_array([$object, 'check'], [$board, $type, $params]) === true) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Get a sorted, unique list of results from conditions.
	 *
	 * @param string $type The type of operation to list.
	 * @param array $conditions Array of objects and their corresponding parameters.
	 * @return array Sorted and unique list of results.
	 */
	public static function listee($board, $type, array $conditions) {
		$resultList = []; // Array to collect results

		foreach ($conditions as $object => $params) {
			// Merge results from calling 'listee' on the object/class
			$resultList = array_merge(
				$resultList,
				call_user_func_array([$object, 'listee'], [$board, $type, $params])
			);
		}

		// Sort results in ascending order and remove duplicates
		sort($resultList);
		return array_unique($resultList);
	}

	/**
	 * Gather information from all conditions.
	 *
	 * @param array $conditions Array of objects and their corresponding parameters.
	 * @return string Concatenated information string.
	 */
	public static function info($board, array $conditions) {
		$sensorInfo = ''; // String to collect information

		foreach ($conditions as $object => $params) {
			// Append information from calling 'info' on the object/class
			$sensorInfo .= call_user_func_array([$object, 'info'], [$board, $params]) . "\n";
		}

		return $sensorInfo;
	}
}

