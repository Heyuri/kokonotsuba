	<div class="threadModConfirm">
		<h3>{$TITLE}</h3>
		<form method="POST" action="{$MODULE_URL}" class="threadModForm">
			{$CSRF_TOKEN}
			<input type="hidden" name="action" value="{$ACTION}">
			<input type="hidden" name="postUid" value="{$POST_UID}">
			<label>{$PASSWORD_LABEL} <input type="password" name="pwd" class="inputtext"></label>
			<input type="submit" value="{$SUBMIT_LABEL}">
		</form>
		<p><a href="{$BACK_URL}">{$BACK_LABEL}</a></p>
	</div>
