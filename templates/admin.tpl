
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
		<input type="hidden" name="import-board" value="1">

		<table id="import-board-table">
			<tbody>
				<tr>
					<td class="postblock"><label for="import-board-title">Title</label></td>
					<td>
						<input required class="inputtext" id="import-board-title" name="import-board-title">
					</td>
				</tr>
				<tr>
					<td class="postblock"><label for="import-board-sub-title">Sub-title</label></td>
					<td>
						<input class="inputtext" id="import-board-sub-title" name="import-board-sub-title">
					</td>
				</tr>
				<tr>
					<td class="postblock"><label for="import-board-identifier">Identifier</label></td>
					<td>
						<input class="inputtext" id="import-board-identifier" name="import-board-identifier" placeholder="b">
					</td>
				</tr>
				<tr>
					<td class="postblock"><label for="import-board-path">Absolute directory</label></td>
					<td>
						<input class="inputtext" id="import-board-path" name="import-board-path" required class="url-input" placeholder="/var/www/html/boards/" value="{$DEFAULT_PATH}">
					</td>
				</tr>
				<tr>
					<td class="postblock"><label for="import-board-listed">Listed</label></td> 
					<td><input type="checkbox" id="import-board-listed" name="import-board-listed" checked></td>
				</tr>
				<tr>
					<td class="postblock"><label for="import-board-file"><b>Board database file</b></label></td>
					<td><input type="file" name="import-board-file" id="import-board-file" required><small>[<a href="javascript:void(0);" onclick="$id('import-board-file').value='';">X</a>]</small>
						<div class="formItemDescription">The mysql dump of the pixmicat database you're importing.</div>
					</td> 
				</tr>
				<tr>
					<td class="postblock"><label for="import-board-tablename">Table name</label></td>
					<td>
						<input class="inputtext" id="import-board-tablename" name="import-board-tablename" required value="imglog">
						<div class="formItemDescription">The table name of post table from the pixmicat database.</div>
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

		<table id="banForm">
			<tbody>
				<input type="hidden" name="adminban-action" value="add-ban">
				<tr>
					<td class="postblock"><label for="post_number">Post number</label></td>
					<td><span id="post_number"><!--&IF($POST_NUMBER,'{$POST_NUMBER}','')--></span></td>
					<td><input type="hidden" name="post_uid" id="post_uid" value="<!--&IF($POST_UID,'{$POST_UID}','')-->"></td>
				</tr>
				<tr>
					<td class="postblock"><label for="ip">IP address</label></td>
					<td><input type="text" class="inputtext" id="ip" name="ip" placeholder="Enter IP address" value="<!--&IF($IP,'{$IP}','')-->" required></td>
				</tr>
				<tr>
					<td class="postblock"><label for="duration">Ban duration</label></td>
					<td>
						<input type="text" class="inputtext" id="duration" name="duration" value="1d" placeholder="e.g., 1d, 2h" required>
						<div class="formItemDescription">Examples: 1w = 1 week, 2d = 2 days, 3h = 3 hours</div>
					</td>
				</tr>
				<tr>
					<td class="postblock"><label for="banprivmsg">Reason for ban</label></td>
					<td><textarea class="inputtext" id="banprivmsg" name="privmsg" rows="4" cols="50" placeholder="Enter reason for the ban"></textarea></td>
				</tr>
				<tr>
					<td class="postblock"><label for="banmsg">Public ban message</label></td>
					<td><textarea class="inputtext" id="banmsg" name="banmsg" rows="4" cols="50"><!--&IF($DEFAULT_BAN_MESSAGE,'{$DEFAULT_BAN_MESSAGE}','')--></textarea></td>
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

	<script>
var trolls = Array(
	"Hatsune Miku is nothing more than an overated normie whore.",
	"HAHA NIGGER MODS DELETING POSTS THEY CAN'T TAKE CRITICISM LITERALLY YANDERE DEV OF IMAGE BOARDS",
	"You're imposing on muh freedoms of speech! See you in court, buddy.",
	"Being gay is okay.",
	"<span class=\"unkfunc\">&gt;Soooooooooooy</span>",
	"I know where you live.<br>I watch everything you do.<br>I know everything about you and I am coming!",
	"Ooooh muh god! qLiterally can't even!<br>I didn't even break any of the rules and I was banned?!",
	"Unrestricted access to the internet is a human right.",
	"get live Child Pizza at http:/jbbait.gov<br>get live Child Pizza at http:/jbbait.gov<br>get live Child Pizza at http:/jbbait.gov<br>get live Child Pizza at http:/jbbait.gov",
	"<span class=\"unkfunc\">&gt;(USER WAS BANNED FOR THIS POST)<br>&gt;(USER WAS BANNED FOR THIS POST)<br>&gt;(USER WAS BANNED FOR THIS POST)<br>&gt;(USER WAS BANNED FOR THIS POST)<br>&gt;(USER WAS BANNED FOR THIS POST)<br></span>"
);
var troll = trolls[Math.floor(Math.random()*trolls.length)];

function updatepview(event=null) {
	var msg = document.getElementById("banmsg");
	var pview = document.getElementById("msgpview");
	pview.innerHTML = troll+msg.value;
}

window.onload = function () {
	var msg = document.getElementById("banmsg");
	msg.insertAdjacentHTML("afterend", '<div>Preview:</div><div class="thread"><div class="post reply"><div class="comment" id="msgpview"></div></div></div>');
	msg.oninput = updatepview;
	updatepview();
}
	</script>
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

	<h2 id="banHeading" class="centerText warning">You have been {$BAN_TYPE}! ヽ(ー_ー )ノ</h2>

	<div id="banScreen">
		<div id="banScreenText">
			<p>{$REASON}</p>
			{$BAN_DETAIL}
		</div>

		<img id="banimg" src="{$BAN_IMAGE}" alt="BANNED!">
	</div>

	<hr id="hrBan">
<!--/&BAN_PAGE-->


<!--&JANITOR_WARN_FORM-->
<div class="warnFormContainer">
	<form action="<!--&IF($FORM_ACTION,'{$FORM_ACTION}','')-->" method="POST">
		<h3 class="centerText">Warn user</h3>

		<label> Post No. <span id="post_number"><!--&IF($POST_NUMBER,'{$POST_NUMBER}','')--></span> </label><br>
		<input type="hidden" name="post_uid" value="<!--&IF($POST_NUMBER,'{$POST_NUMBER}','')-->"><br>
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
						<!--&IF($IS_VIEW,'
							<!--&DELETED_POST_NOTE_INPUT/-->
						','
							<!--&DELETED_POST_NOTE_PREVIEW/-->					
						')-->
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

<!--&DELETED_POST_NOTE_INPUT-->
	<tr>
		<td class="postblock">Staff note</td>
		<td> 
			<textarea class="inputtext" name="note" placeholder="Optionally, leave a note."><!--&IF($NOTE,'{$NOTE}','')--></textarea>
			<br><button type="submit" name="action" value="saveNote">Save</button>
		</td>
	</tr>
<!--/&DELETED_POST_NOTE_INPUT-->

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
	<!--&DELETE_POST_RESTORE_BUTTON/-->
<!--/&DELETE_POST_OPEN_BUTTONS-->

<!--&DELETE_POST_PURGE_BUTTON-->
	<!--&IF($FILE_ONLY,'[<button type="submit"
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
	<!--&IF($IS_OPEN,'[<button type="submit"
		name="action"
		value="restore"
		class="adminFunctions adminRestoreFunction buttonLink"
		title="Restore the post to the board">
		Restore
	</button>]','')-->
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