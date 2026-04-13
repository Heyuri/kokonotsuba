
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
use Kokonotsuba\request\request;
use Kokonotsuba\account\staffAccountFromSession;
use Kokonotsuba\policy\postPolicy;
use Kokonotsuba\policy\postRenderingPolicy;
use Kokonotsuba\containers\appContainer;

// ─── Resolve root directory ───
$rootDir = __DIR__ . '/../';

// ─── Autoloader & core includes ───
require $rootDir . 'autoload.php';
require_once $rootDir . 'code/Kokonotsuba/constants.php';
require $rootDir . 'paths.php';
require $rootDir . 'bootstrap/libraryIncludes.php';

// ─── CLI-compatible request (no HTTP superglobals) ───
$request = new request();

// ─── Global config & policies ───
$globalConfig = getGlobalConfig();
$cookieService = new cookieService([]);
$staffAccountFromSession = new staffAccountFromSession();
$currentUserId = $staffAccountFromSession->getUID();
$postPolicy = new postPolicy(
	$globalConfig['AuthLevels'],
	$staffAccountFromSession->getRoleLevel(),
	$currentUserId
);
$postRenderingPolicy = new postRenderingPolicy(
	$globalConfig['AuthLevels'],
	$staffAccountFromSession->getRoleLevel(),
	$currentUserId,
	$cookieService
);

// ─── Database ───
require $rootDir . 'bootstrap/database.php';

// ─── Container ───
$container = new appContainer();
$container->set('request', $request);
$container->set('cookieService', $cookieService);
$container->set('staffAccountFromSession', $staffAccountFromSession);
$container->set('currentUserId', $currentUserId);
$container->set('postPolicy', $postPolicy);
$container->set('postRenderingPolicy', $postRenderingPolicy);
$container->set('globalConfig', $globalConfig);
$container->set('databaseConnection', $databaseConnection);
$container->set('transactionManager', $transactionManager);

// ─── Repositories & Board ───
require $rootDir . 'bootstrap/repositories.php';
require $rootDir . 'bootstrap/board.php';

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