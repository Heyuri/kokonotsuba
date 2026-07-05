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

		// Emit a sub-header row each time the owning module changes, so module-contributed
		// settings are visually grouped under their module name within the thematic group.
		$currentModule = '';
		foreach ($fields as $dotpath => $meta) {
			$moduleLabel = $meta['module'] ?? '';
			if ($moduleLabel !== '' && $moduleLabel !== $currentModule) {
				$html .= "<tr class=\"config-module-header\"><td class=\"postblock\" colspan=\"2\">" . sanitizeStr($moduleLabel) . "</td></tr>";
				$currentModule = $moduleLabel;
			}
			$html .= renderConfigFieldRow((string)$dotpath, $meta, $board);
		}

		$html .= "\t\t\t</tbody>\t\t</table>";
		$html .= "\t</fieldset>";
	}

	$html .= "\t<div class=\"buttonSection\">";
	$html .= "\t\t<button type=\"submit\" id=\"board-config-save-button\">Save configuration</button>";
	$html .= "\t</div>";
	$html .= "</form>";

	// Inline CSS/JS for the interactive array list editors (rendered once after the form).
	$html .= configArrayEditorAssets();

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
			$arr = is_array($current) ? $current : [];
			$shape = configArrayShape($arr);
			if ($shape === 'nested') {
				// Deeply-nested arrays (values that are themselves arrays) fall back to a JSON textarea.
				$json = json_encode($arr, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
				return "<textarea class=\"config-json\" id=\"{$id}\" name=\"{$name}\" rows=\"6\" spellcheck=\"false\">" . sanitizeStr((string)$json) . "</textarea>";
			}
			return renderArrayListEditor($id, $name, $arr, $shape);

		case configSchema::TYPE_TEXT:
			return "<textarea class=\"config-text\" id=\"{$id}\" name=\"{$name}\" rows=\"4\">" . sanitizeStr((string)$current) . "</textarea>";

		case configSchema::TYPE_TEMPLATE:
			$options = '';
			foreach (listTemplateDirectories((string)$current) as $dir) {
				$selected = $dir === (string)$current ? ' selected' : '';
				$options .= "<option value=\"" . sanitizeStr($dir) . "\"{$selected}>" . sanitizeStr($dir) . "</option>";
			}
			return "<select class=\"config-template\" id=\"{$id}\" name=\"{$name}\">{$options}</select>";

		case configSchema::TYPE_STRING:
		default:
			$value = sanitizeStr((string)$current);
			return "<input type=\"text\" class=\"inputtext\" id=\"{$id}\" name=\"{$name}\" value=\"{$value}\">";
	}
}

/**
 * List the selectable template directories under templates/, for template-type config fields.
 * The current value is always included (even if it isn't a directory under templates/, e.g. a
 * module-provided template name) so a saved selection is never dropped from the dropdown.
 *
 * @param string $current The field's current value, guaranteed to appear in the list.
 * @return string[] Sorted directory names.
 */
