	<form action="{$LIVE_INDEX_FILE}?mode=handleAccountAction" method="POST">
		<h3>Create a new staff account</h3>

		{$CSRF_HIDDEN_INPUT}
		<table id="account-create-table">
			<tbody>
				<tr>
					<td class="postblock"><label for="usrname">Account username:</label></td>
					<td><input required maxlength="50" class="inputtext" id="usrname" name="usrname"></td>
				</tr>
				<tr>
					<td class="postblock"><label for="passwd">Account password:</label></td>
					<td><input type="password" class="inputtext" id="passwd" name="passwd" required maxlength="1000"></td>
				</tr>
				<tr>
					<td class="postblock"><label for="hashed">Already hashed?</label></td>
					<td><input type="checkbox" id="hashed" name="ishashed"></td>
				</tr>
				<tr>
					<td class="postblock"><label for="role">Role</label></td>
					<td>
						<select id="role" name="role" required>
							<option value="" disabled checked>Select a role</option>
							<option value="{$USER}">User</option>
							<option value="{$JANITOR}">Janitor</option>
							<option value="{$MODERATOR}">Moderator</option>
							<option value="{$ADMIN}">Admin</option>
						</select>
					</td>
				</tr>
			</tbody>
		</table>

		<div class="buttonSection">
			<input id="accountcreateformsubmit" type="submit" value="Create account">
		</div>
	</form>