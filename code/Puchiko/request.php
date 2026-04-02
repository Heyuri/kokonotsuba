<?php

namespace Puchiko\request;

/* redirect */
function redirect(string $to) {
	// Prevent header injection (CRLF)
	$to = str_replace(["\r", "\n", "\0"], '', $to);

	header("Location: " . $to);
	exit;
}