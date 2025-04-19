<?php

// for filter arrays

function applyArrayFilter(array $filters, string $key): array {
	if (!isset($filters[$key]) || !is_array($filters[$key])) {
		return [];
	}
	return array_values(array_filter($filters[$key], fn($v, $k) => is_numeric($k) && is_numeric($v), ARRAY_FILTER_USE_BOTH));
}

function applyRegexIPFilter(string $ip): ?string {
	if (!preg_match('/^[\d\.\*]+$/', $ip)) {
		return null;
	}
	$pattern = preg_quote($ip, '/');
	return "^" . str_replace('\*', '.*', $pattern) . "$";
}


function bindfiltersParameters(array &$params, string &$query, array $filters): void {
	$boards = applyArrayFilter($filters, 'board');
	if (!empty($boards)) {
		$query .= " AND (";
		foreach ($boards as $index => $board) {
			$query .= ($index > 0 ? " OR " : "") . "boardUID = :board_$index";
			$params[":board_$index"] = (int)$board;
		}
		$query .= ")";
	}

	foreach (['comment' => 'com', 'subject' => 'sub', 'name' => 'name'] as $key => $column) {
		if (!empty($filters[$key]) && is_string($filters[$key])) {
			$query .= " AND {$column} LIKE :{$key}";
			$params[":{$key}"] = '%' . $filters[$key] . '%';
		}
	}

	if (!empty($filters['ip_address']) && is_string($filters['ip_address'])) {
		$regex = applyRegexIPFilter($filters['ip_address']);
		if ($regex !== null) {
			$query .= " AND host REGEXP :ip_regex";
			$params[':ip_regex'] = $regex;
		}
	}
}

function bindActionLogFiltersParameters(array &$params, string &$query, array $filters): void {
	$boards = applyArrayFilter($filters, 'board');
	if (!empty($boards)) {
		$query .= " AND (";
		foreach ($boards as $index => $board) {
			$query .= ($index > 0 ? " OR " : "") . "board_uid = :board_$index";
			$params[":board_$index"] = (int)$board;
		}
		$query .= ")";
	}

	if (!empty($filters['id'])) {
		$query .= " AND id = :id";
		$params[':id'] = (int)$filters['id'];
	}

	if (!empty($filters['time_added'])) {
		$query .= " AND time_added = :time_added";
		$params[':time_added'] = $filters['time_added'];
	}

	if (!empty($filters['name'])) {
		$query .= " AND name = :name";
		$params[':name'] = strval($filters['name']);
	}

	if (!empty($filters['role']) && is_array($filters['role'])) {
		$query .= " AND (";
		foreach ($filters['role'] as $index => $role) {
			$query .= ($index > 0 ? " OR " : "") . "role = :role_$index";
			$params[":role_$index"] = (int)$role;
		}
		$query .= ")";
	}

	if (!empty($filters['log_action'])) {
		$query .= " AND log_action LIKE :log_action";
		$params[':log_action'] = '%' . $filters['log_action'] . '%';
	}

	if (!empty($filters['date_after']) || !empty($filters['date_before'])) {
		$after = $filters['date_after'] ?? null;
		$before = $filters['date_before'] ?? null;

		if ($after && $before) {
			$minDate = min($after, $before);
			$maxDate = max($after, $before);
			$query .= " AND date_added BETWEEN :date_before AND :date_after";
			$params[':date_before'] = $minDate;
			$params[':date_after'] = $maxDate;
		} elseif ($after) {
			$query .= " AND date_added >= :date_after";
			$params[':date_after'] = $after;
		} elseif ($before) {
			$query .= " AND date_added <= :date_before";
			$params[':date_before'] = $before;
		}
	}

	if (!empty($filters['ip_address']) && is_string($filters['ip_address'])) {
		$regex = applyRegexIPFilter($filters['ip_address']);
		if ($regex !== null) {
			$query .= " AND ip_address REGEXP :ip_regex";
			$params[':ip_regex'] = $regex;
		}
	}

	if (!empty($filters['deleted'])) {
		$query .= " AND log_action LIKE :delete";
		$params[':delete'] = '%delete%';
	}

	if (!empty($filters['ban'])) {
		$query .= " AND (log_action LIKE :ban OR log_action LIKE :mute OR log_action LIKE :warn)";
		$params[':ban'] = '%ban%';
		$params[':mute'] = '%mute%';
		$params[':warn'] = '%warn%';
	}
}

