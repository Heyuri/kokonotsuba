<?php
/**
 * Global error handler
 *
 * @package PMCLibrary
 * @version $Id$
 */

/**
 * Handles normal PHP errors
 */
function errorHandler($errno, $errstr, $errfile, $errline) {
	$globalconfig = getGlobalConfig();
	// Ignore @ prefix suppressed error
	if (!(error_reporting() & $errno)) {
        return;
    }

      PMCLibrary::getLoggerInstance($globalconfig['ERROR_HANDLER_FILE'],'Global')->
    	error('Error caught: #%d: %s in %s on line %d',
    	$errno, $errstr, $errfile, $errline);
}
set_error_handler('errorHandler');

/**
 * Handles fatal PHP errors. Only PHP 5.2+ supports this method.
 */
function fatalErrorHandler() {
    // Get global config
    $globalconfig = getGlobalConfig();
    
    // Get the last error (if any)
    $e = error_get_last();
    if ($e !== NULL) {
        // Track and log memory usage at the time of the error
        $currentMemoryUsage = memory_get_usage(true);
        $peakMemoryUsage = memory_get_peak_usage(true);

        // Log the fatal error with memory info
        PMCLibrary::getLoggerInstance($globalconfig['ERROR_HANDLER_FILE'], 'Global')->
            error('Fatal error caught: #%d: %s in %s on line %d',
                $e['type'], $e['message'], $e['file'], $e['line']);
        
        // Log current memory and peak memory usage
        PMCLibrary::getLoggerInstance($globalconfig['ERROR_HANDLER_FILE'], 'Global')->
            error("Memory usage at the time of error: Current = %d bytes, Peak = %d bytes",
                $currentMemoryUsage, $peakMemoryUsage);

        // If it's a string conversion error, print backtrace
        if (strpos($e['message'], 'could not be converted to string') !== false) {
            // Capture backtrace
            ob_start();
            debug_print_backtrace();
            $trace = ob_get_clean();

            // Log the trace as a separate error
            PMCLibrary::getLoggerInstance($globalconfig['ERROR_HANDLER_FILE'], 'Global')->
                error("Backtrace for string conversion error:\n%s", $trace);
        }
    }
}

register_shutdown_function('fatalErrorHandler');

/**
 * Handles thrown exceptions by program itself or PHP.
 */
function exceptionHandler($e) {
	$globalconfig = getGlobalConfig();
	PMCLibrary::getLoggerInstance($globalconfig['ERROR_HANDLER_FILE'], 'Global')->error('Exception caught: %s', $e);
}
set_exception_handler('exceptionHandler');
