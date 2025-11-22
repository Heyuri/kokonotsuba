<?php
/*

YOU MUST GIVE CREDIT TO WWW.HEYURI.NET ON YOUR BBS IF YOU ARE PLANNING TO USE THIS SOFTWARE.

*/

/* Prevent the user from aborting script execution */
ignore_user_abort(true);

require __DIR__ . '/bootstrap/session.php';
require __DIR__ . '/paths.php';
require __DIR__ . '/includes.php';
require __DIR__ . '/bootstrap/checks.php';
require __DIR__ . '/bootstrap/database.php';
require __DIR__ . '/bootstrap/repositories.php';
require __DIR__ . '/bootstrap/board.php';
require __DIR__ . '/bootstrap/dependencies.php';
require __DIR__ . '/bootstrap/di.php';

/*────────────────────────────────────────────────────────────
	Main Handler Execution
────────────────────────────────────────────────────────────*/
try {
	// Update session
	updateAccountSession($accountRepository, $loginSessionHandler);

	// init mode handler
	$modeHandler = new modeHandler($routeDiContainer);
	
	// validate the currently selected board
	$modeHandler->validateBoard($board);

	// run the mode router
	$modeHandler->handle();

} catch(\BoardException $boardException) {
	$errorMessage = $boardException->getMessage();

	$softErrorHandler->errorAndExit($errorMessage);
} catch (\Throwable $e) {
	PMCLibrary::getLoggerInstance($globalConfig['ERROR_HANDLER_FILE'], 'Global')
		->error($e->getMessage());

	$softErrorHandler->errorAndExit("There has been an error. (;´Д`)");
}

clearstatcache();
