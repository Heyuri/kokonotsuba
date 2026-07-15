<?php

namespace Kokonotsuba\libraries\html;

use Kokonotsuba\config\configSchema;
use Kokonotsuba\config\configService;
use Kokonotsuba\template\templateEngine;

use function Kokonotsuba\libraries\_T;
use function Puchiko\strings\sanitizeStr;

use const Kokonotsuba\GLOBAL_BOARD_UID;

/**
 * Render the configuration editor form for a single board.
 *
 * Fields are prefilled with the board's effective value and marked with a * when the board
 * overrides what it inherits from the global config.
 *
 * @param templateEngine $tpl           Admin template engine (supplies the config editor blocks).
 * @param configService  $configService Supplies the effective and inherited values.
 * @param int            $boardUid      Board being edited.
 * @param string         $liveIndexFile Front-controller filename (form action target).
 * @param string         $csrfInput     Pre-rendered hidden CSRF input.
 * @param string         $notice        Optional message shown above the fields (e.g. after a save).
 * @return string The complete <form> HTML.
 */
function drawBoardConfigForm(templateEngine $tpl, configService $configService, int $boardUid, string $liveIndexFile, string $csrfInput, string $notice = ''): string {
	$groupsHtml = renderConfigGroups(
		$tpl,
		$configService->getEffectiveValues($boardUid),
		$configService->getInheritedValues($boardUid)
	);

	if ($groupsHtml === '') {
		return '<p>No configurable settings are defined.</p>';
	}

	return $tpl->ParseBlock('BOARD_CONFIG_FORM', configFormMessages() + [
		'{$LIVE_INDEX_FILE}' => sanitizeStr($liveIndexFile),
		'{$BOARD_UID}'       => $boardUid,
		'{$CSRF_INPUT}'      => $csrfInput,
		'{$CONFIG_NOTICE}'   => renderConfigNotice($tpl, $notice),
		'{$CONFIG_GROUPS}'   => $groupsHtml,
	]);
}

/**
 * Translated strings the editor's JS needs (it can't call _T itself), handed to it as data
 * attributes on the form.
 *
 * @return array<string, string> Placeholder => translated text.
 */
function configFormMessages(): array {
	return [
		'{$MSG_NO_CHANGES}'     => sanitizeStr(_T('config_no_changes')),
		'{$MSG_SAVE_FAILED}'    => sanitizeStr(_T('config_save_failed')),
		'{$MSG_CONFIRM_SAVE}'   => sanitizeStr(_T('config_confirm_save')),
		'{$MSG_CONFIRM_MORE}'   => sanitizeStr(_T('config_confirm_more')),
		'{$MSG_CONFIRM_APPLY}'  => sanitizeStr(_T('config_confirm_apply')),
		'{$MSG_CONFIRM_CANCEL}' => sanitizeStr(_T('config_confirm_cancel')),
		'{$MSG_COL_SETTING}'    => sanitizeStr(_T('config_confirm_col_setting')),
		'{$MSG_COL_FROM}'       => sanitizeStr(_T('config_confirm_col_from')),
		'{$MSG_COL_TO}'         => sanitizeStr(_T('config_confirm_col_to')),
		'{$MSG_EMPTY_VALUE}'    => sanitizeStr(_T('config_confirm_empty_value')),
		'{$MSG_ENTRIES}'        => sanitizeStr(_T('config_confirm_entries')),
	];
}

/**
 * Render the optional notice shown above the fields, or '' when there's nothing to say.
 *
 * @param templateEngine $tpl    Admin template engine.
 * @param string         $notice Message text.
 * @return string Notice HTML.
 */
function renderConfigNotice(templateEngine $tpl, string $notice): string {
	if ($notice === '') {
		return '';
	}

	return $tpl->ParseBlock('CONFIG_NOTICE', ['{$CONFIG_NOTICE_TEXT}' => sanitizeStr($notice)]);
}

