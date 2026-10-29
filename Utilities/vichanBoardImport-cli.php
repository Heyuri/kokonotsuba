
<?php
/***************
 * Vichan Board Import CLI
 * 
 * Usage: php vichanBoardImport-cli.php /path/to/vichan.sql /boards/path
 *
 * Imports boards and posts from a vichan SQL dump into kokonotsuba.
 ****************/

use Kokonotsuba\board\boardCreator;
use Kokonotsuba\board\import\vichanBoardImporter;
use Kokonotsuba\cookie\cookieService;
use Kokonotsuba\kernel\kernel;
use Kokonotsuba\request\request;

// ─── Resolve root directory ───
$rootDir = __DIR__ . '/../';

// ─── Autoloader & core includes ───
require $rootDir . 'autoload.php';
require_once $rootDir . 'code/Kokonotsuba/constants.php';
require $rootDir . 'paths.php';
\Kokonotsuba\kernel\kernel::loadLibraries();

// ─── Container: CLI request, no cookies, repositories and the board list ───
$container = kernel::bootServices(new request(), new cookieService([]));

$databaseConnection = $container->get('databaseConnection');
$transactionManager = $container->get('transactionManager');
$boardService = $container->get('boardService');
$fileService = $container->get('fileService');
$postRepository = $container->get('postRepository');
$threadRepository = $container->get('threadRepository');
$quoteLinkRepository = $container->get('quoteLinkRepository');

// ─── Parse CLI arguments ───
$sqlDump = $argv[1] ?? null;
$boardPath = $argv[2] ?? null;

if (!$sqlDump || !$boardPath) {
	echo "Usage: php " . basename(__FILE__) . " /path/to/vichan.sql /boards/path\n";
	exit(1);
}

// ─── Run import ───
$boardCreator = new boardCreator($boardService);

$vichanBoardImporter = new vichanBoardImporter(
	$databaseConnection,
	$boardCreator,
	$boardService,
	$postRepository,
	$threadRepository,
	$fileService,
	$transactionManager,
	$quoteLinkRepository
);

$vichanBoardImporter->importVichanInstance($sqlDump, $boardPath);