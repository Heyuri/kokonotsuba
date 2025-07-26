<?php
/*
* WIP database library for Kokonotsuba!
* This file is for functions to help with database/PDO queries and migration
*/


function extractAndConvertTimestamp(string $raw): ?string {
	// Match format like 2025/05/19(Mon)17:26:38
	if (preg_match('/\d{4}\/\d{2}\/\d{2}\([^)]+\)\d{2}:\d{2}:\d{2}/', $raw, $matches)) {
		$timestamp = $matches[0];

		// Remove the (Day) part
		$cleaned = preg_replace('/\([^)]+\)/', '', $timestamp);

		// Convert to MySQL-compatible format
		$dt = DateTime::createFromFormat('Y/m/d H:i:s', $cleaned);
		return $dt ? $dt->format('Y-m-d H:i:s') : null;
	}

	return null; // No valid timestamp found
}

// truncate string for mysql TEXT column
function truncateForText(string $input): string {
	$maxBytes = 65535;

	// Use mb_strcut to safely cut multibyte strings (like UTF-8) without breaking characters
	return mb_strcut($input, 0, $maxBytes, 'UTF-8');
}

function addApostropheToArray(&$arrayOfValuesForQuery) {
	foreach ($arrayOfValuesForQuery as &$item) {
		$item = "'" . addslashes($item) . "'";
	}
}