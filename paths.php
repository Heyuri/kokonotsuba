<?php
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
#		throw new Exception("Configuration file not found: " . $configFile);
		throw new Exception("Configuration file not found: " . $configFile);
	}

	require $configFile;
	
	// Ensure $config is set in the included file
	if (!isset($config)) {
#		throw new Exception("Configuration array $config is not defined in: " . $configFile);
		throw new Exception("Configuration array \$config is not defined in: " . $configFile);
	}
	
	return $config;
}

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
