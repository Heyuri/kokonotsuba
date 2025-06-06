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
	$globalconfig = getGlobalConfig();
	$e = error_get_last();
	if($e !== NULL) {
	    PMCLibrary::getLoggerInstance($globalconfig['ERROR_HANDLER_FILE'],'Global')->
	    	error('Fatal error caught: #%d: %s in %s on line %d',
	    	$e['type'], $e['message'], $e['file'], $e['line']);
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