/**
 * Render the configuration editor form for the global config — the overrides that apply to every
 * board. Identical to the board form except for its wording and where it posts; fields are
 * prefilled with the global value and marked with a * where it overrides the schema default.
 *
 * @param templateEngine $tpl           Admin template engine.
 * @param configService  $configService Supplies the global and default values.
 * @param string         $liveIndexFile Front-controller filename (form action target).
 * @param string         $csrfInput     Pre-rendered hidden CSRF input.
 * @param string         $notice        Optional message shown above the fields (e.g. after a save).
 * @return string The complete <form> HTML.
 */
function drawGlobalConfigForm(templateEngine $tpl, configService $configService, string $liveIndexFile, string $csrfInput, string $notice = ''): string {
	$groupsHtml = renderConfigGroups(
		$tpl,
		$configService->getEffectiveValues(GLOBAL_BOARD_UID),
		$configService->getInheritedValues(GLOBAL_BOARD_UID)
	);

	if ($groupsHtml === '') {
		return '<p>No configurable settings are defined.</p>';
	}

	return $tpl->ParseBlock('GLOBAL_CONFIG_FORM', configFormMessages() + [
		'{$LIVE_INDEX_FILE}' => sanitizeStr($liveIndexFile),
		'{$CSRF_INPUT}'      => $csrfInput,
		'{$CONFIG_NOTICE}'   => renderConfigNotice($tpl, $notice),
		'{$CONFIG_GROUPS}'   => $groupsHtml,
	]);
}

/**
 * Render every schema group as a fieldset of field rows. Shared by both scopes: the only
 * difference between the board and global editors is which values they pass in.
 *
 * All markup lives in the templates/admin/config/ blocks; this only supplies their values.
 *
 * @param templateEngine $tpl       Admin template engine.
 * @param array          $values    dot-path => the value to prefill each field with.
 * @param array          $inherited dot-path => the value this scope would show if it overrode
 *                                  nothing; a field differing from it is marked as overridden.
 * @return string The fieldsets' HTML ('' if the schema declares no fields).
 */
function renderConfigGroups(templateEngine $tpl, array $values, array $inherited): string {
	$groupLabels = configSchema::getGroupLabels();
	$groupsHtml = '';

	foreach (configSchema::getGroups() as $groupName => $fields) {
		if (empty($fields)) {
			continue;
		}

		// Emit a sub-header row each time the owning module changes, so module-contributed
		// settings are visually grouped under their module name within the thematic group.
		$rowsHtml = '';
		$currentModule = '';

		foreach ($fields as $dotpath => $meta) {
			$moduleLabel = $meta['module'] ?? '';
			if ($moduleLabel !== '' && $moduleLabel !== $currentModule) {
				$rowsHtml .= $tpl->ParseBlock('CONFIG_MODULE_HEADER', [
					'{$MODULE_LABEL}' => sanitizeStr($moduleLabel),
				]);
				$currentModule = $moduleLabel;
			}

			$dotpath = (string)$dotpath;
			$rowsHtml .= renderConfigFieldRow(
				$tpl,
				$dotpath,
				$meta,
				$values[$dotpath] ?? $meta['default'],
				$inherited[$dotpath] ?? $meta['default']
			);
		}

		$groupsHtml .= $tpl->ParseBlock('CONFIG_GROUP', [
			'{$GROUP_LABEL}' => sanitizeStr($groupLabels[$groupName] ?? $groupName),
			'{$CONFIG_ROWS}' => $rowsHtml,
		]);
	}

	return $groupsHtml;
}

/**
 * Render a single field row (label cell + input cell) for the config form.
 *
 * A field's 'label' and 'desc' are language keys (config_label_{dot-path} / config_desc_{dot-path});
 * they are translated here, at render time, rather than when the schema loads - the schema is
 * loaded during bootstrap, before the language loader is ready.
 *
 * @param templateEngine $tpl       Admin template engine.
 * @param string         $dotpath   Config dot-path key.
 * @param array          $meta      Normalized schema metadata (default/type/label/desc/min).
 * @param mixed          $current   Value to prefill the input with.
 * @param mixed          $inherited Value this scope would show if it overrode nothing.
 * @return string Table row HTML.
 */
