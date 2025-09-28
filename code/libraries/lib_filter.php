<?php


/**
 * Apply a filter to an array by checking the key's value.
 * The function ensures the filter's value is an array of numeric keys and values.
 *
 * @param array $filters The array of filters to apply the filter on.
 * @param string $key The key in the filter array that holds the value to be processed.
 * @return array The filtered array, containing only numeric keys and values.
 */
function applyArrayFilter(array $filters, string $key): array {
	// Check if the key exists and if the value at the key is an array
	if (!isset($filters[$key]) || !is_array($filters[$key])) {
		return [];  // Return an empty array if the key is not set or the value is not an array
	}
	
	// Filter the array, keeping only numeric keys and numeric values
	return array_values(array_filter($filters[$key], fn($v, $k) => is_numeric($k) && is_numeric($v), ARRAY_FILTER_USE_BOTH));
}

/**
 * Apply a regular expression filter for IP address validation.
 * Converts a potentially wild-carded IP address (with *) to a valid regex pattern.
 *
 * @param string $ip The IP address to process.
 * @return string|null A regex pattern string if valid, null if the IP is invalid.
 */
function applyRegexIPFilter(string $ip): ?string {
	// Validate the format of the IP address (digits, periods, asterisks, or colons)
	if (!preg_match('/^[\d\.\*\:a-fA-F]+$/', $ip)) {
		return null;
	}
	
	// Escape for regex, so dots, asterisks, etc. are not treated as regex
	$pattern = preg_quote($ip, '/');

	// Replace the escaped asterisk (\*) with .*
	$ipPattern = "^" . str_replace('\*', '.*', $pattern) . "$";

	return $ipPattern;
}

/**
 * Bind the filter parameters to a SQL query for execution.
 * This function modifies the query by adding conditions based on the filters provided.
 *
 * @param array $params The array where bound parameters will be stored (by reference).
 * @param string $query The SQL query string to modify (by reference).
 * @param array $filters The filters array containing user input for filtering results.
 */
function bindPostFilterParameters(array &$params, string &$query, array $filters, bool $prefixColumn = false): void {
	// Some join queries group post related stuff with 'p.'
	if($prefixColumn) {
		$columnPrefix = 'p.';
	} else {
		$columnPrefix = '';
	}

	// Apply the 'board' filter and bind parameters
	$boards = applyArrayFilter($filters, 'board');
	if (!empty($boards)) {
		$query .= " AND (";
		foreach ($boards as $index => $board) {
			$query .= ($index > 0 ? " OR " : "") . $columnPrefix . "boardUID = :board_$index";
			$params[":board_$index"] = (int)$board;  // Bind the board UID as an integer
		}
		$query .= ")";
	}

	// Apply the 'tripcode' filter to both 'tripcode' and 'secure_tripcode' columns
	if (!empty($filters['tripcode']) && is_string($filters['tripcode'])) {
		$query .= " AND (" . $columnPrefix . "tripcode LIKE :tripcode OR " . $columnPrefix . "secure_tripcode LIKE :tripcode)";
		$params[":tripcode"] = '%' . $filters['tripcode'] . '%';
	}

	// Apply filters for 'comment', 'subject', and 'name' by binding the LIKE clauses
	foreach (['capcode' => $columnPrefix . 'capcode', 'comment' => $columnPrefix . 'com', 'subject' => $columnPrefix . 'sub', 'post_name' => $columnPrefix . 'name'] as $key => $column) {
		if (!empty($filters[$key]) && is_string($filters[$key])) {
			$query .= " AND {$column} LIKE :{$key}";
			$params[":{$key}"] = '%' . $filters[$key] . '%';  // Bind the filter as a LIKE clause
		}
	}

	// Apply the 'ip_address' filter using a regex pattern
	if (!empty($filters['ip_address']) && is_string($filters['ip_address'])) {
		$regex = applyRegexIPFilter($filters['ip_address']);
		if ($regex !== null) {
			$query .= " AND " . $columnPrefix . "host REGEXP :ip_regex";
			$params[':ip_regex'] = $regex;  // Bind the regex for IP address
		}
	}
}

/**
 * Binds filter parameters and appends WHERE conditions to a thread-related SQL query.
 *
 * @param array  &$params  The array to store bound parameters for the PDO statement.
 * @param string &$query   The SQL query string to which filter conditions will be appended.
 * @param array  $filters  The filters to apply (e.g., 'board', 'thread_uid', 'created_before').
 * @param string $orderBy  The column by which to order the results (default: 'last_bump_time').
 * @param bool   $isCount  Whether the query is a count query (avoids ORDER BY if true).
 */
