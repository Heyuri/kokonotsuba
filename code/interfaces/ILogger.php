<?php

/**
 * ILogger
 */
interface ILogger {
	/**
	 * Constructor.
	 *
	 * @param string $logName Logger name
	 * @param string $logFile Log file path
	 */
	public function __construct($logName, $logFile);

	/**
	 * Check if the logger should log ERROR level messages.
	 *
	 * @return boolean Whether ERROR level logging is enabled
	 */
	public function isErrorEnabled();

	/**
	 * Log a message at the ERROR level.
	 *
	 * @param string $format  Formatted message content
	 * @param mixed  $varargs Parameters
	 */
	public function error($format, $varargs = '');
}
