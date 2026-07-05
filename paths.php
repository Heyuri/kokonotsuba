<?php

function getBackendDir() {
	return __DIR__.'/';
}

function getBackendCodeDir() {
	return getBackendDir().'code/Kokonotsuba/';
}

function getBackendGlobalDir() {
	return getBackendDir().'global/';
}

/** Directory holding the board configuration schema files (grouped defaults + metadata). */
function getConfigSchemaDir() {
	return getBackendGlobalDir().'configs/';
}

function getBoardStoragesDir() {
	return getBackendGlobalDir().'board-storages/';
}

/**
 * Return the board-agnostic default config array (immutable globals + editable schema
 * defaults). This is the source of truth for board creation.
 */
function getTemplateConfigArray(): array {
	return \Kokonotsuba\config\configService::resolveDefaults();
}

/* get the database settings from dbSettings PHP file */
function getDatabaseSettings() {
	$dir = rtrim((string)(getenv('KOKONOTSUBA_APPROOT') ?: __DIR__), '/');
	$dbSettings = require $dir.'/databaseSettings.php';
	if(empty($dbSettings)) die("Could not read database settings.");	
	else return $dbSettings;
}

function getGlobalConfig() {
	return require __DIR__.'/global/globalconfig.php';
}

function getGlobalAttachmentDirectory(): string {
	$globalDir = getBackendGlobalDir();

	$globalAttachmentDirectory = $globalDir . 'attachments/';

	return $globalAttachmentDirectory;
}