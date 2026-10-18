		<form method="POST" action="{$MODULE_URL}" class="threadModForm threadModGrantForm">
			{$CSRF_TOKEN}
			<input type="hidden" name="action" value="grant">
			<label>{$GRANT_LABEL} No.<input type="number" name="postNumber" min="1" class="inputtext threadModNumberField"></label>
			<input type="submit" value="{$GRANT_BUTTON}">
		</form>
		<form method="POST" action="{$MODULE_URL}" class="threadModForm threadModTransferForm">
			{$CSRF_TOKEN}
			<input type="hidden" name="action" value="transfer">
			<label>{$TRANSFER_LABEL} No.<input type="number" name="postNumber" min="1" class="inputtext threadModNumberField"></label>
			<input type="submit" value="{$TRANSFER_BUTTON}">
		</form>
		<h4>{$ROSTER_TITLE}</h4>
		{$ROSTER_HTML}