function bindThreadFilterParameters(array &$params, string &$query, array $filters): void {
	// Apply the 'board' filter and bind parameters
	$boards = applyArrayFilter($filters, 'board');
	if (!empty($boards)) {
		$query .= " AND (";
		foreach ($boards as $index => $board) {
			$query .= ($index > 0 ? " OR " : "") . "t.boardUID = :board_$index";
			$params[":board_$index"] = (int)$board;
		}
		$query .= ")";
	}

	// Apply 'thread_uid' partial match
	if (!empty($filters['thread_uid']) && is_string($filters['thread_uid'])) {
		$query .= " AND t.thread_uid = :thread_uid";
		$params[':thread_uid'] = $filters['thread_uid'];
	}

	// Apply timestamp filters
	if (!empty($filters['created_before']) && is_string($filters['created_before'])) {
		$query .= " AND t.thread_created_time < :created_before";
		$params[':created_before'] = $filters['created_before'];
	}
	if (!empty($filters['created_after']) && is_string($filters['created_after'])) {
		$query .= " AND t.thread_created_time > :created_after";
		$params[':created_after'] = $filters['created_after'];
	}
	if (!empty($filters['bumped_after']) && is_string($filters['bumped_after'])) {
		$query .= " AND t.last_bump_time > :bumped_after";
		$params[':bumped_after'] = $filters['bumped_after'];
	}
	if (!empty($filters['replied_after']) && is_string($filters['replied_after'])) {
		$query .= " AND t.last_reply_time > :replied_after";
		$params[':replied_after'] = $filters['replied_after'];
	}
}

/**
 * Bind action log filter parameters to a SQL query for execution.
 * This function modifies the query based on the action log filters provided by the user.
 *
 * @param array $params The array where bound parameters will be stored (by reference).
 * @param string $query The SQL query string to modify (by reference).
 * @param array $filters The filters array containing user input for filtering action logs.
 */
function bindActionLogFiltersParameters(array &$params, string &$query, array $filters): void {
	// Apply the 'board' filter and bind parameters
	$boards = applyArrayFilter($filters, 'board');
	if (!empty($boards)) {
		$query .= " AND (";
		foreach ($boards as $index => $board) {
			$query .= ($index > 0 ? " OR " : "") . "board_uid = :board_$index";
			$params[":board_$index"] = (int)$board;  // Bind the board UID as an integer
		}
		$query .= ")";
	}

	// Apply filters for action log ID and other parameters
	if (!empty($filters['id'])) {
		$query .= " AND id = :id";
		$params[':id'] = (int)$filters['id'];  // Bind the action log ID
	}

	// Apply filter for the time the action was added
	if (!empty($filters['time_added'])) {
		$query .= " AND time_added = :time_added";
		$params[':time_added'] = $filters['time_added'];  // Bind the time added
	}

	// Apply filter for the log name
	if (!empty($filters['log_name'])) {
		$query .= " AND name = :log_name";
		$params[':log_name'] = strval($filters['log_name']);  // Bind the log name as a string
	}

	// Apply filters for 'role' and bind them as OR conditions
	if (!empty($filters['role']) && is_array($filters['role'])) {
		$query .= " AND (";
		foreach ($filters['role'] as $index => $role) {
			$query .= ($index > 0 ? " OR " : "") . "role = :role_$index";
			$params[":role_$index"] = (int)$role;  // Bind the role ID as an integer
		}
		$query .= ")";
	}

	// Apply filter for the log action and bind it as a LIKE clause
	if (!empty($filters['log_action'])) {
		$query .= " AND log_action LIKE :log_action";
		$params[':log_action'] = '%' . $filters['log_action'] . '%';  // Bind the action log
	}

	// Apply date range filters
	if (!empty($filters['date_after']) || !empty($filters['date_before'])) {
		$after = $filters['date_after'] ?? null;
		$before = $filters['date_before'] ?? null;

		if ($after && $before) {
			$minDate = min($after, $before);
			$maxDate = max($after, $before);
			$query .= " AND date_added BETWEEN :date_before AND :date_after";
			$params[':date_before'] = $minDate;  // Bind the 'before' date
			$params[':date_after'] = $maxDate;  // Bind the 'after' date
		} elseif ($after) {
			$query .= " AND date_added >= :date_after";
			$params[':date_after'] = $after;  // Bind the 'after' date
		} elseif ($before) {
			$query .= " AND date_added <= :date_before";
			$params[':date_before'] = $before;  // Bind the 'before' date
		}
	}

	// Apply filter for the IP address using regex
	if (!empty($filters['ip_address']) && is_string($filters['ip_address'])) {
		$regex = applyRegexIPFilter($filters['ip_address']);
		if ($regex !== null) {
			$query .= " AND ip_address REGEXP :ip_regex";
			$params[':ip_regex'] = $regex;  // Bind the regex for IP address
		}
	}

	$actionConditions = [];
	
	if (!empty($filters['deleted'])) {
		$actionConditions[] = "log_action LIKE :delete";
		$params[':delete'] = '%delete%';
	}

	if (!empty($filters['ban'])) {
		$actionConditions[] = "log_action LIKE :ban";
		$actionConditions[] = "log_action LIKE :mute";
		$actionConditions[] = "log_action LIKE :warn";
		$params[':ban'] = '%ban%';
		$params[':mute'] = '%mute%';
		$params[':warn'] = '%warn%';
	}

	if (!empty($actionConditions)) {
		$query .= " AND (" . implode(" OR ", $actionConditions) . ")";
	}

}


