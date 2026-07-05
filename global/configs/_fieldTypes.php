<?php
/**
 * Shared field-builder helpers for the board config schema files.
 *
 * Each config file `require_once`s this and uses these to declare fields concisely instead of
 * repeating the ['default' => ..., 'type' => ..., 'label' => ..., 'desc' => ...] shape:
 *
 *   use function Kokonotsuba\config\fields\{boolField, intField, stringField, textField, arrayField};
 *   return [
 *       '_group'      => 'Posting',
 *       'ALWAYS_NOKO' => boolField('Always noko', false, 'Redirect to the reply by default.'),
 *   ];
 *
 * Files beginning with "_" are skipped by the schema loader, so this is never treated as a group.
 */

namespace Kokonotsuba\config\fields;

/** Build a boolean (checkbox) field definition. */
function boolField(string $label, bool $default, string $desc = ''): array {
	return ['default' => $default, 'type' => 'bool', 'label' => $label, 'desc' => $desc];
}

/** Build an integer (number input) field definition. */
function intField(string $label, int $default, string $desc = ''): array {
	return ['default' => $default, 'type' => 'int', 'label' => $label, 'desc' => $desc];
}

/** Build a single-line string (text input) field definition. */
function stringField(string $label, string $default, string $desc = ''): array {
	return ['default' => $default, 'type' => 'string', 'label' => $label, 'desc' => $desc];
}

/** Build a multi-line text (textarea) field definition. */
function textField(string $label, string $default, string $desc = ''): array {
	return ['default' => $default, 'type' => 'text', 'label' => $label, 'desc' => $desc];
}

/** Build an array (JSON textarea) field definition. */
function arrayField(string $label, array $default, string $desc = ''): array {
	return ['default' => $default, 'type' => 'array', 'label' => $label, 'desc' => $desc];
}

/**
 * Build a template-directory field: a <select> populated with the directories under templates/.
 * Stored as the directory-name string; behaves like a string everywhere except the editor input.
 */
function templateField(string $label, string $default, string $desc = ''): array {
	return ['default' => $default, 'type' => 'template', 'label' => $label, 'desc' => $desc];
}
