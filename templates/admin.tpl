
<!--&GLOBAL_ADMIN_PAGE_CONTENT-->
	<div id="adminPageContent">
		{$PAGE_CONTENT}
	</div>
	<hr class="threadSeparator">
	<!--&IF($PAGER,'{$PAGER}','')-->
<!--/&GLOBAL_ADMIN_PAGE_CONTENT-->

<!--&ACCOUNT_PAGE-->
	{$VIEW_OWN_ACCOUNT}
	{$CREATE_ACCOUNT}
	<!--&IF($ACCOUNT_LIST,'<h3>Staff list</h3>{$ACCOUNT_LIST}','')-->
<!--/&ACCOUNT_PAGE-->

<!--&VIEW_ACCOUNT-->
<form id="account-modify-form" action="{$LIVE_INDEX_FILE}?mode=handleAccountAction" method="POST">
		<h3>Your account</h3>

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
<!--/&VIEW_ACCOUNT-->

<!--&CREATE_ACCOUNT-->
	<form action="{$LIVE_INDEX_FILE}?mode=handleAccountAction" method="POST">
		<h3>Create a new staff account</h3>

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
<!--/&CREATE_ACCOUNT-->

<!--&BOARD_PAGE-->
	{$CREATE_BOARD}
	{$IMPORT_BOARD}
	<h3>Existing boards</h3>
	{$BOARD_LIST}
<!--/&BOARD_PAGE-->

<!--&CREATE_BOARD-->
	<form action="{$LIVE_INDEX_FILE}?mode=handleBoardRequests" method="POST">
		<h3>Create a new board</h3>

		<input type="hidden" name="new-board" value="1">

		<table id="board-create-table">
			<tbody>
				<tr>
					<td class="postblock"><label for="new-board-title">Title</label></td>
					<td>
						<input required class="inputtext" id="new-board-title" name="new-board-title">
						<div class="formItemDescription">Title of the board.</div>
					</td>
				</tr>
				<tr>
					<td class="postblock"><label for="new-board-sub-title">Sub-title</label></td>
					<td>
						<input class="inputtext" id="new-board-sub-title" name="new-board-sub-title">
						<div class="formItemDescription">Smaller text beneath the board title on the page, typically providing a description of the board.</div>
					</td>
				</tr>
				<tr>
					<td class="postblock"><label for="new-board-identifier">Identifier</label></td>
					<td>
						<input class="inputtext" id="new-board-identifier" name="new-board-identifier" placeholder="b">
						<div class="formItemDescription">The string that represents the board in the URL and file storage. E.g. the 'b' in "/b/" or "boards.example.net/b/"</div>
					</td>
				</tr>
				<tr>
					<td class="postblock"><label for="new-board-path">Absolute directory</label></td>
					<td>
						<input class="inputtext" id="new-board-path" name="new-board-path" required class="url-input" placeholder="/var/www/html/boards/" value="{$DEFAULT_PATH}">
						<div class="formItemDescription">The directory where the board will be created at. Excluding its identifier. E.g. '/var/www/boards/' not '/var/www/boards/b/'</div>
					</td>
				</tr>
				<tr>
					<td class="postblock"><label for="new-board-listed">Listed</label></td> 
					<td><input type="checkbox" id="new-board-listed" name="new-board-listed" checked></td>
				</tr>
			</tbody>
		</table>

		<div class="buttonSection">
			<input id="board-form-submit" type="submit" value="Create board">
		</div>
	</form>

	<p>After creating a new board, be sure to configure it at its configuration file</p>
<!--/&CREATE_BOARD-->

<!--&IMPORT_BOARD-->
	<form id="import-board-form" action="{$LIVE_INDEX_FILE}?mode=handleBoardRequests" method="POST" enctype="multipart/form-data">
		<h3>Import a board</h3>
		<p>This imports the entirety of a vichan's boards and posts - please ensure there are no conflicting board URIs</p>
		<input type="hidden" name="import-board" value="1">
		<table id="import-board-table">
			<tbody>
				<tr>
					<td class="postblock"><label for="import-board-path">Absolute directory</label></td>
					<td>
						<input class="inputtext" id="import-board-path" name="import-board-path" required class="url-input" placeholder="/var/www/html/boards/" value="{$DEFAULT_PATH}">
					</td>
				</tr>
				<tr>
					<td class="postblock"><label for="import-dump-path">Dump path</label></td>
					<td>
						<input class="inputtext" id="import-dump-path" name="import-dump-path" required class="url-input" placeholder="/srv/kokonotsuba/bkp-kereste.sql">
					</td>
				</tr>
			</tbody>
		</table>

		<div class="buttonSection">
			<input id="import-board-submit" type="submit" value="Import">
		</div>
		
	</form>
<!--/&IMPORT_BOARD-->

<!--&EDIT_BOARD-->
	<form id="board-action-form" action="{$LIVE_INDEX_FILE}?mode=handleBoardRequests" method="POST">
		<h3>Edit board</h3>
	
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
					<td class="postblock"><label for="edit-board-sub-title">Subtitle</label></td>
					<td> <input id="edit-board-sub-title" name="edit-board-sub-title" value="{$BOARD_SUB_TITLE}"></td>
				</tr>
				<tr>
					<td class="postblock"><label for="edit-board-config-path">Config file</label></td>
					<td> <input id="edit-board-config-path" class="url-input" name="edit-board-config-path" value="{$BOARD_CONFIG_FILE}" required></td>
				</tr>
				<tr>
					<td class="postblock"><label for="edit-board-storage-dir">Board storage directory</label></td>
					<td> <input id="edit-board-storage-dir" name="edit-board-storage-dir" value="{$BOARD_STORAGE_DIR}" required> </td>
				</tr>
				<tr>
					<td class="postblock"><label for="edit-board-listed">Listed</label></td>
					<td><input type="checkbox"  id="edit-board-listed" name="edit-board-listed" {$CHECKED}></td>
				</tr>
			</tbody>
		</table>

		<div class="buttonSection">
			<button type="submit" id="board-save-button" name="boardactionsubmit" value="save">Save changes</button>
			<button type="submit" id="edit-board-delete-button" name="board-action-submit" value="delete-board">Delete board</button>
		</div>
	</form>
