	<h3>{$LOGIN_FORM_HEADING}</h3>
	<p class="description">{$TRIPCODE_LOGIN_DESCRIPTION}</p>
	<div class="loginFormContainer">
		<form action="{$MODULE_PAGE_URL}" method="POST">
			<input name="action" value="tripLogin" type="hidden">
			<table class="loginFormTable">
				<tr>
					<td class="postblock"><label for="tripcodeLogin">{$TRIPCODE_LOGIN_LABEL}</td>
					<td>
						<p class="formItemDescription">{$TRIPCODE_LOGIN_HASH_NOTE}</p>
						<input id="tripcodeLogin" name="tripcodeLogin" type="password" placeholder="#password">
					</td>
				</tr>
			</table>
			<input type="submit" value="{$LOGIN_SUBMIT}">
		</form>
	</div>