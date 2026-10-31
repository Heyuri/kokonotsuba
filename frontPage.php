<?php

use Kokonotsuba\containers\appContainer;
use Kokonotsuba\kernel\kernel;
use Kokonotsuba\routers\modeHandler;

// The front page's backend entry point. Served through an index.php that requires it: the one
// beside it when the backend is the web root, or a copy an admin places in another web root.

ignore_user_abort(true);

require __DIR__ . '/autoload.php';

kernel::loadLibraries();

if (PHP_SAPI !== 'cli' && \Puchiko\request\isDirectRequestFor(__FILE__, $_SERVER)) {
	http_response_code(403);
	exit('Forbidden');
}

$container = kernel::bootWeb();

kernel::bootFrontPage($container);

kernel::run($container, static function (appContainer $container): void {
	modeHandler::forFrontPage($container)->handle();
});
