<?php

namespace Puchiko\array;

// Helper function for order-insensitive array comparison
function array_equals(array $arr1, array $arr2): bool {
	return count($arr1) === count($arr2) && 
		   empty(array_diff($arr1, $arr2)) && 
		   empty(array_diff($arr2, $arr1));
}

function find_row_by_key_value(array $rows, string $key, $value): ?array {
	// iterate over each row in the provided array
	foreach ($rows as $row) {
		// skip invalid rows that are not arrays
		if (!is_array($row)) {
			continue;
		}

		// check if the key exists in the current row
		if (array_key_exists($key, $row)) {
			// compare the value and return the row if it matches exactly
			if ($row[$key] === $value) {
				return $row;
			}
		}
	}

	// if no matching row is found, return null
	return null;
}