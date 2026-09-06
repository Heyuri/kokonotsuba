	<hr class="threadSeparator">

	<form class="banAppealForm" method="POST" action="{$MODULE_URL}">
		{$CSRF_TOKEN}
		<input type="hidden" name="banId" value="{$BAN_ID}">

		<h4 class="banAppealHeading">{$APPEAL_HEADING}</h4>
		<div class="formItemDescription">{$APPEAL_HINT}</div>

		<textarea class="inputtext banAppealReason" name="reason" rows="5" cols="60" maxlength="{$MAX_LENGTH}" placeholder="{$APPEAL_PLACEHOLDER}" required></textarea>

		<div class="buttonSection">
			<button type="submit">{$APPEAL_SUBMIT}</button>
		</div>
	</form>
