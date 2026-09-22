<?php

/* Prevent the user from aborting script execution */

use Kokonotsuba\debug\requestMetrics;
use Kokonotsuba\debug\requestProfiler;
use Kokonotsuba\error\BoardException;
use Kokonotsuba\kokoLibrary;
use Kokonotsuba\routers\modeHandler;

use function Kokonotsuba\libraries\_T;
use function Kokonotsuba\libraries\renderBasicBootstrapErrorPage;
use function Kokonotsuba\libraries\updateAccountSession;
use function Puchiko\json\renderJsonErrorPage;

ignore_user_abort(true);

// run the auto-loader
require __DIR__ . '/autoload.php';

// require constants (core file)
require_once __DIR__ . '/code/Kokonotsuba/constants.php';

// main requires
require __DIR__ . '/paths.php';
require __DIR__ . '/bootstrap/libraryIncludes.php';

// The backend entry point only runs when a board's koko.php requires it. Requested directly it
// has no board, so it refuses here rather than in a web server rule that has to know its path.
if (PHP_SAPI !== 'cli' && \Puchiko\request\isDirectRequestFor(__FILE__, $_SERVER)) {
	http_response_code(403);
	exit('Forbidden');
}

// Create request object from superglobals (must be early, before other bootstrap files)
$request = \Kokonotsuba\request\request::fromGlobals();

// Timings for the debug bar, and the request sampler when a profile was asked for. Both are
// started here, ahead of the bootstrap, so what they measure is the whole request.
requestMetrics::begin($request->getRequestTimeFloat());
requestProfiler::startIfRequested($request);

// Set the app root for this request
$kokoInstanceRoot = __DIR__ . '/';

require __DIR__ . '/bootstrap/session.php';

require __DIR__ . '/bootstrap/cookies.php';
require __DIR__ . '/bootstrap/global.php';
require __DIR__ . '/bootstrap/checks.php';

try {
	require __DIR__ . '/bootstrap/database.php';
} catch (RuntimeException $runtimeException) {
	renderBasicBootstrapErrorPage($runtimeException->getMessage());
}

require __DIR__ . '/bootstrap/container.php';
require __DIR__ . '/bootstrap/repositories.php';
require __DIR__ . '/bootstrap/board.php';
require __DIR__ . '/bootstrap/requestBoard.php';
require __DIR__ . '/bootstrap/dependencies.php';
require __DIR__ . '/bootstrap/di.php';


/*────────────────────────────────────────────────────────────
	Main Handler Execution
────────────────────────────────────────────────────────────*/
try {
	// Update session
	updateAccountSession($accountRepository, $loginSessionHandler);

	// init mode handler
	$modeHandler = new modeHandler($container);
	
	// validate the currently selected board
	$modeHandler->validateBoard($board);

	// run the mode router
	$modeHandler->handle();

} catch(BoardException $boardException) {
	// get error message
	$errorMessage = $boardException->getMessage();

	// a refusal is the client's doing unless the exception says otherwise; 500 is kept for crashes
	$statusCode = $boardException->getCode();
	$statusCode = is_int($statusCode) && $statusCode >= 400 && $statusCode <= 599 ? $statusCode : 400;

	// if its a request made by js, then serve json error
	if($request->isAjax()) {
		// strip html tags - message.js doesn't accept any raw html
		$errorMessage = strip_tags($errorMessage);

		// render the json page
		renderJsonErrorPage($errorMessage, $statusCode);
	}
	// otherwise its a regular request - serve html error page
	else {
		$softErrorHandler->errorAndExit($errorMessage, $statusCode);
	}
} catch (\Throwable $e) {
	// log message
	kokoLibrary::getLoggerInstance($globalConfig['ERROR_HANDLER_FILE'], 'Global')
		->error($e->__toString());

	// throw blanket error message
	$softErrorHandler->errorAndExit(_T('blanket_error'), 500, true);
}

clearstatcache();
