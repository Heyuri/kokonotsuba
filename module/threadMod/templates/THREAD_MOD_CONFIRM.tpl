	<div class="threadModConfirm">
		<h3>{$TITLE}</h3>
		<form method="POST" action="{$MODULE_URL}" class="threadModForm">
			{$CSRF_TOKEN}
			<input type="hidden" name="action" value="{$ACTION}">
			<input type="hidden" name="postUid" value="{$POST_UID}">
			<table class="formtable threadModTable">
				<tbody>
					<tr>
						<td class="postblock"><label for="threadModPwd">{$PASSWORD_LABEL}</label></td>
						<td>
							<div class="formItemDescription">{$PASSWORD_DESC}</div>
							<input type="password" id="threadModPwd" name="pwd" class="inputtext">
							<button type="submit">{$SUBMIT_LABEL}</button>
						</td>
					</tr>
				</tbody>
			</table>
		</form>
		<p><a href="{$BACK_URL}">{$BACK_LABEL}</a></p>
	</div>
