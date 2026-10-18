		<form method="POST" action="{$MODULE_URL}" class="threadModForm threadModClaimForm">
			{$CSRF_TOKEN}
			<input type="hidden" name="action" value="claim">
			<input type="hidden" name="threadUid" value="{$THREAD_UID}">
			<label>{$PASSWORD_LABEL} <input type="password" name="pwd" class="inputtext"></label>
			<input type="submit" value="{$CLAIM_LABEL}">
		</form>