<!--/&EDIT_BOARD-->

<!--&VIEW_BOARD-->
	[<a id="board-back-button" href="{$LIVE_INDEX_FILE}?mode=boards">Back to board list</a>]

	<h3>Board info</h3>

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
				<td class="postblock"><label for="board-database-sub-title">Subtitle</label></td>
				<td><div id="board-database-sub-title">{$BOARD_SUB_TITLE}</div></td>
			</tr>
			<tr>
				<td class="postblock"><label for="board-date-added">Date added</label></td>
				<td><div id="board-date-added">{$BOARD_DATE_ADDED}</div></td>
			</tr>
			<tr>
				<td class="postblock"><label for="board-config-path">Config file</label></td>
				<td><div id="board-config-path">{$BOARD_CONFIG_FILE}</div></td>
			</tr>
			<tr>
				<td class="postblock"><label for="board-storage-dir">Board storage directory</label></td>
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
	<form action="{$MODULE_URL}" method="POST">
		<h3>Rebuild boards</h3>
		
		{$REBUILD_CHECK_LIST}

		<div class="buttonSection">
			<button name="formSubmit" value="save">Submit</button>
		</div>
	</form>
<!--/&ADMIN_REBUILD_PAGE-->





<!--&GLOBALMSG_PAGE-->
	<form action="{$MODULE_URL}&action=setmessage" method="post">
		<h3>Edit global message</h3>

		<table id="postform_tbl">
			<tbody>
				<tr>
					<td class="postblock" style="min-width:9em"><label for="inputGlobalMessage">Global message<div>(raw HTML)</div></label></td>
					<td><textarea class="inputtext" id="inputGlobalMessage" name="content">{$CURRENT_GLOBAL_MESSAGE}</textarea></td>
				</tr>
			</tbody>
		</table>

		<div class="buttonSection">
			<input type="submit" name="submit" value="Submit">
		</div>
	</form>
		
	<h3>Current global message</h3>

	<hr>

	<div id="globalMessagePreviewCurrent">
		<div id="globalmsg">
			{$CURRENT_GLOBAL_MESSAGE}
		</div>
	</div>
<!--/&GLOBALMSG_PAGE-->

<!--&BLOTTER_ADMIN_PAGE_TABLE_BLOCK-->
	<tr>
		<td>{$DATE}</td>
		<td>{$COMMENT}</td>
		<td><input type="checkbox" name="entrydelete[]" value="{$UID}"></td>
	</tr>
<!--/&BLOTTER_ADMIN_PAGE_TABLE_BLOCK-->

<!--&BLOTTER_ADMIN_PAGE-->
	<form action="{$MODULE_URL}" method='post'>
		<h3>Add new blotter entry</h3>

		<table class="formtable">
			<tbody>
				<tr>
					<td class='postblock'><label for='new_blot_txt'>Blotter entry</label></td>
					<td><textarea id='new_blot_txt' class='inputtext' name='new_blot_txt' cols='30' rows='5' ></textarea></td>
				</tr>
			</tbody>
		</table>

		<div class="buttonSection">
			<input type='submit' name='submit' value='Submit'>
		</div>
	</form>

	<form id="blotterdeletionform" action="{$MODULE_URL}" method="POST">
		<h3>Blotter entries</h3>

		<table class="postlists" id="blotterlist">
			<thead>
				<tr>
					<th>Date</th>
					<th>Entry</th>
					<th>Del</th>
				</tr>
			</thead>
			<tbody>
				<!--&FOREACH($ROWS,'BLOTTER_ADMIN_PAGE_TABLE_BLOCK')-->
			</tbody>
		</table>

		<div class="buttonSection">
			<input value="Submit" name="delete_submit" type="submit">
		</div>
	</form>
<!--/&BLOTTER_ADMIN_PAGE-->

<!--&BLOTTER_PREVIEW_ITEM-->
	<li class="blotterListItem">
		<span class="blotterDate">{$DATE}</span> - <span class="blotterMessage">{$COMMENT}</span>
	</li>
<!--/&BLOTTER_PREVIEW_ITEM-->

<!--&BLOTTER_PREVIEW-->
	<ul id="blotter">
		<!--&FOREACH($ENTRIES,'BLOTTER_PREVIEW_ITEM')-->
		<!--&IF($EMPTY,'<li>- No blotter entries -</li>','')-->
		<li class="blotterListShowAll">[<a href="{$MODULE_URL}">Show all</a>]</li>
	</ul>
<!--/&BLOTTER_PREVIEW-->

<!--&BLOTTER_TABLE_ROW-->
	<tr>
		<td>{$DATE}</td>
		<td>{$COMMENT}</td>
	</tr>
<!--/&BLOTTER_TABLE_ROW-->

<!--&BLOTTER_PAGE-->
	[<a href="{$STATIC_INDEX_FILE}">Return</a>]

	<h2 class="theading2">Blotter</h2>

	<table class="postlists" id="blotterlist">
		<thead>
			<tr>
				<th>Date</th>
				<th>Entry</th>
			</tr>
		</thead>
		<tbody>
			<!--&FOREACH($ROWS,'BLOTTER_TABLE_ROW')-->
			<!--&IF($EMPTY,'<tr><td colspan="2">No entries</td></tr>','')-->
		</tbody>
	</table>
<!--/&BLOTTER_PAGE-->

