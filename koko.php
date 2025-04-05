<?php
/*

YOU MUST GIVE CREDIT TO WWW.HEYURI.NET ON YOUR BBS IF YOU ARE PLANNING TO USE THIS SOFTWARE.

*/
session_start();

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

postRedirectIO::createInstance($dbSettings);

$board = getBoardFromBootstrapFile();

//board-related singletons
PMCLibrary::createFileIOInstance($board);
PIOPDO::createInstance($dbSettings);
boardPathCachingIO::createInstance($dbSettings);

try {
	//handle user requests and gzip compression on request
	$modeHandler = new modeHandler($board);
	$modeHandler->handle();
} catch(\Throwable $e) {
	$globalConfig = getGlobalConfig();
	$globalHTML = new globalHTML($board);

	PMCLibrary::getLoggerInstance($globalConfig['ERROR_HANDLER_FILE'],'Global')->error($e->getMessage());	
	$globalHTML->error("There has been an error. (;´Д`)");
}
clearstatcache();
