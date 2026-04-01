<?php

namespace Puchiko\request;

use Kokonotsuba\request\request;

/* redirect */
function redirect(string $to) {
	header("Location: " . $to);
	exit;
}