<!--&ADMIN_BAN_FORM-->
<div class="banFormContainer">
	<form method="POST" action="{$MODULE_URL}">
		<h3 class="centerText">Add a ban</h3>
		<input type="hidden" name="adminban-action" value="add-ban">

		<table id="banForm">
			<tbody>
				<tr>
					<td class="postblock"><label for="post_number">Post number</label></td>
					<td><span id="post_number"><!--&IF($POST_NUMBER,'{$POST_NUMBER}','')--></span></td>
					<td><input type="hidden" name="post_uid" id="post_uid" value="<!--&IF($POST_UID,'{$POST_UID}','')-->"></td>
				</tr>
				<tr>
					<td class="postblock"><label for="ip">IP address</label></td>
					<td>
						<div class="formItemDescription">The IP to be banned. You can use '*' for range bans. E.g, '127.0.*' will ban any IP that begins with '127.0.'</div>
						<input type="text" class="inputtext" id="ip" name="ip" placeholder="Enter IP address" value="<!--&IF($IP,'{$IP}','')-->" required>
					</td>
				</tr>
				<tr>
					<td class="postblock"><label for="duration">Ban duration</label></td>
					<td>
						<input type="text" class="inputtext" id="duration" name="duration" value="1d" placeholder="e.g., 1d, 2h" required>
						<div class="formItemDescription">Legend: 
							<ul>
								<li>'1y' = 1 year</li>
								<li>'1m' = 1 month</li>
								<li>'1w' = 1 week</li>
								<li>'1d' = 1 day</li>
								<li>'1h' = 1 hour</li>
							</ul>
							<p>Decimal values can also be used - e.g: 1.5y = 18 months.</p>
							<p>Units can also be combined - '1y2m' will last for 1 year and 2 months</p>
					</td>
				</tr>
				<tr>
					<td class="postblock"><label for="banprivmsg">Reason for ban</label></td>
					<td>
						<textarea class="inputtext" id="banprivmsg" name="privmsg" rows="4" cols="50" placeholder="Enter reason for the ban"></textarea>
					</td>
				</tr>
				<tr>
					<td class="postblock"><label for="banmsg">Public ban message</label></td>
					<td>
						<textarea class="inputtext" id="banmsg" name="banmsg" rows="4" cols="50"><!--&IF($DEFAULT_BAN_MESSAGE,'{$DEFAULT_BAN_MESSAGE}','')--></textarea>
					</td>
				</tr>
				<tr>
					<td class="postblock"><label for="public">Public ban</label></td>
					<td><input type="checkbox" id="public" name="public"></td>
				</tr>
				<tr>
					<td class="postblock"><label for="global">Global ban</label></td>
					<td><input type="checkbox" id="global" name="global"></td>
				</tr>
			</tbody>
		</table>

		<div class="buttonSection centerText">
			<input id="bigredbutton" type="submit" value="BAN!">
		</div>
	</form>

</div>
<!--/&ADMIN_BAN_FORM-->

<!--&ADMIN_BAN_ROW-->
	<tr>
		<td class="colDel"><input type="checkbox" name="{$CHECKBOX_NAME}" value="on" /></td>
		<td class="colPattern">{$IP}</td>
		<td class="colStart">{$START}</td>
		<td class="colEnd">{$EXPIRES}</td>
		<td class="colReason">{$REASON}</td>
	</tr>
<!--/&ADMIN_BAN_ROW-->

<!--&ADMIN_BAN_TABLE-->
	<form method="POST" action="{$MODULE_URL}">
		<h3>{$TITLE}</h3>

		<input type="hidden" name="adminban-action" value="delete-ban">

		<div id="banTableContainer">
			<table class="postlists banTable" id="{$TABLE_ID}">
				<thead>
					<tr>
						<th>Remove</th>
						<th>IP address</th>
						<th>Start time</th>
						<th>Expiration time</th>
						<th>Reason</th>
					</tr>
				</thead>
				<tbody>
					<!--&FOREACH($ROWS,'ADMIN_BAN_ROW')-->
				</tbody>
			</table>
		</div>

		<div class="buttonSection">
			<button type="submit" id="revokeButton">Remove selected</button>
		</div>
	</form>
<!--/&ADMIN_BAN_TABLE-->

<!--&ADMIN_BAN_MANAGEMENT_PAGE-->
	<!--&ADMIN_BAN_FORM/-->
	<!--&FOREACH($TABLES,'ADMIN_BAN_TABLE')-->
<!--/&ADMIN_BAN_MANAGEMENT_PAGE-->

<!--&BAN_PAGE-->
	<div>[<a href="{$RETURN_URL}">Return</a>]</div>

	<h2 id="banHeading" class="centerText warning">
		<!--&IF($IS_BANNED,'You have been {$BAN_TYPE}! ヽ(ー_ー )ノ','You are not banned! <span class="ascii">ヽ(´∇`)ノ</span>')-->
	</h2>

	<div id="banScreen">
		<div id="banScreenText">
			<p><!--&IF($REASON,'{$REASON}','')--></p>
			<!--&IF($IS_BANNED,'{$BAN_DETAIL}','')-->
		</div>

		<img id="banimg" src="{$BAN_IMAGE}" alt="<!--&IF($IS_BANNED,'BANNED!','NOT BANNED!')-->">
	</div>

	<hr id="hrBan">
<!--/&BAN_PAGE-->

<!--&JANITOR_WARN_FORM-->
<div class="warnFormContainer">
	<form action="<!--&IF($FORM_ACTION,'{$FORM_ACTION}','')-->" method="POST">
		<h3 class="centerText">Warn user</h3>

		<label> Post No. <span id="post_number"><!--&IF($POST_NUMBER,'{$POST_NUMBER}','')--></span> </label><br>
		<input type="hidden" name="post_uid" value="<!--&IF($POST_UID,'{$POST_UID}','')-->"><br>
		<label>Reason:<br>
			<textarea name="msg" cols="80" rows="6"><!--&IF($REASON_DEFAULT,'{$REASON_DEFAULT}','')--></textarea>
		</label><br>
		<label>Public? <input type="checkbox" name="public"></label>

		<div class="buttonSection centerText">
			<input type="submit" value="Warn">
		</div>
	</form>
</div>
<!--/&JANITOR_WARN_FORM-->


