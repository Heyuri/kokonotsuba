<?php
/**
 * Board exception class for user-facing errors.
 *
 * This exception is used to represent errors that should be shown directly
 * to the user, rather than being logged as internal system errors.
 *
 * All other exceptions are handled by the global exception handler and logged
 * to an error file. Instances of this class are caught separately and their
 * messages are displayed in the user interface.
 */

 class BoardException extends Exception {
    
 }