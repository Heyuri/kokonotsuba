<?php
/**
 * Appends everything it is given to a log file. Only the error level is emitted.
 */

namespace Kokonotsuba\logger;

use Kokonotsuba\interfaces\ILogger;

class SimpleLogger implements ILogger {
	private $logName;
	private $logFile;

	public function __construct($logFile, $logName) {
		$this->logName = $logName;
		$this->logFile = $logFile;
	}

	public function isErrorEnabled() {
		return true;
	}

	public function error($format, $varargs = '') {
		if (!$this->isErrorEnabled()) return;

		$varargs = func_get_args();
		array_shift($varargs);
		$this->logFormat('ERROR', $format, $varargs);
	}

	/**
	 * Log with format message.
	 *
	 * @param  string $logLevel   Log level
	 * @param  string $message Format message
	 * @param  array  $vars    Prarameters
	 */
	private function logFormat($logLevel, $message, array $vars) {
		$dateTime = date('c');
		$message = vsprintf($message, $vars);
		file_put_contents(
			$this->logFile,
			"$dateTime $logLevel {$this->logName} - $message".PHP_EOL,
			FILE_APPEND
		);
	}
}