<!--&THREAD_MOVE_FORM-->
<div class="moveThreadContainer">
	<form id="thread-move-form" method="POST" action="<!--&IF($FORM_ACTION,'{$FORM_ACTION}','')-->">
		<h3 class="centerText">Move thread</h3>

		<input type="hidden" name="move-thread-uid" value="<!--&IF($THREAD_UID,'{$THREAD_UID}','')-->">
		<input type="hidden" name="move-thread-board-uid" value="<!--&IF($CURRENT_BOARD_UID,'{$CURRENT_BOARD_UID}','')-->">

		<table>
			<tbody>
				<tr>
					<td class="postblock"><label for="move-thread-num">Thread No.</label></td>
					<td><span id="move-thread-num">{$THREAD_NUMBER}</span></td>
				</tr>
				<tr>
					<td class="postblock"><label for="move-thread-board">Current board</label></td>
					<td><span id="move-thread-board">{$CURRENT_BOARD_NAME}</span></td>
				</tr>
				<tr id="boardrow">
					<td class="postblock"><label>Boards</label></td>
					<td>{$BOARD_RADIO_HTML}</td>
				</tr>
				<tr>
					<td class="postblock"><label>Options</label></td>
					<td>
						<label id="move-thread-leave-shadow-thread" title="Leave original thread up and lock it">
							<input type="checkbox" name="leave-shadow-thread" checked value="1">Leave shadow thread
						</label>		
					</td>
				</tr>
			</tbody>
		</table>

		<div class="buttonSection centerText">
			<button type="submit" name="move-thread-submit" value="move it!">Move thread</button>
		</div>
	</form>
</div>
<!--/&THREAD_MOVE_FORM-->

