<?php

namespace Kokonotsuba\libraries\html;

use Kokonotsuba\board\board;
use Kokonotsuba\config\configSchema;

use function Puchiko\strings\sanitizeStr;

/**
 * Render the per-board configuration editor form.
 *
 * Fields are grouped by their schema file. Each field is prefilled with the board's current
 * effective value (schema default overlaid with any stored override) and rendered with the
 * koko "postblock" label + value table structure. Values equal to the default are shown but
 * only stored as overrides when changed (handled on save).
 *
 * @param board  $board         Board whose effective config supplies the prefilled values.
 * @param string $liveIndexFile Front-controller filename (form action target).
 * @param string $csrfInput     Pre-rendered hidden CSRF input.
 * @return string The complete <form> HTML.
 */
function drawBoardConfigForm(board $board, string $liveIndexFile, string $csrfInput): string {
	$groups = configSchema::getGroups();
	$groupLabels = configSchema::getGroupLabels();

	if (empty($groups)) {
		return '<p>No configurable settings are defined.</p>';
	}

	$boardUid = (int)$board->getBoardUID();

	$html  = '<form id="boardConfigForm" action="' . sanitizeStr($liveIndexFile) . '?mode=handleBoardRequests" method="POST">';
	$html .= "\t<h3>Board configuration</h3>";
	$html .= "\t<p class=\"formItemDescription\">Values are prefilled with this board's current settings. Only settings you change from their default are saved as overrides for this board.</p>";
	$html .= "\t<input type=\"hidden\" name=\"saveBoardConfig\" value=\"{$boardUid}\">";
	$html .= "\t" . $csrfInput;

	foreach ($groups as $groupName => $fields) {
		if (empty($fields)) {
			continue;
		}

		$label = $groupLabels[$groupName] ?? $groupName;

		$html .= "\t<fieldset class=\"board-config-group\">";
		$html .= "\t\t<legend>" . sanitizeStr($label) . "</legend>";
		$html .= "\t\t<table class=\"board-config-table\">\t\t\t<tbody>";

		foreach ($fields as $dotpath => $meta) {
			$html .= renderConfigFieldRow((string)$dotpath, $meta, $board);
		}

		$html .= "\t\t\t</tbody>\t\t</table>";
		$html .= "\t</fieldset>";
	}

	$html .= "\t<div class=\"buttonSection\">";
	$html .= "\t\t<button type=\"submit\" id=\"board-config-save-button\">Save configuration</button>";
	$html .= "\t</div>";
	$html .= "</form>";

	return $html;
}

/**
 * Render a single field row (label cell + input cell) for the config form.
 *
 * @param string $dotpath Config dot-path key.
 * @param array  $meta    Normalized schema metadata (default/type/label/desc).
 * @param board  $board   Board supplying the current effective value.
 * @return string Table row HTML.
 */
function renderConfigFieldRow(string $dotpath, array $meta, board $board): string {
	$type = $meta['type'];
	$default = $meta['default'];
	$label = $meta['label'];
	$desc = $meta['desc'];

	$current = $board->getConfigValue($dotpath, $default);
	$isOverridden = json_encode($current) !== json_encode($default);

	$inputKey = configSchema::inputKey($dotpath);
	$fieldId = $inputKey;
	$fieldName = 'config[' . $inputKey . ']';

	$overrideMarker = $isOverridden
		? ' <span class="config-overridden" title="Overridden for this board">*</span>'
		: '';

	$descHtml = $desc !== ''
		? "\t\t\t\t\t<div class=\"formItemDescription\">" . sanitizeStr($desc) . "</div>"
		: '';

	$rowClass = $isOverridden ? ' class="config-row-overridden"' : '';

	$input = renderConfigInput($type, $fieldId, $fieldName, $current);

	return "<tr{$rowClass}>"
		. "<td class=\"postblock\"><label for=\"" . sanitizeStr($fieldId) . "\">" . sanitizeStr($label) . "</label>{$overrideMarker}<br><code class=\"config-key\">" . sanitizeStr($dotpath) . "</code></td>"
		. "<td>{$input}{$descHtml}</td>"
		. "</tr>";
}

/**
 * Render the input control for a config field based on its type.
 *
 * @param string $type    One of the configSchema::TYPE_* constants.
 * @param string $fieldId HTML id attribute.
 * @param string $fieldName HTML name attribute.
 * @param mixed  $current Current effective value.
 * @return string Input HTML.
 */
function renderConfigInput(string $type, string $fieldId, string $fieldName, mixed $current): string {
	$id = sanitizeStr($fieldId);
	$name = sanitizeStr($fieldName);

	switch ($type) {
		case configSchema::TYPE_BOOL:
			$checked = $current ? ' checked' : '';
			return "<input type=\"checkbox\" id=\"{$id}\" name=\"{$name}\" value=\"1\"{$checked}>";

		case configSchema::TYPE_INT:
			$value = sanitizeStr((string)(int)$current);
			return "<input type=\"number\" step=\"1\" id=\"{$id}\" name=\"{$name}\" value=\"{$value}\">";

		case configSchema::TYPE_ARRAY:
			$json = json_encode(
				is_array($current) ? $current : [],
				JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
			);
			return "<textarea class=\"config-json\" id=\"{$id}\" name=\"{$name}\" rows=\"6\" spellcheck=\"false\">" . sanitizeStr((string)$json) . "</textarea>";

		case configSchema::TYPE_TEXT:
			return "<textarea class=\"config-text\" id=\"{$id}\" name=\"{$name}\" rows=\"4\">" . sanitizeStr((string)$current) . "</textarea>";

		case configSchema::TYPE_STRING:
		default:
			$value = sanitizeStr((string)$current);
			return "<input type=\"text\" class=\"inputtext\" id=\"{$id}\" name=\"{$name}\" value=\"{$value}\">";
	}
}
