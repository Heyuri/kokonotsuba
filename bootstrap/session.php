<?php

// Set a unique session name for the instance
session_name('kokonotsuba_session_id_' . substr(md5(__DIR__), 0, 5)); 

// Get host from request
$host = $_SERVER['HTTP_HOST'] ?? '';

// Strip port if present
$host = preg_replace('/:\d+$/', '', $host);

// Strip IPv6 brackets if present
$host = trim($host, '[]');

// Decide if it's safe/valid to set the cookie domain
$isIp = filter_var($host, FILTER_VALIDATE_IP) !== false;
$isLocalhost = $host === 'localhost';
$isOnion = strtolower(substr($host, -6)) === '.onion';

// Remove first label to get base domain if you want it for all subdomains
if (!$isIp && !$isLocalhost && !$isOnion) {
	$parts = explode('.', $host);
	if (count($parts) > 2) {
		$host = implode('.', array_slice($parts, -2));
	}
}

// Build cookie params
$params = [
	'lifetime' => 0,
	'path'     => '/',
	'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
	'httponly' => true
];

if (!$isIp && !$isLocalhost && !$isOnion && $host !== '') {
	$params['domain'] = $host; // applies to apex + all subdomains
}

// Set cookie params for whole domain (or host-only when domain is omitted)
session_set_cookie_params($params);

session_start();
