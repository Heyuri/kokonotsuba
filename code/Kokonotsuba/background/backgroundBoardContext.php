<?php

namespace Kokonotsuba\background;

use Kokonotsuba\containers\appContainer;
use Kokonotsuba\cookie\cookieService;
use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\database\transactionManager;
use Kokonotsuba\kernel\databaseHandles;
use Kokonotsuba\kernel\kernel;
use Kokonotsuba\request\request;

/**
 * The container a background task renders boards from: built the way a request builds it, with
 * no HTTP session, so everything it draws is what an anonymous reader gets.
 *
 * Defines GLOBAL_BOARD_ARRAY, so it is booted once per process.
 */
final class backgroundBoardContext {
	public static function boot(): appContainer {
		// background.php has already connected; reuse that connection.
		$databaseConnection = databaseConnection::getInstance();
		$db = new databaseHandles($databaseConnection, new transactionManager($databaseConnection), getDatabaseSettings(), getTableNames());

		// No HTTP session in CLI: an empty request and no cookies.
		return kernel::bootServices(new request(), new cookieService([]), $db);
	}
}
