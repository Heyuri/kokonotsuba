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
	return getBackendDir().'configs/';
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

/** Application root, honouring the KOKONOTSUBA_APPROOT override used by CLI entry points. */
function getAppRoot(): string {
	return rtrim((string)(getenv('KOKONOTSUBA_APPROOT') ?: __DIR__), '/');
}

/**
 * Canonical logical-name => real-name table map. Fixed by the schema, never by configuration.
 *
 * @return array<string, string>
 */
function getTableNames(): array {
	static $tableNames = null;

	if ($tableNames === null) {
		$tableNames = require getAppRoot().'/tables.php';
		if (!is_array($tableNames) || $tableNames === []) {
			die('Could not read tables.php.');
		}
	}

	return $tableNames;
}

/**
 * The real name of one table, by its logical key.
 *
 * This is how application code should reach a table name. Module classes have the same lookup
 * on their moduleContext; this is for the places that have no context (module *Lib.php
 * factories, background tasks, CLI utilities).
 */
function getTableName(string $key): string {
	$tableNames = getTableNames();

	if (!isset($tableNames[$key])) {
		throw new InvalidArgumentException("Unknown table key: {$key}. Add it to tables.php.");
	}

	return $tableNames[$key];
}

/**
 * Database credentials only. Table names are not here — see getTableName()/getTableNames().
 *
 * A databaseSettings.php written before table names moved out still carries its own copy of
 * them. Identical values are ignored, but a genuinely renamed table is fatal: silently reading
 * the wrong table would be worse than refusing to start.
 */
function getDatabaseSettings() {
	static $settings = null;

	if ($settings !== null) {
		return $settings;
	}

	$credentials = require getAppRoot().'/databaseSettings.php';
	if (empty($credentials)) {
		die('Could not read database settings.');
	}

	$renamed = [];
	foreach (getTableNames() as $key => $name) {
		if (isset($credentials[$key]) && $credentials[$key] !== $name) {
			$renamed[] = "{$key}: databaseSettings.php says '{$credentials[$key]}', tables.php says '{$name}'";
		}
	}

	if ($renamed !== []) {
		die(
			"Table names have moved from databaseSettings.php into tables.php and are no longer configurable.\n"
			."This install renamed:\n  ".implode("\n  ", $renamed)."\n"
			."Rename the tables in the database to match tables.php, then delete the table entries from databaseSettings.php.\n"
		);
	}

	return $settings = $credentials;
}

function getGlobalConfig() {
	return require __DIR__.'/global/globalconfig.php';
}

function getGlobalAttachmentDirectory(): string {
	$globalDir = getBackendGlobalDir();

	$globalAttachmentDirectory = $globalDir . 'attachments/';

	return $globalAttachmentDirectory;
}