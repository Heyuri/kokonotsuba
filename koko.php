<?php
/*

YOU MUST GIVE CREDIT TO WWW.HEYURI.NET ON YOUR BBS IF YOU ARE PLANNING TO USE THIS SOFTWARE.

*/
session_start();

/* Prevent the user from aborting script execution */
ignore_user_abort(true); 

function getBackendDir() {
	return __DIR__.'/';
}

function getBackendCodeDir() {
	return getBackendDir().'code/';
}

function getBackendGlobalDir() {
	return getBackendDir().'global/';
}

function getBoardConfigDir() {
	return getBackendGlobalDir().'board-configs/';
}

function getBoardStoragesDir() {
	return getBackendGlobalDir().'board-storages/';
}

function getTemplateConfigArray() {
	// Path to the board template configuration file
	$configFile = getBoardConfigDir() . 'board-template.php';
	
	// Check if the file exists before including
	if (!file_exists($configFile)) {
		throw new Exception("Configuration file not found: " . $configFile);
	}

	require $configFile;
	
	// Ensure $config is set in the included file
	if (!isset($config)) {
		throw new Exception("Configuration array \$config is not defined in: " . $configFile);
	}
	
	return $config;
}



require getBackendDir().'includes.php';


function generateNewBoardConfigFile(string $boardUid): ?string {
	$templateConfigPath = getBoardConfigDir().'board-template.php';//config template
	$newConfigFileName = 'board-' . $boardUid . '.php';
	$boardConfigsDirectory = getBoardConfigDir();

	if(!copyFileWithNewName($templateConfigPath, $newConfigFileName, $boardConfigsDirectory)) throw new Exception("Failed to copy new config file");
	return $newConfigFileName;
}

/* get the database settings from dbSettings PHP file */
function getDatabaseSettings() {
	$dbSettings = require __DIR__.'/databaseSettings.php';
	if(empty($dbSettings)) die("Could not read database settings.");	
	else return $dbSettings;
}

function getGlobalConfig() {
	require __DIR__.'/global/globalconfig.php';
	
	return $config;
}


/*────────────────────────────────────────────────────────────
	The main judgment of the functions of the program
────────────────────────────────────────────────────────────*/

//Check if this is the backend
if(file_exists('.backend')) die("You are trying to access the instance's backend");


// Global configuration file
$globalConfig	= getGlobalConfig();

// ───────────────────────────────────────
// Database Setup
// ───────────────────────────────────────
$dbSettings = getDatabaseSettings();

DatabaseConnection::createInstance($dbSettings);

$databaseConnection = DatabaseConnection::getInstance();

$transactionManager = new transactionManager($databaseConnection);



// ───────────────────────────────────────
// IO / Core Systems
// ───────────────────────────────────────
PMCLibrary::createFileIOInstance();
$FileIO				= PMCLibrary::getFileIOInstance();

// ───────────────────────────────────────
// Account and action log Bootstrap
// ───────────────────────────────────────
$accountRepository = new accountRepository($databaseConnection, $dbSettings['ACCOUNT_TABLE']);

$actionLoggerRepository = new actionLoggerRepository($databaseConnection, $dbSettings['ACTIONLOG_TABLE'], $dbSettings['BOARD_TABLE']);
$actionLoggerService = new actionLoggerService($actionLoggerRepository, $accountRepository); 

$accountService = new accountService($accountRepository, $actionLoggerService);

// ───────────────────────────────────────
// Post/Thread Bootstrap
// ───────────────────────────────────────
$attachmentRepository = new attachmentRepository($databaseConnection, $dbSettings['POST_TABLE'], $dbSettings['THREAD_TABLE']);
$attachmentService = new attachmentService($attachmentRepository);
$threadRepository = new threadRepository($databaseConnection, $dbSettings['POST_TABLE'], $dbSettings['THREAD_TABLE']);
$postRepository = new postRepository($databaseConnection, $dbSettings['POST_TABLE'], $dbSettings['THREAD_TABLE']);
$postService = new postService($postRepository, $transactionManager, $threadRepository, $attachmentService);
$threadService = new threadService($databaseConnection, $threadRepository, $postRepository, $postService, $transactionManager, $dbSettings['THREAD_TABLE'], $dbSettings['POST_TABLE']);
$quoteLinkRepository = new quoteLinkRepository($databaseConnection, $dbSettings['QUOTE_LINK_TABLE'], $dbSettings['POST_TABLE'], $dbSettings['THREAD_TABLE']);
$quoteLinkService = new quoteLinkService($quoteLinkRepository, $postRepository);
$postSearchRepository = new postSearchRepository($databaseConnection, $dbSettings['POST_TABLE'], $dbSettings['THREAD_TABLE']);
$postSearchService = new postSearchService($postSearchRepository);
$postRedirectRepository = new postRedirectRepository($databaseConnection, $dbSettings['THREAD_REDIRECT_TABLE'], $dbSettings['THREAD_TABLE']);
$postRedirectService = new postRedirectService($postRedirectRepository, $threadService);

