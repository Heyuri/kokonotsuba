<?php
/*

YOU MUST GIVE CREDIT TO WWW.HEYURI.NET ON YOUR BBS IF YOU ARE PLANNING TO USE THIS SOFTWARE.

*/
session_start();

/* Libraries */
require __DIR__.'/lib/interfaces.php';
require __DIR__.'/lib/lib_simplelogger.php';
require __DIR__.'/lib/lib_loggerinterceptor.php';
require __DIR__.'/lib/lib_admin.php'; // Admin panel functions
require __DIR__.'/lib/lib_template.php'; // Template library
require __DIR__.'/lib/lib_post.php'; // Post and thread functions
require __DIR__.'/lib/lib_pte.php';
require __DIR__.'/lib/lib_pms.php';
require __DIR__.'/lib/lib_pio.php';
require __DIR__.'/lib/lib_pio.cond.php';
require __DIR__.'/lib/lib_common.php'; // Introduce common function archives
require __DIR__.'/lib/pmclibrary.php'; // Ingest libraries
require __DIR__.'/lib/lib_errorhandler.php'; // Introduce global error capture
require __DIR__.'/lib/lib_compatible.php'; // Introduce compatible libraries


/* Caching */
require __DIR__.'/lib/boardPathCachingIO.php';
require __DIR__.'/lib/cachedBoardPath.php';

/* Database singleton */
require __DIR__.'/lib/database.php';

/* Handle soft error pages */
require __DIR__.'/lib/softErrorHandler.php';

/* HTML output */
require __DIR__.'/lib/globalHTML.php';

/* Main output */
require __DIR__.'/lib/modeHandler.php';

/* Overboard */
require __DIR__.'/lib/overboard.php';

/* Post objects and singletons */
require __DIR__.'/lib/postValidator.php';
require __DIR__.'/lib/postSingleton.php';
require __DIR__.'/lib/postRedirectIO.php';
require __DIR__.'/lib/threadRedirect.php';

/* Admin page selector */
require __DIR__.'/lib/adminPageHandler.php';

/* Account Related */
require __DIR__.'/lib/accountIO.php';
require __DIR__.'/lib/accountClass.php';
require __DIR__.'/lib/accountRequestHandler.php';
require __DIR__.'/lib/staffAccountSession.php';
require __DIR__.'/lib/loginHandler.php';
require __DIR__.'/lib/authenticate.php';

/* Action log */
require __DIR__.'/lib/actionClass.php';
require __DIR__.'/lib/actionLoggerSingleton.php';


/* Board classes and singleton */
require __DIR__.'/lib/boardClass.php';
require __DIR__.'/lib/boardSingleton.php';
require __DIR__.'/lib/boardStoredFile.php';

/* IP */
require __DIR__.'/lib/IPAddress.php';
require __DIR__.'/lib/IPValidator.php';

function getBackendDir() {
	return __DIR__.'/';
}

function getBackendGlobalDir() {
	return __DIR__.'/global/';
}

function getBoardConfigDir() {
	return getBackendGlobalDir().'board-configs/';
}

function getBoardStoragesDir() {
	return getBackendGlobalDir().'board-storages/';
}

function getTemplateConfigArray() {
	require getBoardConfigDir().'board-template.php';
	return $config;
}

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
	$dbSettings = getDatabaseSettings();	
	
	$BoardIO = boardIO::getInstance();
	$boardUIDIni = parse_ini_file('./boardUID.ini', true);
	
	//run some checks
	if(!$boardUIDIni) die("There was an error parsing boardUID.ini");
	if(!isset($boardUIDIni['board_uid'])) die("Board UID value in the ini file is not be set or is null. Check boardUID.ini to ensure it has a valid board UID.");
	
	$boardUID = $boardUIDIni['board_uid'];
	return $BoardIO->getBoardByUID($boardUID);
}

//Check if this is the backend
if(file_exists('.backend')) die("You are trying to access the instance's backend");

/*-----------The main judgment of the functions of the program-------------*/
$dbSettings = getDatabaseSettings();
//database singletons
DatabaseConnection::createInstance($dbSettings);
boardIO::createInstance($dbSettings);
AccountIO::createInstance($dbSettings);
ActionLogger::createInstance($dbSettings);
boardPathCachingIO::createInstance($dbSettings);
postRedirectIO::createInstance($dbSettings);

$board = getBoardFromBootstrapFile();

//board-related singletons
PMCLibrary::createFileIOInstance($board);
PMS::createInstance($board);
PTELibrary::createInstance($board);
PIOPDO::createInstance($dbSettings);

try {
	//handle user requests and gzip compression on request
	$modeHandler = new modeHandler($board);
	$modeHandler->handle();
} catch(Exception $e) {
	$globalConfig = getGlobalConfig();
	$globalHTML = new globalHTML($board);

	PMCLibrary::getLoggerInstance($globalConfig['ERROR_HANDLER_FILE'],'Global')->error($e->getMessage());	
	$globalHTML->error("There has been an error. (;´Д`)");
	
}
clearstatcache();