function renderConfigFieldRow(templateEngine $tpl, string $dotpath, array $meta, mixed $current, mixed $inherited): string {
	$isOverridden = json_encode($current) !== json_encode($inherited);

	$inputKey = configSchema::inputKey($dotpath);

	$descKey = $meta['desc'];
	$descHtml = $descKey !== ''
		? $tpl->ParseBlock('CONFIG_FIELD_DESC', ['{$FIELD_DESC_TEXT}' => sanitizeStr(_T($descKey))])
		: '';

	return $tpl->ParseBlock('CONFIG_ROW', [
		'{$ROW_CLASS}'        => $isOverridden ? 'configRowOverridden' : '',
		'{$FIELD_ID}'         => sanitizeStr($inputKey),
		'{$FIELD_LABEL}'      => sanitizeStr(_T($meta['label'])),
		'{$FIELD_KEY}'        => sanitizeStr($dotpath),
		'{$OVERRIDE_MARKER}'  => $isOverridden ? $tpl->ParseBlock('CONFIG_OVERRIDE_MARKER', []) : '',
		'{$FIELD_INPUT}'      => renderConfigInput($tpl, $meta, $inputKey, $current),
		'{$FIELD_DESC}'       => $descHtml,
	]);
}

/**
 * Render the input control for a config field based on its type.
 *
 * @param templateEngine $tpl      Admin template engine.
 * @param array          $meta     Normalized schema metadata (type, and an int field's 'min' bound).
 * @param string         $inputKey camelCase form key; used as both the id and the name.
 * @param mixed          $current  Current effective value.
 * @return string Input HTML.
 */
