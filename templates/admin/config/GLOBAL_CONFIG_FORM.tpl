<form id="boardConfigForm" action="{$LIVE_INDEX_FILE}?mode=globalConfig" method="POST" data-msg-no-changes="{$MSG_NO_CHANGES}" data-msg-failed="{$MSG_SAVE_FAILED}" data-msg-confirm="{$MSG_CONFIRM_SAVE}" data-msg-more="{$MSG_CONFIRM_MORE}" data-msg-apply="{$MSG_CONFIRM_APPLY}" data-msg-cancel="{$MSG_CONFIRM_CANCEL}" data-msg-col-setting="{$MSG_COL_SETTING}" data-msg-col-from="{$MSG_COL_FROM}" data-msg-col-to="{$MSG_COL_TO}" data-msg-empty="{$MSG_EMPTY_VALUE}" data-msg-entries="{$MSG_ENTRIES}">
	<h3>Global configuration</h3>
	<p>Defaults config values for all boards.</p>
	<p>A board only stops following a value here once it overrides that value itself.</p>
	<p>To make your edits take effect - click Save Changes</p>

	<input type="hidden" name="saveGlobalConfig" value="1">
	{$CSRF_INPUT}

	{$CONFIG_NOTICE}

	{$CONFIG_GROUPS}

	<div class="buttonSection">
		<button type="submit" id="boardConfigSaveButton">Save configuration</button>
		<button type="submit" id="boardConfigResetButton" name="resetGlobalConfig" value="1" formnovalidate>Reset to defaults</button>
	</div>
</form>
<!--&CONFIG_FORM_ASSETS/-->