// ───────────────────────────────────────
// Board Bootstrap
// ───────────────────────────────────────
$boardPostNumbers = new boardPostNumbers($databaseConnection, $dbSettings['POST_NUMBER_TABLE']);

$boardPathRepository = new boardPathRepository($databaseConnection, $dbSettings['BOARD_PATH_CACHE_TABLE']);

$boardPathService = new boardPathService($boardPathRepository);

$boardRepository = new boardRepository($databaseConnection, $dbSettings['BOARD_TABLE']);

$boardDiContainer = new boardDiContainer(
	$postRepository, 
	$postService, 
	$actionLoggerService, 
	$threadRepository, 
	$threadService, 
	$quoteLinkService, 
	$boardPostNumbers, 
	$boardPathService, 
	$postSearchService,
	$attachmentService,
	$postRedirectService,
	$transactionManager
);


$boardService = new boardService($boardRepository, $boardDiContainer, $boardPathService);

$boardList = $boardService->getAllRegularBoards();

$visibleBoards = $boardService->getAllListedBoards();

// Get the board that's currently being accessed by the request
$board = $boardService->getBoardFromBootstrapFile('./boardUID.ini');

// Globally accessible board array, it exists to avoid managing complicated dependencies and circular dependencies
// Defines and globals are to be avoided, but this is an exception
define('GLOBAL_BOARD_ARRAY', $boardService->getAllRegularBoards());

// ───────────────────────────────────────
// Validate Early
// ───────────────────────────────────────
if (!file_exists($board->getFullConfigPath()) || !file_exists($board->getBoardStoragePath())) {
	die("Invalid board setup");
}

$config = $board->loadBoardConfig();

// ───────────────────────────────────────
// Dependencies
// ───────────────────────────────────────
$templateEngine		= $board->getBoardTemplateEngine();
$moduleEngine		= $board->getModuleEngine();

$adminTemplateEngine = new templateEngine(getBackendDir() . 'templates/admin.tpl', [
	'config'	=> $config,
	'boardData'	=> [
		'title'		=> $board->getBoardTitle(),
		'subtitle'	=> $board->getBoardSubTitle()
	]
]);

$adminPageRenderer	= new pageRenderer($adminTemplateEngine, $moduleEngine, $board);

// ───────────────────────────────────────
// Error Handling & Authentication
// ───────────────────────────────────────
$softErrorHandler = new softErrorHandler($board->getBoardHead('Error!'), $board->getBoardFooter(), $board->getConfigValue('STATIC_INDEX_FILE'), $templateEngine);
$loginSessionHandler	= new loginSessionHandler($config['STAFF_LOGIN_TIMEOUT']);
$authenticationHandler	= new authenticationHandler();

$adminLoginController	= new adminLoginController(
	$actionLoggerService,
	$accountRepository,
	$loginSessionHandler,
	$authenticationHandler,
	$softErrorHandler
);


// ───────────────────────────────────────
// Session & Validation
// ───────────────────────────────────────
$staffAccountFromSession		= new staffAccountFromSession;

$IPValidator		= new IPValidator($config, new IPAddress);
$postValidator		= new postValidator($board, $config, $IPValidator, $threadRepository, $softErrorHandler, $threadService, $postService, $attachmentService, $FileIO);

$overboard			= new overboard(
		$board, 
		$config, 
		$softErrorHandler, 
		$threadRepository, 
		$boardService, 
		$postRepository, 
		$postService, 
		$quoteLinkService, 
		$threadService,
		$postSearchService,
		$attachmentService,
		$actionLoggerService,
		$postRedirectService,
		$transactionManager,
		$moduleEngine, 
		$templateEngine
	);

// ───────────────────────────────────────
// DI containers
// ───────────────────────────────────────
$routeDiContainer = new routeDiContainer(
	$board,
	$config,
	$moduleEngine,
	$templateEngine,
	$adminTemplateEngine,
	$overboard,
	$adminPageRenderer,
	$softErrorHandler,
	$boardRepository,
	$boardService,
	$FileIO,
	$postRepository,
	$postService,
	$threadRepository,
	$threadService,
	$accountRepository,
	$accountService,
	$actionLoggerRepository,
	$actionLoggerService,
	$adminLoginController,
	$staffAccountFromSession,
	$postValidator,
	$transactionManager,
	$postRedirectService,
	$databaseConnection,
	$boardPathService,
	$attachmentService,
	$visibleBoards,
	$boardList,
	$quoteLinkRepository,
	$quoteLinkService
);

// ───────────────────────────────────────
// Main Handler Execution
// ───────────────────────────────────────
try {
	$modeHandler = new modeHandler($routeDiContainer);

	$modeHandler->validateBoard($board);
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