function renderConfigInput(templateEngine $tpl, array $meta, string $inputKey, mixed $current): string {
	$values = [
		'{$FIELD_ID}'   => sanitizeStr($inputKey),
		'{$FIELD_NAME}' => sanitizeStr('config[' . $inputKey . ']'),
	];

	switch ($meta['type']) {
		case configSchema::TYPE_BOOL:
			return $tpl->ParseBlock('CONFIG_INPUT_BOOL', $values + [
				'{$CHECKED}' => $current ? 'checked' : '',
			]);

		case configSchema::TYPE_INT:
			$min = $meta['min'] ?? configSchema::DEFAULT_INT_MIN;
			return $tpl->ParseBlock('CONFIG_INPUT_INT', $values + [
				'{$MIN_ATTR}'    => $min === null ? '' : 'min="' . (int)$min . '"',
				'{$FIELD_VALUE}' => (int)$current,
			]);

		case configSchema::TYPE_ARRAY:
			$arr = is_array($current) ? $current : [];
			$shape = configArrayShape($arr);

			// Deeply-nested arrays (values that are themselves arrays) fall back to a JSON textarea.
			if ($shape === 'nested') {
				$json = json_encode($arr, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
				return $tpl->ParseBlock('CONFIG_INPUT_JSON', $values + [
					'{$FIELD_VALUE}' => sanitizeStr((string)$json),
				]);
			}

			return renderArrayListEditor($tpl, $values, $arr, $shape);

		case configSchema::TYPE_TEXT:
			return $tpl->ParseBlock('CONFIG_INPUT_TEXT', $values + [
				'{$FIELD_VALUE}' => sanitizeStr((string)$current),
			]);

		case configSchema::TYPE_TEMPLATE:
			$options = '';
			foreach (listTemplateDirectories((string)$current) as $dir) {
				$options .= $tpl->ParseBlock('CONFIG_TEMPLATE_OPTION', [
					'{$OPTION_VALUE}' => sanitizeStr($dir),
					'{$SELECTED}'     => $dir === (string)$current ? 'selected' : '',
				]);
			}
			return $tpl->ParseBlock('CONFIG_INPUT_TEMPLATE', $values + [
				'{$TEMPLATE_OPTIONS}' => $options,
			]);

		case configSchema::TYPE_STRING:
		default:
			return $tpl->ParseBlock('CONFIG_INPUT_STRING', $values + [
				'{$FIELD_VALUE}' => sanitizeStr((string)$current),
			]);
	}
}

/**
 * List the selectable template directories under templates/, for template-type config fields.
 *
 * Only board-page template sets — the directories named with the "koko" prefix — are offered;
 * the others (admin, global, ...) are support templates that aren't valid choices for a board.
 * The current value is always included (even if it isn't a prefixed directory under templates/,
 * e.g. a module-provided template name) so a saved selection is never dropped from the dropdown.
 *
 * @param string $current The field's current value, guaranteed to appear in the list.
 * @return string[] Sorted directory names.
 */
function listTemplateDirectories(string $current = ''): array {
	$dir = getBackendDir() . 'templates/';
	$dirs = [];

	if (is_dir($dir)) {
		foreach (scandir($dir) ?: [] as $entry) {
			if ($entry === '.' || $entry === '..' || !str_starts_with($entry, 'koko')) {
				continue;
			}
			if (is_dir($dir . $entry)) {
				$dirs[] = $entry;
			}
		}
	}

	sort($dirs, SORT_STRING);

	if ($current !== '' && !in_array($current, $dirs, true)) {
		array_unshift($dirs, $current);
	}

	return $dirs;
}

/**
 * Classify an array value for editor rendering.
 *
 * @param array $value The array value.
 * @return string 'nested' if any element is itself an array; 'list' for a sequential list;
 *                'map' for a string-keyed map.
 */
function configArrayShape(array $value): string {
	foreach ($value as $v) {
		if (is_array($v)) {
			return 'nested';
		}
	}

	return array_is_list($value) ? 'list' : 'map';
}

/**
 * Render the interactive list editor for a flat array config field.
 *
 * Existing entries are editable rows (a value input, plus a key input for maps) with a delete (x)
 * button; a trailing "add" row appends new entries. boardConfigForm.js keeps a hidden JSON input
 * (the one that actually submits, under the field name) in sync as rows are edited, added, or
 * removed, so entries are persisted by the form's own save button rather than per-row. The server
 * side is unchanged (it still receives and decodes a JSON string).
 *
 * @param templateEngine $tpl    Admin template engine.
 * @param array          $values Field id/name placeholder values shared with the other inputs.
 * @param array          $arr    Current array value.
 * @param string         $shape  'list' or 'map'.
 * @return string Editor HTML.
 */
function renderArrayListEditor(templateEngine $tpl, array $values, array $arr, string $shape): string {
	$isMap = $shape === 'map';

	$rows = '';
	foreach ($arr as $key => $value) {
		$rows .= $tpl->ParseBlock('CONFIG_ARRAY_ROW', [
			'{$ARRAY_KEY_INPUT}' => $isMap
				? $tpl->ParseBlock('CONFIG_ARRAY_KEY_INPUT', ['{$ARRAY_KEY}' => sanitizeStr((string)$key)])
				: '',
			'{$ARRAY_VALUE}' => sanitizeStr((string)$value),
		]);
	}

	$json = json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

	return $tpl->ParseBlock('CONFIG_ARRAY_EDITOR', $values + [
		'{$ARRAY_MODE}'                  => $isMap ? 'map' : 'list',
		'{$ARRAY_ROWS}'                  => $rows,
		'{$ARRAY_NEW_KEY_INPUT}'         => $isMap ? $tpl->ParseBlock('CONFIG_ARRAY_NEW_KEY_INPUT', []) : '',
		'{$ARRAY_NEW_VALUE_PLACEHOLDER}' => $isMap ? 'value' : 'new entry',
		'{$ARRAY_JSON}'                  => sanitizeStr((string)$json),
	]);
}
