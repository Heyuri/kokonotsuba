<?php

use Kokonotsuba\containers\appContainer;
use Kokonotsuba\kernel\kernel;
use Kokonotsuba\routers\modeHandler;

/* Prevent the user from aborting script execution */
ignore_user_abort(true);

// run the auto-loader
require __DIR__ . '/autoload.php';

// load all library files
kernel::loadLibraries();

// The backend entry point only runs when a board's koko.php requires it. Requested directly it
// has no board, so it refuses here rather than in a web server rule that has to know its path.
if (PHP_SAPI !== 'cli' && \Puchiko\request\isDirectRequestFor(__FILE__, $_SERVER)) {
	http_response_code(403);
	exit('Forbidden');
}

// initialize container class with all general dependencies
$container = kernel::bootWeb();

// init board dependencies + board object  
kernel::bootBoard($container);

kernel::run($container, static function (appContainer $container): void {
	$modeHandler = new modeHandler($container);

	// validate the currently selected board
	$modeHandler->validateBoard($container->get('board'));

	$modeHandler->handle();
});
