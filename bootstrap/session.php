<?php

// Set a unique session name for the instance
session_name('kokonotsuba_session_id_' . substr(md5(__DIR__), 0, 5)); 

// Get host from request
$host = $_SERVER['HTTP_HOST'] ?? '';

// Strip port if present
$host = preg_replace('/:\d+$/', '', $host);

// Remove first label to get base domain if you want it for all subdomains
$parts = explode('.', $host);
if (count($parts) > 2) {
	$host = implode('.', array_slice($parts, -2));
}

// Set cookie params for whole domain
session_set_cookie_params([
	'lifetime' => 0,
	'path'     => '/',
	'domain'   => $host, // applies to apex + all subdomains
	'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
	'httponly' => true
]);

session_start();