<!--&DELETED_POST_ENTRY-->
	<hr class="threadSeparator">
	<div class="deletedPostContainer">
		<div class="deletedPostEntry">
			<form method="POST" action="<!--&IF($IS_VIEW,'{$VIEW_URL}','{$URL}')-->">
				<input type="hidden" name="deletedPostId" value="{$ID}">
				<table class="deletedPostTable">
					<tbody>
						<tr>
							<td class="postblock">Board</td>
							<td> {$BOARD_TITLE} ({$BOARD_UID})</td>
						</tr>
						<tr>
							<td class="postblock">Deleted by</td>
							<td><!--&IF($DELETED_BY,'{$DELETED_BY}','<i>User</i>')--></td>
						</tr>
						<tr>
							<td class="postblock">Deleted at</td>
							<td>{$DELETED_AT}</td>
						</tr>
						<!--&IF($IS_OPEN,'','
							<!--&DELETED_POST_RESTORE_INFO/-->
						')-->
						<tr>
							<td class="postblock"></td>
							<td>
								<!--&DELETE_POST_BUTTONS/-->
							</td>
						</tr>
					</tbody>
				</table>
			</form>
			<div class="deletedPostHtmlContainer alignLeft">
				{$POST_HTML}
			</div>
		</div>
	</div>
<!--/&DELETED_POST_ENTRY-->

<!--&PURGE_RESTORE_ENTRY_BUTTON-->
	<!--&IF($CAN_PURGE_RESTORE_RECORD,'
		[<button type="submit"
			name="action"
			value="deleteRecord"
			class="adminFunctions adminDeleteRecordFunction buttonLink"
			title="Delete this restore record from the database (post is left intact)">
			Delete
		</button>]
	','')-->
<!--/&PURGE_RESTORE_ENTRY_BUTTON-->

<!--&DELETED_POST_NOTE_PREVIEW-->
	<!--&IF($NOTE_PREVIEW,'
		<tr>
			<td class="postblock">Note</td>
			<td>
				<p class="postNote">{$NOTE_PREVIEW}</p>
			</td>
		</tr>	
	','')-->
<!--/&DELETED_POST_NOTE_PREVIEW-->

<!--&DELETE_POST_BUTTONS-->
	<div class="deletedPostButtons">

		<!--&IF($IS_OPEN,'
			<!--&DELETE_POST_OPEN_BUTTONS/-->
		','
			<!--&PURGE_RESTORE_ENTRY_BUTTON/-->					
		')-->
		
		<!--&IF($IS_VIEW,'','[<a href="{$VIEW_MORE_URL}">View</a>]')-->
	</div>
<!--/&DELETE_POST_BUTTONS-->

<!--&DELETE_POST_OPEN_BUTTONS-->
	<!--&IF($CAN_PURGE,'
		<!--&DELETE_POST_PURGE_BUTTON/-->
	','')-->

	<!--&IF($IS_OPEN,'
		<!--&DELETE_POST_RESTORE_BUTTON/-->
	','')-->
<!--/&DELETE_POST_OPEN_BUTTONS-->

<!--&DELETE_POST_PURGE_BUTTON-->
	<!--&IF($IS_ATTACHMENT_ONLY,'[<button type="submit"
			name="action"
			value="purgeAttachment"
			class="adminFunctions adminPurgeFunction buttonLink"
			title="Purge attachment from system">
			Purge file
		</button>]','
		[<button type="submit"
			name="action"
			value="purge"
			class="adminFunctions adminPurgeFunction buttonLink"
			title="Purge from system">
			Purge
		</button>]
	')-->	
<!--/&DELETE_POST_PURGE_BUTTON-->

<!--&DELETE_POST_RESTORE_BUTTON-->
	<!--&IF($IS_ATTACHMENT_ONLY,'[<button type="submit"
		name="action"
		value="restoreAttachment"
		class="adminFunctions adminRestoreFunction buttonLink"
		title="Restore this attachment to the board">
		Restore attachment
	</button>]','[<button type="submit"
		name="action"
		value="restore"
		class="adminFunctions adminRestoreFunction buttonLink"
		title="Restore the attachment to the board">
		Restore
	</button>]')-->
<!--/&DELETE_POST_RESTORE_BUTTON-->

<!--&DELETED_POST_RESTORE_INFO-->
	<tr>
		<td class="postblock">Restored by</td>
		<td>
			<!--&IF($RESTORED_AT,'{$RESTORED_BY}','<i>N/A</i>')-->
		</td>
	</tr>
	<tr>
		<td class="postblock">Restored at</td>
		<td>
			<!--&IF($RESTORED_AT,'{$RESTORED_AT}','<i>N/A</i>')-->
		</td>
	</tr>
<!--/&DELETED_POST_RESTORE_INFO-->

<!--&DELETED_POST_VIEW_ENTRY-->
	<h3>View <!--&IF($IS_OPEN,'deleted','restored')--> post</h3>
	[<a href="{$BACK_URL}">Back</a>]
	{$DELETED_POST}
<!--/&DELETED_POST_VIEW_ENTRY-->

<!--&DELETED_POSTS_MOD_PAGE-->
	<h3>{$MODULE_HEADER_TEXT}</h3>
	<div class="deletedPostIndexLinks">[<a href="{$URL}">Deleted</a>] [<a href="{$URL}&pageName=restoredIndex">Restored</a>]</div>
	<div class="toggleLink">[<a title="Toggle the visibility of deleted posts on the live frontend" href="{$URL}&toggleVisibility=1"><!--&IF($SHOW_DELETED_POSTS,'Hide','Show')--> deleted posts on live frontend</a>]</div> 
	<div class="deletedPostsListContainer">
		<div class="deletedPostsList">
			<!--&FOREACH($DELETED_POSTS,'DELETED_POST_ENTRY')-->
			<!--&IF($ARE_NO_POSTS,'<div class="centerText">No posts currently in queue.</div>','')-->
		</div>
	</div>
<!--/&DELETED_POSTS_MOD_PAGE-->

<!--&CAPCODE_ENTRY-->
	<h3>View capcode</h3>
	[<a href="{$MODULE_URL}">Back</a>]
	<div class="capcodeEntryContainer">
		<form method="POST" action="{$MODULE_URL}">
			<input name="capcodeId" value="{$ID}" type="hidden">
			<table class="capcodeEntry">
				<tr>
					<td class="postblock"><label for="rawTripcode">Tripcode</label></td>
					<td><input id="rawTripcode" name="rawTripcode" value="{$TRIP_KEY}{$TRIPCODE}"></td>
				</tr>
				<tr>
					<td class="postblock"><label for="capcodeColorHex">Capcode color</label></td>
					<td><input type="color" id="capcodeColorHex" name="capcodeColorHex" value="{$CAPCODE_COLOR}"></td>
				</tr>
				<tr>
					<td class="postblock"><label for="capcodeText">Capcode text</label></td>
					<td><input id="capcodeText" name="capcodeText" value="{$CAPCODE_TEXT}"></td>
				</tr>
				<tr>
					<td class="postblock">Preview</td>
					<td><span class="capcodeEntryPreview">{$PREVIEW}</span></td>
				</tr>
				<tr>
					<td class="postblock">ID</td>
					<td><span class="capcodeEntryId">{$ID}</span></td>
				</tr>
				<tr>
					<td class="postblock">Date added</td>
					<td><span class="capcodeEntryDateAdded">{$DATE_ADDED}</span></td>
				</tr>
				<tr>
					<td class="postblock">Added by</td>
					<td><span class="capcodeEntryAddedBy"><!--&IF($ADDED_BY_USERNAME,'{$ADDED_BY_USERNAME}','<i>N/A</i>')--></span></td>
				</tr>
				<tr>
					<td class="postblock"><label for="capcodeSubmitButtons"></label></td>
					<td>
						<div id="capcodeSubmitButtons">
							<button type="submit"
								name="action"
								value="editCapcode"
								class="adminFunctions adminEditCapcodeFunction"
								title="Delete the capcode">
								Submit edit
							</button>
							<button type="submit"
								name="action"
								value="deleteCapcode"
								class="adminFunctions adminDeleteCapcodeFunction"
								title="Delete the capcode">
								Delete
							</button>
						</div>
					</td>
				</tr>
			</table>
		</form>
	</div>
<!--/&CAPCODE_ENTRY-->

<!--&CAPCODE_CREATE_FORM-->
	<div class="capcodeCreateFormContainer">
		<h4>Add capcode</h4>
		<p>Create a capcode that will be added to the capcode list.</p>
		<form method="POST" action="{$MODULE_URL}">
			<table class="capcodeCreateForm">
				<tr>
					<td class="postblock">Tripcode</td>
					<td><input name="rawTripcode" required="" maxlength=11></td>
					<td><small class="formNote">The tripcode along with the trip key. Like "{$REGULAR_TRIP_KEY}.CzKQna1OU" or "{$SECURE_TRIP_KEY}dHdC5plkz6"</small><td>
				</tr>
				<tr>
					<td class="postblock"><label for="capcodeColorHex">Capcode color</label></td>
					<td><input type="color" id="capcodeColorHex" name="capcodeColorHex"></td>
					<td><small class="formNote">Color that the post name will have.</small></td>
				</tr>
				<tr>
					<td class="postblock"><label for="capcodeText">Capcode text</label></td>
					<td><input id="capcodeText" name="capcodeText"></td>
					<td><small class="formNote">Text that gets appended to the name after '##'. E.g 'User{$SECURE_TRIP_KEY}dHdC5plkz6 ## Pezident'</small></td>
				</tr>
				<tr>
					<td class="postblock"><label for="capcodeSubmitButtons"></label></td>
					<td>
						<div id="capcodeSubmitButtons" class="capcodeSubmitButtons">
							<button type="submit"
								name="action"
								value="createCapcode"
								class="adminFunctions adminCreateCapcodeFunction"
								title="Create capcode">
								Submit
							</button>
						</div>
					</td>
				</tr>
			</table>
		</form>
	</div>
<!--/&CAPCODE_CREATE_FORM-->

<!--&STAFF_CAPCODE_ROW-->
	<tr>
		<td>{$STAFF_CAPCODE_LABEL}</td>
		<td>{$STAFF_CAPCODE_PREVIEW}</td>
		<td>{$STAFF_CAPCODE_REQUIRED_ROLE}</td>
	</tr>
<!--/&STAFF_CAPCODE_ROW-->

<!--&CAPCODE_ROW-->
	<tr>
		<td><span class="postertrip">{$TRIP_KEY}{$TRIPCODE}</span></td>
		<td>{$CAPCODE_COLOR}</td>
		<td>{$CAPCODE_TEXT}</td>
		<td>{$PREVIEW}</td>
		<td>[<a href="{$VIEW_ENTRY_URL}">View</a>]</td>
	</tr>
<!--/&CAPCODE_ROW-->

<!--&CAPCODE_INDEX-->
	<h3>Capcodes</h3> 
	<!--&CAPCODE_CREATE_FORM/-->
	<div class="capcodeListContainer">
		<h4>Capcode list</h4>
		<p>User capcodes that can be used as long as the user knows the trip password.</p>
		<table class="capcodeList postlists">
			<thead>
				<th>Tripcode</th> <th>Color hexadecimal</th> <th>Capcode text</th> <th>Preview</th> <th></th>
			</thead>
			<tbody>
				<!--&FOREACH($CAPCODES,'CAPCODE_ROW')-->
				<!--&IF($ARE_NO_CAPCODES,'
					<tr>
						<td colspan="5" class="centerText">No capcodes found.</td>
					</tr>','')-->
			</tbody>
		</table>
	</div>
	
	<div class="staffCapcodeListContainer">
		<h4>Staff capcode list</h4>
		<p>Only usable by staff. Can be edited in <code>globalconfig.php</code></p>

		<table class="staffCapcodeList postlists">
			<thead>
				<th>Capcode</th> <th>Preview</th> <th>Required role to use</th>
			</thead>
			<tbody>
			<!--&FOREACH($STAFF_CAPCODES,'STAFF_CAPCODE_ROW')-->
			</tbody>
		</table>
	</div>
<!--/&CAPCODE_INDEX-->

<!--&ANTI_SPAM_ENTRY-->
	<h3>View rule</h3>
	[<a href="{$MODULE_URL}">Back</a>]
	<div class="rulesetEntryContainer">
		<form method="POST" action="{$MODULE_URL}">
			<input name="entryId" value="{$ID}" type="hidden">
			<input name="action" value="update" type="hidden">
			<table class="ruleEntry">
				<tbody>
					<tr>
						<td class="postblock"><label for="pattern">Pattern</label></td>
						<td><textarea id="pattern" name="pattern"><!--&IF($PATTERN,'{$PATTERN}','')--></textarea></td>
					</tr>
					<tr>
						<td class="postblock"><label for="matchType">Match type</label></td>
						<td>
							<select name="matchType">
								<option value="contains"<!--&IF($CONTAINS_SELECTED,' selected','')-->>Contains</option>
								<option value="exact"<!--&IF($EXACT_SELECTED,' selected','')-->>Exact match</option>
								<option value="fuzzy"<!--&IF($FUZZY_SELECTED,' selected','')-->>Fuzzy</option>
								<option value="regex"<!--&IF($REGEX_SELECTED,' selected','')-->>Regex</option>
							</select>
						</td>
					</tr>
					<tr>
						<td class="postblock"><label for="maxDistance">Distance</label></td>
						<td>
							<input id="maxDistance" name="maxDistance" min="0" max="4" type="number" value="<!--&IF($MAX_DISTANCE,'{$MAX_DISTANCE}','')-->">
						</td>
					</tr>
					<tr>
						<td class="postblock"><label for="fields">Fields</label></td>
						<td>
							<ul class="boardFilterList" id="fields">
								<li><label><input type="checkbox" name="matchField[]" value="subject"<!--&IF($SUBJECT_SELECTED,' checked','')-->>Subject</label></li>
								<li><label><input type="checkbox" name="matchField[]" value="comment"<!--&IF($COMMENT_SELECTED,' checked','')-->>Comment</label></li>
								<li><label><input type="checkbox" name="matchField[]" value="name"<!--&IF($NAME_SELECTED,' checked','')-->>Name</label></li>
								<li><label><input type="checkbox" name="matchField[]" value="email"<!--&IF($EMAIL_SELECTED,' checked','')-->>Email</label></li>
							</ul>
						</td>
					</tr>
					<tr>
						<td class="postblock"><label for="matchCase">Match case</label></td>
						<td>
							<label><input type="checkbox" id="matchCase" name="matchCase" value="1"<!--&IF($CASE_SENSITIVE,' checked','')-->>Case sensitive</label>
						</td>
					</tr>
					<tr>
						<td class="postblock"><label for="spamAction">Action</label></td>
						<td>
							<select id="spamAction" name="spamAction">
								<option value="reject"<!--&IF($REJECT_SELECTED,' selected','')-->>Reject</option>
								<!--<option value="mute"<!--&IF($MUTE_SELECTED,' selected','')-->>Mute</option>-->
								<!--<option value="ban"<!--&IF($BAN_SELECTED,' selected','')-->>Ban</option>-->
							</select>
						</td>
					</tr>
					<tr>
						<td class="postblock"><label for="description">Description</label></td>
						<td>
							<textarea id="description" name="description" placeholder="Stops an advertising bot."><!--&IF($DESCRIPTION,'{$DESCRIPTION}','')--></textarea>
						</td>
					</tr>
					<tr>
						<td class="postblock"><label for="isActive">Status</label></td>
						<td>
							<label><input type="checkbox" id="isActive" name="isActive" value="1"<!--&IF($IS_ACTIVE,' checked','')-->>Active</label>
						</td>
					</tr>
					<tr>
						<td class="postblock"><label for="userMessage">User message</label></td>
						<td>
							<textarea id="userMessage" name="userMessage" placeholder="Your post contained content that tripped spam filters."><!--&IF($USER_MESSAGE,'{$USER_MESSAGE}','')--></textarea>
						</td>
					</tr>
					<tr>
						<td class="postblock">Date added</td>
						<td><span class="spamRuleCreatedAt">{$CREATED_AT}</span></td>
					</tr>
					<tr>
						<td class="postblock">Created by</td>
						<td><span class="spamRuleCreatedBy"><!--&IF($CREATED_BY,'{$CREATED_BY}','<i>N/A</i>')--></span></td>
					</tr>
				</tbody>
			</table>
			<input type="submit" value="Submit">
		</form>
	</div>
<!--/&ANTI_SPAM_ENTRY-->

<!--&NEW_ENTRY_FORM-->
	<div class="newEntryForm">
		<form method="POST" action="{$MODULE_URL}">
			<input name="action" value="addEntry" type="hidden">
			<table>
				<tbody>
					<tr>
						<td class="postblock"><label for="pattern">Pattern</label></td>
						<td>
							<div class="formItemDescription">The string you want to ban. Enter raw regex if using the regex match type.</div>
							<textarea id="pattern" name="pattern" placeholder="Spicy viagra pills for just 19.31! a pop!" required><!--&IF($PATTERN_VALUE,'{$PATTERN_VALUE}','')--></textarea>
						</td>
					</tr>
					<tr>
						<td class="postblock"><label for="matchType">Match type</label></td>
						<td>
							<div class="formItemDescription">The way strings are checked. </div>
							<select name="matchType">
								<option value="contains">Contains</option>
								<option value="exact">Exact match</option>
								<option value="fuzzy">Fuzzy</option>
								<option value="regex">Regex</option>
							</select>
						</td>
					</tr>
					<tr>
						<td class="postblock"><label for="maxDistance">Distance</label></td>
						<td>
							<div class="formItemDescription">For the fuzzy match type. The higher the distance, the less strict it is. Higher values may increase cases of false positives.</div>
							<input id="maxDistance" name="maxDistance" min="0" max="4" type="number" value="3">
						</td>
					</tr>
					<tr>
						<td class="postblock"><label for="fields">Fields</label></td>
						<td>
							<div class="formItemDescription">The fields that will be checked when the post is submitted.</div>
							<ul class="boardFilterList" id="fields">
								<li><label><input type="checkbox" name="matchField[]" value="subject" checked>Subject</label></li>
								<li><label><input type="checkbox" name="matchField[]" value="comment" checked>Comment</label></li>
								<li><label><input type="checkbox" name="matchField[]" value="name" checked>Name</label></li>
								<li><label><input type="checkbox" name="matchField[]" value="email" checked>Email</label></li>
							</ul>
						</td>
					</tr>
					<tr>
						<td class="postblock"><label for="matchCase">Match case</label></td>
						<td>
							<div class="formItemDescription">Whether it should be case sensitive.</div>
							<label><input type="checkbox" id="matchCase" name="matchCase" value="1">Case sensitive</label>
						</td>
					</tr>
					<tr>
						<td class="postblock"><label for="spamAction">Action</label></td>
						<td>
							<div class="formItemDescription">What happens to the user or their post once its caught by the spam rule.</div>
							<select id="spamAction" name="spamAction">
								<option value="reject">Reject</option>
								<!--<option value="mute">Mute</option>-->
								<!--<option value="ban">Ban</option>-->
							</select>
						</td>
					</tr>
					<tr>
						<td class="postblock"><label for="description">Description</label></td>
						<td>
							<div class="formItemDescription">Describes what the filter is for.</div>
							<textarea id="description" name="description" placeholder="Stops an advertising bot."></textarea>
						</td>
					</tr>
					<tr>
						<td class="postblock"><label for="userMessage">User message</label></td>
						<td>
							<div class="formItemDescription">The error message a user will see if they trip the spam rule. (Leave blank for a default message)</div>
							<textarea id="userMessage" name="userMessage" placeholder="Your post contained content that tripped spam filters."></textarea>
						</td>
					</tr>
				</tbody>
			</table>

			<div class="buttonSection">
				<input type="submit" value="Submit">
			</div>
		</form>
	</div>
<!--/&NEW_ENTRY_FORM-->

<!--&SPAM_ROW-->
	<tr>
		<td><div class="centerText"><input name="entryIDs[]" type="checkbox" value="{$ID}"></div></td>
		<td>{$PATTERN}</td>
		<td><!--&IF($IS_ACTIVE,'Yes','No')--></td>
		<td><!--&IF($DESCRIPTION,'{$DESCRIPTION}','')--></td>
		<td><!--&IF($ACTION,'{$ACTION}','')--></td>
		<td><!--&IF($MATCH_TYPE,'{$MATCH_TYPE}','')--></td>
		<td><!--&IF($APPLIED_FIELDS,'{$APPLIED_FIELDS}','')--></td>
		<td><a href="{$VIEW_ENTRY_URL}">View</a></td>
	</tr>
<!--/&SPAM_ROW-->

<!--&ANTI_SPAM_INDEX-->
	<h3>Anti-spam management</h3>
	<h4>Add new rule</h4>
	<!--&NEW_ENTRY_FORM/--> 
	<div class="spamRulesContainer">
		<h4>Spam ruleset</h4>
		<p>Anti-spam rules that every new post submission is checked against for active entries.</p>
		<form action="{$MODULE_URL}" method="POST">
			<input type="hidden" name="action" value="delete">
			<table class="spamList postlists">
				<thead>
					<th>Delete</th> <th>Pattern</th> <th>Active?</th> <th>Description</th> <th>Action</th> <th>Match type</th> <th>Fields</th> <th></th>
				</thead>
				<tbody>
					<!--&FOREACH($ROWS,'SPAM_ROW')-->
				</tbody>
			</table>
			<input type="submit" value="Submit">
		</form>
	</div>
<!--/&ANTI_SPAM_INDEX-->

<!--&THEME_FORM-->
	<h3>Theme for thread No.{$THREAD_NUMBER}</h3>
	<p>Edit the thread's style and elements - AKA css hax.</p>
	<div class="themeForm">
		<form method="POST" action="{$MODULE_URL}">
			<input name="action" value="<!--&IF($IS_EDIT,'edit','create')-->" type="hidden">
			<input name="thread_uid" value="<!--&IF($THREAD_UID,'{$THREAD_UID}','')-->" type="hidden">
			<table>
				<tbody>
					<tr>
						<td class="postblock"><label for="backgroundHexColor">Background color</label></td>
						<td>
							<div class="formItemDescription">The background color. It will be obscured by a background image if one is set.</div>
							<input id="backgroundHexColor" name="backgroundHexColor" <!--&IF($BACKGROUND_HEX_COLOR,'value="{$BACKGROUND_HEX_COLOR}"','')--> type="color">
						</td>
					</tr>
					<tr>
						<td class="postblock"><label for="replyBackgroundHexColor">Reply background color</label></td>
						<td>
							<div class="formItemDescription">The background color of reply elements.</div>
							<input id="replyBackgroundHexColor" name="replyBackgroundHexColor" <!--&IF($REPLY_BACKGROUND_HEX_COLOR,'value="{$REPLY_BACKGROUND_HEX_COLOR}"','')--> type="color">
						</td>
					</tr>
					<tr>
						<td class="postblock"><label for="backgroundImageUrl">Background image</label></td>
						<td>
							<div class="formItemDescription">URL of an image which will be displayed as a tiled background for the thread.</div>
							<input class="inputtext" id="backgroundImageUrl" name="backgroundImageUrl" <!--&IF($BACKGROUND_IMAGE_URL,'value="{$BACKGROUND_IMAGE_URL}"','')--> placeholder="https://up.heyuri.net/src/0001.jpg">
						</td>
					</tr>
					<tr>
						<td class="postblock"><label for="textHexColor">Text color</label></td>
						<td>
							<div class="formItemDescription">The color of the text.</div>
							<input id="textHexColor" name="textHexColor" <!--&IF($TEXT_HEX_COLOR,'value="{$TEXT_HEX_COLOR}"','')--> type="color">
						</td>
					</tr>
					<tr>
						<td class="postblock"><label for="audio">Audio</label></td>
						<td>
							<div class="formItemDescription">URL of audio that plays when opening the thread.</div>
							<input class="inputtext" id="audio" name="audio" <!--&IF($AUDIO,'value="{$AUDIO}"','')--> placeholder="https://up.heyuri.net/src/0002.mp3">
						</td>
					</tr>
					<tr>
						<td class="postblock"><label for="rawStyling">Raw styling</label></td>
						<td>
							<div class="formItemDescription">Raw CSS for the thread - you will have to create blocks yourself.</div>
							<textarea class="inputtext" id="rawStyling" name="rawStyling"placeholder="#t1_123 { display: hidden; }"><!--&IF($RAW_STYLING,'{$RAW_STYLING}','')--></textarea>
						</td>
					</tr>
					<!--&IF($DATE_ADDED,'
						<tr>
							<td class="postblock">Date added</td>
							<td>
								<div class="dateThemeAdded">{$DATE_ADDED}</div>
							</td>
						</tr>
						','')-->
					<!--&IF($ADDED_BY,'
						<tr>
							<td class="postblock">Theme added by</td>
							<td>
								<div class="themeAddedBy">{$ADDED_BY}</div>
							</td>
						</tr>
						','')-->
				</tbody>
			</table>

			<div class="buttonSection">
				<input type="submit" value="Submit">
				<!--&IF($IS_EDIT,'<button name="action" value="delete">Delete theme</button>','')-->
			</div>
		</form>
	</div>
<!--/&THEME_FORM-->

<!--&NOTE_CREATE_FORM-->
	<div class="noteFormContainer">
		<h3>Leave a note for post No.<span class="noteFormPostNumber">{$POST_NUMBER}</span></h3>
		<div class="noteForm">
			<form method="POST" action="{$MODULE_URL}">
				<input name="action" value="addNote" type="hidden">
				<input name="postUid" value="<!--&IF($POST_UID,'{$POST_UID}','')-->" type="hidden">
				<table>
					<tbody>
						<tr>
							<td class="postblock"><label for="note">Note</label></td>
							<td>
								<div class="formItemDescription">{$NOTE_VISIBILITY_DESCRIPTION}</div>
								<textarea id="note" name="note"></textarea>
							</td>
						</tr>
					</tbody>
				</table>

				<div class="buttonSection">
					<input type="submit" value="Save note">
				</div>
			</form>
	</div>
<!--/&NOTE_CREATE_FORM-->

<!--&NOTE_EDIT_FORM-->
	<div class="noteFormContainer">
		<h3>Edit note for post No.<span class="noteFormPostNumber">{$POST_NUMBER}</span></h3>
		<div class="noteForm">
			<form method="POST" action="{$MODULE_URL}">
				<input name="action" value="editNote" type="hidden">
				<input name="note_id" value="<!--&IF($NOTE_ID,'{$NOTE_ID}','')-->" type="hidden">
				<table>
					<tbody>
						<tr>
							<td class="postblock"><label for="note">Note</label></td>
							<td>
								<div class="formItemDescription">{$NOTE_VISIBILITY_DESCRIPTION}</div>
								<textarea id="note" name="note"><!--&IF($NOTE,'{$NOTE}','')--></textarea>
							</td>
						</tr>
					</tbody>
				</table>

				<div class="buttonSection">
					<input type="submit" value="Save note">
					<button name="action" value="deleteNote">Delete note</button>
				</div>
			</form>
	</div>
<!--/&NOTE_EDIT_FORM-->

<!--&NOTE_ENTRY_HTML-->
	<div class="noteOnPost" title="{$NOTE_TITLE_TEXT}">{$NOTE_TEXT}
		<i class="noteAddedBy"> - {$ACCOUNT_NAME}</i> <i>({$NOTE_TIMESTAMP})</i> 
		<span class="noteFunctions"> 
			<!--&IF($CAN_DELETE_NOTE,'<span class="noteDeleteFunction">[<a href="{$NOTE_DELETION_URL}">X</a>]</span>','')-->
			<!--&IF($CAN_EDIT_NOTE,'<span class="noteEditFunction">[<a href="{$NOTE_EDIT_URL}">E</a>]</span>','')-->
		</span>
	</div>
<!--/&NOTE_ENTRY_HTML-->