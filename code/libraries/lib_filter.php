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
    // Validate the format of the IP address (must only contain digits, periods, or asterisks)
    if (!preg_match('/^[\d\.\*]+$/', $ip)) {
        return null;  // Return null if the IP address is invalid
    }

    // Escape the IP address to be safely used in a regex pattern
    $pattern = preg_quote($ip, '/');
    
    // Replace '*' with '.*' for wildcard matching
    return "^" . str_replace('\*', '.*', $pattern) . "$";
}

/**
 * Bind the filter parameters to a SQL query for execution.
 * This function modifies the query by adding conditions based on the filters provided.
 *
 * @param array $params The array where bound parameters will be stored (by reference).
 * @param string $query The SQL query string to modify (by reference).
 * @param array $filters The filters array containing user input for filtering results.
 */
function bindfiltersParameters(array &$params, string &$query, array $filters): void {
    // Apply the 'board' filter and bind parameters
    $boards = applyArrayFilter($filters, 'board');
    if (!empty($boards)) {
        $query .= " AND (";
        foreach ($boards as $index => $board) {
            $query .= ($index > 0 ? " OR " : "") . "boardUID = :board_$index";
            $params[":board_$index"] = (int)$board;  // Bind the board UID as an integer
        }
        $query .= ")";
    }

    // Apply filters for 'comment', 'subject', and 'name' by binding the LIKE clauses
    foreach (['comment' => 'com', 'subject' => 'sub', 'name' => 'name'] as $key => $column) {
        if (!empty($filters[$key]) && is_string($filters[$key])) {
            $query .= " AND {$column} LIKE :{$key}";
            $params[":{$key}"] = '%' . $filters[$key] . '%';  // Bind the filter as a LIKE clause
        }
    }

    // Apply the 'ip_address' filter using a regex pattern
    if (!empty($filters['ip_address']) && is_string($filters['ip_address'])) {
        $regex = applyRegexIPFilter($filters['ip_address']);
        if ($regex !== null) {
            $query .= " AND host REGEXP :ip_regex";
            $params[':ip_regex'] = $regex;  // Bind the regex for IP address
        }
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

    // Apply filter for deleted logs
    if (!empty($filters['deleted'])) {
        $query .= " AND log_action LIKE :delete";
        $params[':delete'] = '%delete%';  // Bind the 'delete' action in the log
    }

    // Apply filter for ban, mute, or warn actions
    if (!empty($filters['ban'])) {
        $query .= " AND (log_action LIKE :ban OR log_action LIKE :mute OR log_action LIKE :warn)";
        $params[':ban'] = '%ban%';  // Bind the 'ban' action
        $params[':mute'] = '%mute%';  // Bind the 'mute' action
        $params[':warn'] = '%warn%';  // Bind the 'warn' action
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

	// Sanitize: filter integers only and remove duplicates
	$boardUIDs = array_unique(array_filter($boardUIDs, fn($id) => is_int($id) || ctype_digit($id)));

	if (empty($boardUIDs)) {
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
    if (is_string($filtersFromRequest['role'])) {
        $filtersFromRequest['role'] = explode(' ', $filtersFromRequest['role']);
    }

    // Ensure that 'board' is an array if it's provided as a space-separated string
    if (is_string($filtersFromRequest['board'])) {
        $filtersFromRequest['board'] = explode(' ', $filtersFromRequest['board']);
    }

    return $filtersFromRequest;
}

/**
 * Handle redirection to a cleaned URL based on the filters.
 *
 * @param array $filtersFromRequest The filters taken from the request.
 * @param array $defaultFilters The default filters.
 * @param string $actionLogUrl The base URL to which the cleaned URL will be appended.
 */
function handleRedirection(array $filtersFromRequest, array $defaultFilters, string $actionLogUrl): void {
    // Check if the 'filterSubmissionFlag' is set in the request (for redirection)
    if (isset($_GET['filterSubmissionFlag'])) {
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
function getFiltersFromRequest(string $url, array $defaultFilters): array {
    // Build filters based on the GET request
    $filtersFromRequest = buildFiltersFromRequest($defaultFilters);

    // Process the 'role' and 'board' filters to ensure they are arrays
    $filtersFromRequest = processRoleAndBoardFilters($filtersFromRequest);

    // Handle redirection if the flag is set
    handleRedirection($filtersFromRequest, $defaultFilters, $url);

    return $filtersFromRequest;
}
