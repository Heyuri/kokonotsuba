<?php

namespace Kokonotsuba\Modules\tripcode;

use BoardException;

function validateCapcodeId(int $id): void {
	// ensure ID is a non-zero positive integer
	if ($id <= 0) {
		throw new BoardException("Capcode ID must be a non-zero positive integer.");
	}

	// no error triggered - good
	// continue program
}