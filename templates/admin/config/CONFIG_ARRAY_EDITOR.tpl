<div class="configArrayEditor" data-mode="{$ARRAY_MODE}">
	<ul class="configArrayList">{$ARRAY_ROWS}</ul>
	<div class="configArrayAddRow">
		{$ARRAY_NEW_KEY_INPUT}
		<input type="text" class="configArrayNewValue" placeholder="{$ARRAY_NEW_VALUE_PLACEHOLDER}">
		<button type="button" class="configArrayAddBtn" title="Add entry">+</button>
	</div>
	<input type="hidden" id="{$FIELD_ID}" name="{$FIELD_NAME}" class="configArrayJson" value="{$ARRAY_JSON}">
</div>
