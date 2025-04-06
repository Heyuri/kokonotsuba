
<!--&ACCOUNT_PAGE-->
	{$VIEW_OWN_ACCOUNT}
	{$CREATE_ACCOUNT}
	<!--&IF($ACCOUNT_LIST,'<h3>Staff list</h3>{$ACCOUNT_LIST}','')-->
<!--/&ACCOUNT_PAGE-->

<!--&VIEW_ACCOUNT-->
	<h3>Your account</h3>
	<form id="account-modify-form" action="{$PHP_SELF}?mode=handleAccountAction" method="POST">
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
					<td><input type="password" name="new_account_password" id="reset-password-inital"></td>
				</tr>
			</tbody>
		</table>
		<input type="submit" value="Save">
	</form>
<!--/&VIEW_ACCOUNT-->

<!--&CREATE_ACCOUNT-->
	<h3>Create a new staff account</h3>
		<form action="{$PHP_SELF}?mode=handleAccountAction" method="POST">
			<table id="account-create-table">
				<tbody>
					<tr>
						<td class="postblock"><label for="usrname">Account username:</label></td>
						<td><input required maxlength="50" id="usrname" name="usrname"></td>
					</tr>
					<tr>
						<td class="postblock"><label for="passwd">Account password:</label></td>
						<td><input type="password" id="passwd" name="passwd" required maxlength="1000"></td>
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
			<input id="accountcreateformsubmit" type="submit" value="Create account">
		</form>
<!--/&CREATE_ACCOUNT-->

<!--&BOARD_PAGE-->
	<h2>Create a new board</h2>
	{$CREATE_BOARD}
	<h2>Boards</h2>
	{$BOARD_LIST}
<!--/&BOARD_PAGE-->

<!--&CREATE_BOARD-->
		<form action="{$PHP_SELF}?mode=handleBoardRequests" method="POST">
			<input type="hidden" name="new-board" value="1">
			<table id="board-create-table">
				<tbody>
					<tr>
						<td class="postblock"><label for="new-board-title">Title</label></td>
						<td><input required  id="new-board-title" name="new-board-title"></td> <td>Title of the board.</td>
					</tr>
					<tr>
						<td class="postblock"><label for="new-board-sub-title">Sub-title</label></td>
						<td><input  id="new-board-sub-title" name="new-board-sub-title"></td> <td>Smaller text beneath the board title on the page, typically providing a description of the board</td>
					</tr>
					<tr>
						<td class="postblock"><label for="new-board-identifier">Identifier</label></td>
						<td><input id="new-board-identifier" name="new-board-identifier" placeholder="b"></td> <td>The string that represents the board in the URL and file storage. e.g the 'b' in "/b/" or "boards.example.net/b/"</td>
					</tr>
					<tr>
						<td class="postblock"><label for="new-board-path">Absolute directory</label></td>
						<td><input id="new-board-path" name="new-board-path" required class="url-input" placeholder="/var/www/html/boards/" value="{$DEFAULT_PATH}"></td> <td>The directory where the board will be created at. Excluding it's identifier. e.g '/var/www/boards/' not '/var/www/boards/b/'</td>
					</tr>
					<tr>
						<td class="postblock"><label for="new-board-listed">Listed</label></td> 
						<td><input type="checkbox" id="new-board-listed" name="new-board-listed" checked></td>
					</tr>
						<tr>
							<td class="postblock"><label for="board-form-submit"></label></td><td><input id="board-form-submit" type="submit" value="Create board"></td>
						</tr>
					</tbody>
				</table>
		</form>
		<p> After creating a new board, be sure to configure it at its configuration file</p>
<!--/&CREATE_BOARD-->