function listTemplateDirectories(string $current = ''): array {
	$dir = getBackendDir() . 'templates/';
	$dirs = [];

	if (is_dir($dir)) {
		foreach (scandir($dir) ?: [] as $entry) {
			if ($entry === '.' || $entry === '..') {
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
 * Existing entries are editable rows (a value input, plus a key input for maps) each with a
 * save (+) and delete (x) button; a trailing "add" row appends new entries. JS keeps a hidden
 * JSON input (the one that actually submits, under $name) in sync, so the server side is
 * unchanged (it still receives and decodes a JSON string).
 *
 * @param string $id    Escaped HTML id for the hidden input.
 * @param string $name  Escaped HTML name for the hidden input (config[<inputKey>]).
 * @param array  $arr   Current array value.
 * @param string $shape 'list' or 'map'.
 * @return string Editor HTML.
 */
function renderArrayListEditor(string $id, string $name, array $arr, string $shape): string {
	$isMap = $shape === 'map';

	$rows = '';
	foreach ($arr as $k => $v) {
		$rows .= renderArrayEditorRow($isMap, (string)$k, (string)$v);
	}

	$json = json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

	$newKey = $isMap ? '<input type="text" class="configArrayNewKey" placeholder="key">' : '';
	$newValuePlaceholder = $isMap ? 'value' : 'new entry';

	$html  = '<div class="configArrayEditor" data-mode="' . ($isMap ? 'map' : 'list') . '">';
	$html .= '<ul class="configArrayList">' . $rows . '</ul>';
	$html .= '<div class="configArrayAddRow">' . $newKey
		. '<input type="text" class="configArrayNewValue" placeholder="' . $newValuePlaceholder . '">'
		. '<button type="button" class="configArrayAddBtn" title="Add entry">+</button>'
		. '</div>';
	$html .= '<input type="hidden" id="' . $id . '" name="' . $name . '" class="configArrayJson" value="' . sanitizeStr((string)$json) . '">';
	$html .= '</div>';

	return $html;
}

/**
 * Render a single editable entry row for the array list editor.
 *
 * @param bool   $isMap Whether the array is a key => value map (adds a key input).
 * @param string $key   Entry key (maps only).
 * @param string $value Entry value.
 * @return string Row HTML.
 */
function renderArrayEditorRow(bool $isMap, string $key, string $value): string {
	$keyInput = $isMap
		? '<input type="text" class="configArrayKey" value="' . sanitizeStr($key) . '">'
		: '';

	return '<li class="configArrayRow">' . $keyInput
		. '<input type="text" class="configArrayValue" value="' . sanitizeStr($value) . '">'
		. '<button type="button" class="configArraySave" title="Save entry">+</button>'
		. '<button type="button" class="configArrayRemove" title="Delete entry">x</button>'
		. '</li>';
}

/**
 * Inline CSS + JS powering the array list editors. Included once per rendered config form.
 *
 * @return string A <style> and <script> block.
 */
function configArrayEditorAssets(): string {
	$css = '<style>'
		. '.configArrayList{list-style:none;margin:0;padding:0;}'
		. '.configArrayRow,.configArrayAddRow{display:flex;gap:4px;margin-bottom:3px;align-items:center;}'
		. '.configArrayRow input,.configArrayAddRow input{flex:1 1 auto;min-width:0;}'
		. '.configArrayKey,.configArrayNewKey{flex:0 0 32%;}'
		. '.configArrayEditor button{flex:0 0 auto;cursor:pointer;padding:0 8px;line-height:1.6;}'
		. '.configArrayAddRow{margin-top:4px;}'
		. '.configArraySaved input{background:#d8f5d8;}'
		. '</style>';

	$js = <<<'JS'
<script>
(function(){
	var form = document.getElementById('boardConfigForm');
	if (!form || form.dataset.arrayEditorInit) return;
	form.dataset.arrayEditorInit = '1';

	function serialize(editor){
		var map = editor.dataset.mode === 'map';
		var out = map ? {} : [];
		editor.querySelectorAll('.configArrayList > .configArrayRow').forEach(function(row){
			var v = row.querySelector('.configArrayValue').value;
			if (map){
				var k = row.querySelector('.configArrayKey').value;
				if (k === '') return;
				out[k] = v;
			} else {
				if (v === '') return;
				out.push(v);
			}
		});
		editor.querySelector('.configArrayJson').value = JSON.stringify(out);
	}

	function addRow(editor){
		var map = editor.dataset.mode === 'map';
		var li = document.createElement('li');
		li.className = 'configArrayRow';
		if (map){
			var nk = editor.querySelector('.configArrayNewKey');
			var ki = document.createElement('input');
			ki.type = 'text'; ki.className = 'configArrayKey';
			ki.value = nk ? nk.value : '';
			li.appendChild(ki);
			if (nk) nk.value = '';
		}
		var nv = editor.querySelector('.configArrayNewValue');
		var vi = document.createElement('input');
		vi.type = 'text'; vi.className = 'configArrayValue';
		vi.value = nv ? nv.value : '';
		li.appendChild(vi);
		if (nv) nv.value = '';
		['configArraySave:+:Save entry','configArrayRemove:x:Delete entry'].forEach(function(spec){
			var p = spec.split(':');
			var b = document.createElement('button');
			b.type = 'button'; b.className = p[0]; b.textContent = p[1]; b.title = p[2];
			li.appendChild(b);
		});
		editor.querySelector('.configArrayList').appendChild(li);
		serialize(editor);
	}

	form.addEventListener('click', function(e){
		var btn = e.target.closest ? e.target.closest('button') : null;
		if (!btn) return;
		var editor = btn.closest('.configArrayEditor');
		if (!editor) return;
		if (btn.classList.contains('configArrayAddBtn')){
			e.preventDefault(); addRow(editor);
		} else if (btn.classList.contains('configArrayRemove')){
			e.preventDefault(); btn.closest('.configArrayRow').remove(); serialize(editor);
		} else if (btn.classList.contains('configArraySave')){
			e.preventDefault();
			serialize(editor);
			var row = btn.closest('.configArrayRow');
			row.classList.add('configArraySaved');
			setTimeout(function(){ row.classList.remove('configArraySaved'); }, 350);
		}
	});

	form.addEventListener('input', function(e){
		var editor = e.target.closest ? e.target.closest('.configArrayEditor') : null;
		if (editor && (e.target.classList.contains('configArrayValue') || e.target.classList.contains('configArrayKey'))){
			serialize(editor);
		}
	});

	form.addEventListener('keydown', function(e){
		if (e.key !== 'Enter') return;
		if (e.target.classList.contains('configArrayNewKey') || e.target.classList.contains('configArrayNewValue')){
			e.preventDefault();
			var editor = e.target.closest('.configArrayEditor');
			if (editor) addRow(editor);
		}
	});

	form.addEventListener('submit', function(){
		form.querySelectorAll('.configArrayEditor').forEach(serialize);
	});
})();
</script>
JS;

	return $css . $js;
}
