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
 *       'ALWAYS_NOKO' => boolField('Always noko', false, 'config_desc_ALWAYS_NOKO'),
 *   ];
 *
 * $desc is a LANGUAGE KEY, not prose: the description text itself lives in
 * code/Kokonotsuba/lang/en_US.php and is translated by the config editor when it renders the
 * field. The key is 'config_desc_' followed by the setting's config dot-path - so a core field
 * keyed 'ALWAYS_NOKO' uses 'config_desc_ALWAYS_NOKO', and a module field uses its prefixed path,
 * e.g. 'config_desc_modules.ads.ADS_INLINE_COUNT'. A field with no description passes ''.
 *
 * Files beginning with "_" are skipped by the schema loader, so this is never treated as a group.
 */

namespace Kokonotsuba\config\fields;

/** Build a boolean (checkbox) field definition. $desc is a language key - see the file header. */
function boolField(string $label, bool $default, string $desc = ''): array {
	return ['default' => $default, 'type' => 'bool', 'label' => $label, 'desc' => $desc];
}

/**
 * Build an integer (number input) field definition.
 *
 * Integers cannot go below zero unless a field opts out by passing an explicit $min (use
 * null for no lower bound at all, e.g. intField('Static HTML pages', 10, '...', min: -1)).
 * The bound is both rendered as the input's min attribute and enforced when the value is saved.
 */
function intField(string $label, int $default, string $desc = '', ?int $min = 0): array {
	return ['default' => $default, 'type' => 'int', 'label' => $label, 'desc' => $desc, 'min' => $min];
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
