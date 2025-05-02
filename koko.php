<?php
/*

YOU MUST GIVE CREDIT TO WWW.HEYURI.NET ON YOUR BBS IF YOU ARE PLANNING TO USE THIS SOFTWARE.

*/
session_start();

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


function generateNewBoardConfigFile() {
	$templateConfigPath = getBoardConfigDir().'board-template.php';//config template
	$newConfigFileName = 'board-'.generateUid().'.php';
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

function getBoardFromBootstrapFile() {
	$boardIO = boardIO::getInstance();
	$boardUIDIni = parse_ini_file('./boardUID.ini', true);
	
	//run some checks
	if(!$boardUIDIni) die("There was an error parsing boardUID.ini");
	if(!isset($boardUIDIni['board_uid'])) die("Board UID value in the ini file is not be set or is null. Check boardUID.ini to ensure it has a valid board UID.");
	
	$boardUID = $boardUIDIni['board_uid'];

	$board = $boardIO->getBoardByUID($boardUID);
	if(!$board) die("Board ($boardUID) not found in database. Contact the Administrator if you believe this is a mistake."); //board was not found
	return $board;
}


/*────────────────────────────────────────────────────────────
	The main judgment of the functions of the program
────────────────────────────────────────────────────────────*/

//Check if this is the backend
if(file_exists('.backend')) die("You are trying to access the instance's backend");


// ───────────────────────────────────────
// Database Setup
// ───────────────────────────────────────
$dbSettings = getDatabaseSettings();

DatabaseConnection::createInstance($dbSettings);
boardIO::createInstance($dbSettings);
AccountIO::createInstance($dbSettings);
ActionLogger::createInstance($dbSettings);
postRedirectIO::createInstance($dbSettings);
threadCacheSingleton::createInstance($dbSettings);

// ───────────────────────────────────────
// Board Bootstrap
// ───────────────────────────────────────
$board = getBoardFromBootstrapFile();

PMCLibrary::createFileIOInstance($board);
PIOPDO::createInstance($dbSettings);
threadSingleton::createInstance($dbSettings);
boardPathCachingIO::createInstance($dbSettings);

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
$globalHTML			= new globalHTML($board);

$moduleEngine		= new moduleEngine($board);
$templateEngine		= $board->getBoardTemplateEngine();

$adminTemplateEngine = new templateEngine(getBackendDir() . 'templates/admin.tpl', [
	'config'	=> $config,
	'boardData'	=> [
		'title'		=> $board->getBoardTitle(),
		'subtitle'	=> $board->getBoardSubTitle()
	]
]);

$pageRenderer		= new pageRenderer($templateEngine, $globalHTML);
$adminPageRenderer	= new pageRenderer($adminTemplateEngine, $globalHTML);

$overboard			= new overboard($config, $moduleEngine, $templateEngine);

// ───────────────────────────────────────
// IO / Core Systems
// ───────────────────────────────────────
$boardIO			= boardIO::getInstance();
$FileIO				= PMCLibrary::getFileIOInstance();
$PIO				= PIOPDO::getInstance();
$threadSingleton	= threadSingleton::getInstance();
$AccountIO			= AccountIO::getInstance();
$actionLogger		= ActionLogger::getInstance();

// ───────────────────────────────────────
// Error Handling & Authentication
// ───────────────────────────────────────
$softErrorHandler	= new softErrorHandler($board);

$loginSessionHandler	= new loginSessionHandler($config['STAFF_LOGIN_TIMEOUT']);
$authenticationHandler	= new authenticationHandler();

$adminLoginController	= new adminLoginController(
	$actionLogger,
	$AccountIO,
	$globalHTML,
	$loginSessionHandler,
	$authenticationHandler
);

// ───────────────────────────────────────
// Session & Validation
// ───────────────────────────────────────
$staffSession		= new staffAccountFromSession;

$IPValidator		= new IPValidator($config, new IPAddress);
$postValidator		= new postValidator($board, $config, $globalHTML, $IPValidator, $threadSingleton);

// ───────────────────────────────────────
// Main Handler Execution
// ───────────────────────────────────────
try {
	$modeHandler = new modeHandler(
		$board,
		$globalHTML,
		$moduleEngine,
		$templateEngine,
		$adminTemplateEngine,
		$overboard,
		$pageRenderer,
		$adminPageRenderer,
		$softErrorHandler,
		$boardIO,
		$FileIO,
		$PIO,
		$threadSingleton,
		$AccountIO,
		$actionLogger,
		$adminLoginController,
		$staffSession,
		$postValidator
	);

	$modeHandler->handle();

} catch (\Throwable $e) {
	$globalConfig	= getGlobalConfig();
	$globalHTML		= new globalHTML($board);

	PMCLibrary::getLoggerInstance($globalConfig['ERROR_HANDLER_FILE'], 'Global')
		->error($e->getMessage());

	$globalHTML->error("There has been an error. (;´Д`)");
}

clearstatcache();
