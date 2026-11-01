	<h3>{$HEADING}</h3>
	<p>{$DESCRIPTION}</p>
	<p><strong>{$STATUS}</strong></p>

	<form action="{$MODULE_URL}" method="post">
		{$CSRF_TOKEN}
		<div class="buttonSection">
			<input type="submit" name="{$BUTTON_NAME}" value="{$BUTTON_LABEL}">
		</div>
	</form>
