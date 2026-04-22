	<span class="accountIndexReturnAnchor">[<a href="{$LIVE_INDEX_FILE}?mode=account">Back to accounts</a>]</span>
	<form id="staff-account-form" action="{$LIVE_INDEX_FILE}?mode=handleAccountAction" method="POST">
		<h3>Staff account</h3>

		{$CSRF_HIDDEN_INPUT}
		<input type="hidden" name="target_account_id" value="{$ACCOUNT_ID}">

		<table id="staff-account-view-table">
			<tbody>
				<tr>
					<td class="postblock">ID</td>
					<td>{$ACCOUNT_ID}</td>
				</tr>
				<tr>
					<td class="postblock">Username</td>
					<td>{$ACCOUNT_NAME}</td>
				</tr>
				<tr>
					<td class="postblock">Role</td>
					<td>{$ACCOUNT_ROLE}</td>
				</tr>
				<tr>
					<td class="postblock">Total actions</td>
					<td>{$ACCOUNT_ACTIONS}</td>
				</tr>
				<tr>
					<td class="postblock">Last logged in</td>
					<td>{$ACCOUNT_LAST_LOGIN}</td>
				</tr>
				<tr>
					<td class="postblock"><label for="admin-reset-password">Reset password</label></td>
					<td><input type="password" class="inputtext" name="admin_reset_password" id="admin-reset-password"></td>
				</tr>
			</tbody>
		</table>

		<div class="buttonSection">
			[<button type="submit" name="action" value="promote" class="buttonLink" title="Promote account">Promote</button>]
			[<button type="submit" name="action" value="demote" class="buttonLink" title="Demote account">Demote</button>]
			[<button type="submit" name="action" value="delete" class="buttonLink" title="Delete account">Delete</button>]
			[<button type="submit" name="action" value="reset_password" class="buttonLink" title="Save new password">Save password</button>]
		</div>
	</form>
