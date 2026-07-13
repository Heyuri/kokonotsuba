<form id="boardConfigForm" action="{$LIVE_INDEX_FILE}?mode=handleBoardRequests" method="POST" data-msg-no-changes="{$MSG_NO_CHANGES}" data-msg-failed="{$MSG_SAVE_FAILED}" data-msg-confirm="{$MSG_CONFIRM_SAVE}" data-msg-more="{$MSG_CONFIRM_MORE}">
	<h3>Board configuration</h3>
	<p>You can edit the board's configuration here.</p>
	<p>To make your edits take effect - click Save Changes</p>

	<input type="hidden" name="saveBoardConfig" value="{$BOARD_UID}">
	{$CSRF_INPUT}

	{$CONFIG_NOTICE}

	{$CONFIG_GROUPS}

	<div class="buttonSection">
		<button type="submit" id="boardConfigSaveButton">Save configuration</button>
		<button type="submit" id="boardConfigResetButton" name="resetBoardConfig" value="{$BOARD_UID}" formnovalidate>Reset to defaults</button>
	</div>
</form>
<!--&CONFIG_FORM_ASSETS/-->
