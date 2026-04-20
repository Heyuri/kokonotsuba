<form id="account-modify-form" action="{$LIVE_INDEX_FILE}?mode=handleAccountAction" method="POST">
		<h3>Your account</h3>

		{$CSRF_HIDDEN_INPUT}
		<input type="hidden" name="password_reset_form" value="1">
		<input  type="hidden" name="id" value="{$ACCOUNT_ID}">

		<table  id="account-view-table">
			<tbody>
				<tr>
					<td class="postblock"><label for="accountviewusername">Username</label></td>
					<td><div id="accountviewusername">{$ACCOUNT_NAME}</div></td>
				</tr>
				<tr>
					<td class="postblock"><label for="accountviewrole">Role</label></td>
					<td><div id="accountviewrole">{$ACCOUNT_ROLE}</div></td>
				</tr>
				<tr>
					<td class="postblock"><label for="accountviewactions">Action record</label></td>
					<td><div id="accountviewactions">{$ACCOUNT_ACTIONS}</div></td>
				</tr>
				<tr>
					<td class="postblock"><label for="reset-password-inital">New password</label></td>
					<td><input type="password" class="inputtext" name="new_account_password" id="reset-password-inital"></td>
				</tr>
			</tbody>
		</table>

		<div class="buttonSection">
			<input type="submit" value="Save new password">
		</div>
	</form>