<!--&EDIT_BOARD-->
	<h2>Edit Board</h2>
	<form id="board-action-form" action="{$PHP_SELF}?mode=handleBoardRequests" method="POST">
	
		<input type="hidden" name="edit-board-uid" value="{$BOARD_UID}">
		<input type="hidden" name="edit-board-uid-for-redirect" value="{$BOARD_UID}">
		<input type="hidden" name="edit-board" value="{$BOARD_UID}">
		
		<table id="board-action-table">
			<tbody>
				<tr>
					<td class="postblock"><label for="edit-board-identifier">Identifier</label></td>
					<td> <input id="edit-board-identifier" name="edit-board-identifier" value="{$BOARD_IDENTIFIER}"></td>
				</tr>
				<tr>
					<td class="postblock"><label for="edit-board-title">Title</label></td>
					<td> <input required id="edit-board-title" name="edit-board-title" value="{$BOARD_TITLE}"></td>
				</tr>
				<tr>
					<td class="postblock"><label for="edit-board-sub-title">Sub-title</label></td>
					<td> <input id="edit-board-sub-title" name="edit-board-sub-title" value="{$BOARD_SUB_TITLE}"></td>
				</tr>
				<tr>
					<td class="postblock"><label for="edit-board-config-path">Config File</label></td>
					<td> <input id="edit-board-config-path" class="url-input" name="edit-board-config-path" value="{$BOARD_CONFIG_FILE}" required></td>
				</tr>
				<tr>
					<td class="postblock"><label for="edit-board-storage-dir">Board Storage Directory</label></td>
					<td> <input id="edit-board-storage-dir" name="edit-board-storage-dir" value="{$BOARD_STORAGE_DIR}" required> </td>
				</tr>
				<tr>
					<td class="postblock"><label for="edit-board-listed">Listed</label></td>
					<td><input type="checkbox"  id="edit-board-listed" name="edit-board-listed" {$CHECKED}></td>
				</tr>
				<tr>
					<td class="postblock"><label for="board-save-button"></label></td>
					<td> <button type="submit" id="board-save-button" name="boardactionsubmit" value="save">Save</button> </td>
				</tr>
				<tr>
					<td class="postblock"><label for="edit-board-delete-button"></label></td>
					<td> <button type="submit" id="edit-board-delete-button" name="board-action-submit" value="delete-board">Delete Board</button> </td>
				</tr>
			</tbody>
		</table>
	</form>
<!--/&EDIT_BOARD-->

<!--&VIEW_BOARD-->
	[<a id="board-back-button" href="{$PHP_SELF}?mode=boards">Back to board list</a>]
	<h2>Board</h2>
		<table  id="board-view-table">
			<tbody>
			<tr>
				<td class="postblock"><label for="board-uid">UID</label></td>
				<td><div id="board-uid">{$BOARD_UID}</div></td>
			</tr>
			<tr>
				<td class="postblock"><label for="board-identifier">Identifier</label></td>
				<td><div id="board-database-title">{$BOARD_IDENTIFIER}</div></td>
			</tr>
			<tr>
				<td class="postblock"><label for="board-database-title">Title</label></td>
				<td><div id="board-database-title">{$BOARD_TITLE}</div></td>
			</tr>
			<tr>
				<td class="postblock"><label for="board-database-sub-title">Sub-title</label></td>
				<td><div id="board-database-sub-title">{$BOARD_SUB_TITLE}</div></td>
			</tr>
			<tr>
				<td class="postblock"><label for="board-date-added">Date Added</label></td>
				<td><div id="board-date-added">{$BOARD_DATE_ADDED}</div></td>
			</tr>
			<tr>
				<td class="postblock"><label for="board-config-path">Config File</label></td>
				<td><div id="board-config-path">{$BOARD_CONFIG_FILE}</div></td>
			</tr>
			<tr>
				<td class="postblock"><label for="board-storage-dir">Board Storage Directory</label></td>
				<td><div id="board-storage-dir">{$BOARD_STORAGE_DIR}</div></td>
			</tr>
			<tr>
				<td class="postblock"><label for="board-url">URL</label></td>
				<td><div id="board-url"><a href="{$BOARD_URL}">{$BOARD_URL}</a></div></td>
			</tr>
			<tr>
				<td class="postblock"><label for="board-listed">Listed</label></td>
				<td><div id="board-url">{$BOARD_IS_LISTED}</div></td>
			</tr>
		</tbody>
	</table>
	
	{$EDIT_BOARD_HTML}
<!--/&VIEW_BOARD-->

<!--&ADMIN_REBUILD_PAGE-->
	<h2>Rebuild</h2>
	<h3>Boards</h3>
	<form action="{$MODULE_URL}" method="POST">
		{$REBUILD_CHECK_LIST}
		<button name="formSubmit" value="save">Submit</button>
	</form>
<!--/&ADMIN_REBUILD_PAGE-->