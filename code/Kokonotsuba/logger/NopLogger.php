<?php
/**
 * A logger that discards everything handed to it.
 *
 * Only ever appropriate in production, and rarely even there: nothing it is given
 * survives, so there is nothing to debug from afterwards.
 */

namespace Kokonotsuba\logger;

use Kokonotsuba\interfaces\ILogger;

class NopLogger implements ILogger {
	public function __construct($logName, $logFile) {}

	public function isErrorEnabled() {
		return false;
	}


	public function error($format, $varargs = '') {}
}