/**
 * Appends a boardUID IN (...) clause to the SQL query, binding the parameters safely.
 *
 * @param array  $params Reference to the array of PDO parameters
 * @param string $query  Reference to the SQL query string (will be modified)
 * @param array  $boardUIDs Array of board UIDs to filter by
 * @param string $columnAlias Which table column to use (default: 't.boardUID')
 */
function bindBoardUIDFilter(array &$params, string &$query, array $boardUIDs, string $columnAlias = 't.boardUID'): void {
	if (empty($boardUIDs)) {
		$query .= " AND 0"; // Prevents IN () syntax error
		return;
	}

	// Sanitize and reindex: keep only integers or digit strings
	$boardUIDs = array_values(array_unique(array_filter(
		$boardUIDs,
		fn($id) => is_int($id) || ctype_digit($id)
	)));

	if (empty($boardUIDs)) {
		$query .= " AND 0";
		return;
	}

	// Create placeholders
	$placeholders = [];
	foreach ($boardUIDs as $index => $uid) {
		$placeholder = ":boardUID$index";
		$placeholders[] = $placeholder;
		$params[$placeholder] = (int) $uid;
	}

	// Append to WHERE clause
	$query .= " AND {$columnAlias} IN (" . implode(', ', $placeholders) . ")";
}

/**
 * Build the filters array from the incoming HTTP request parameters.
 *
 * @param array $defaultFilters Default values for filters.
 * @return array An array containing the filters taken from the request or default values.
 */
function buildFiltersFromRequest(array $defaultFilters): array {
	$filtersArray = [];

	foreach($defaultFilters as $key=>$filter) {
		$filtersArray[$key] = $_GET[$key] ?? $filter;
	}
	
	return $filtersArray;
}

/**
 * Process 'role' and 'board' filters to ensure they are arrays if they are strings.
 *
 * @param array $filtersFromRequest The filters taken from the request.
 * @return array The processed filters with 'role' and 'board' as arrays.
 */
function processRoleAndBoardFilters(array $filtersFromRequest): array {
	// Ensure that 'role' is an array if it's provided as a space-separated string
	if (array_key_exists('role', $filtersFromRequest) && is_string($filtersFromRequest['role'])) {
		$filtersFromRequest['role'] = explode(' ', $filtersFromRequest['role']);
	}

	// Ensure that 'board' is an array if it's provided as a space-separated string
	if (array_key_exists('board', $filtersFromRequest) && is_string($filtersFromRequest['board'])) {
		$filtersFromRequest['board'] = explode(' ', $filtersFromRequest['board']);
	}

	return $filtersFromRequest;
}

/**
 * Handle redirection to a cleaned URL based on the filters.
 *
 * @param array $filtersFromRequest The filters taken from the request.
 * @param bool $isSubmission Whether the form is being submitted
 * @param array $defaultFilters The default filters.
 * @param string $actionLogUrl The base URL to which the cleaned URL will be appended.
 */
function handleRedirection(array $filtersFromRequest, bool $isSubmission, array $defaultFilters, string $actionLogUrl): void {
	// Check if the 'filterSubmissionFlag' is set in the request (for redirection)
	if ($isSubmission) {
		// Build the cleaned URL with the applied filters
		$cleanedUrl = buildSmartQuery($actionLogUrl, $defaultFilters, $filtersFromRequest);

		// Redirect to the cleaned URL
		redirect($cleanedUrl);
		exit;
	}
}

/**
 * Main function to get filters from the request, process them, and handle redirection if needed.
 *
 * @param board $board The board object that contains board-related data.
 * @param string $url The base url of the page
 * @return array The filters array, processed and ready for use.
 */
function getFiltersFromRequest(string $url, bool $isSubmission, array $defaultFilters): array {
	// Build filters based on the GET request
	$filtersFromRequest = buildFiltersFromRequest($defaultFilters);

	// Process the 'role' and 'board' filters to ensure they are arrays
	$filtersFromRequest = processRoleAndBoardFilters($filtersFromRequest);

	// trim trailing/prepended spaces
	$filtersFromRequest = array_map(function($item) {
		if (is_string($item)) {
			return trim($item);
		}
		return $item;
	}, $filtersFromRequest);

	// Handle redirection if the flag is set
	handleRedirection($filtersFromRequest, $isSubmission, $defaultFilters, $url);

	return $filtersFromRequest;
}
