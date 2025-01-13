<!--&THEMENAME-->Kokonotsuba Textboard
<!--/&THEMENAME-->
<!--&THEMEVER-->DEV RC1
<!--/&THEMEVER-->
<!--&THEMEAUTHOR-->Deadking
<!--/&THEMEAUTHOR-->
<!--&HEADER-->
<!DOCTYPE html>
<html lang="en-US">

<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<meta http-equiv="cache-control" content="max-age=0">
	<meta http-equiv="cache-control" content="no-cache">
	<meta http-equiv="expires" content="0">
	<meta http-equiv="expires" content="Tue, 01 Jan 1980 1:00:00 GMT">
	<meta http-equiv="pragma" content="no-cache">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="Berry" content="no">
	<title>{$PAGE_TITLE}</title>
	<meta name="robots" content="follow,archive">
	<link class="linkstyle" rel="stylesheet" type="text/css" href="{$STATIC_URL}css/txt/pseud0ch.css" title="Pseud0ch Mona">
	<link class="linkstyle" rel="stylesheet alternate" type="text/css" href="{$STATIC_URL}css/txt/pseud0ch2.css" title="Pseud0ch">
	<link class="linkstyle" rel="stylesheet alternate" type="text/css" href="{$STATIC_URL}css/txt/pseud0ch3.css" title="Pseud0ch Times New Roman">
	<link class="linkstyle" rel="stylesheet alternate" type="text/css" href="{$STATIC_URL}css/txt/kareha.css" title="Kareha">
	<link class="linkstyle" rel="stylesheet alternate" type="text/css" href="{$STATIC_URL}css/txt/mobile.css" title="Mobile">
	<link rel="shortcut icon" href="{$STATIC_URL}image/favicon.png">
	<script type="text/javascript" src="{$STATIC_URL}js/koko.js"></script>
	<script type="text/javascript" src="{$STATIC_URL}js/qr.js"></script>
	<script type="text/javascript" src="{$STATIC_URL}js/qu.js"></script>
	<script type="text/javascript" src="{$STATIC_URL}js/qu2.js"></script>
	<script type="text/javascript" src="{$STATIC_URL}js/style.js"></script>
	<script type="text/javascript" src="{$STATIC_URL}js/catalog.js"></script>
	<script type="text/javascript" src="{$STATIC_URL}js/insert.js"></script>
	<script type="text/javascript" src="{$STATIC_URL}js/addemotestxt.js" defer></script>
	<script type="text/javascript" src="{$STATIC_URL}/js/admin.js" defer></script>
	<!--/&HEADER-->

	<!--&TOPLINKS-->
	<div class="boardlist"<!--&IF($IS_THREAD,' style="display:none"','')-->>
		<span class="toplinks">{$TOP_LINKS}</span>
		<div class="adminbar" align="RIGHT">{$HOME} {$HOOKLINKS} {$ADMIN}</div>
	</div>
	<!--/&TOPLINKS-->

	<!--&BODYHEAD-->

<body class="thread" style="border:none;padding:0px;margin:0px;background-image:none;margin-left:0px; padding-top: 1px;";>
	[<a href="{$PHP_SELF2}">Return</a>]  {$HOME}
	<hr>
	<script id="wz_tooltip" type="text/javascript" src="{$STATIC_URL}js/wz_tooltip.js"></script>
	<!--&TOPLINKS/-->
	<!--/&BODYHEAD-->

	<!--&POSTFORM-->
	<div id="postarea">
		<form id="postform" name="postform" action="{$PHP_SELF}" method="POST" <!--&IF($MAX_FILE_SIZE,' enctype="multipart/form-data"','')-->>
				<table class="menu" align="CENTER" width="95%" border="<!--&IF($IS_THREAD,'0','1')-->" cellspacing="7" cellpadding="3"<!--&IF($IS_THREAD,' style="display:inline;background:transparent;border:none"','')-->><tbody>
					<tr><td>
						<nobr><font size="+1"><b><!--&IF($IS_THREAD,' New Reply ','New Thread')--></b></font>
			</nobr>
			{$FORM_HIDDEN}
			<table>
				<tbody>
					<tr>
						<td valign="TOP"><label for="name">Name:</label></td>
						<td>{$FORM_NAME_FIELD}</td>
					</tr>
					<tr>
						<td valign="TOP"><label for="name">Email:</label></td>
						<td><label>{$FORM_EMAIL_FIELD} {$FORM_SUBMIT}</label></td>
					</tr>
					
					
					<!--&IF($FORM_ATTECHMENT_FIELD,'<tr><td valign="TOP"><label><label for="upfile">File:</label></td><td>{$FORM_ATTECHMENT_FIELD}','')-->
					<!--&IF($FORM_NOATTECHMENT_FIELD,'<nobr>[<label>{$FORM_NOATTECHMENT_FIELD}No File</label>]</nobr>','')-->
					<!--&IF($FORM_CONTPOST_FIELD,'<nobr>[<label>{$FORM_CONTPOST_FIELD}Continuous</label>]</nobr>','')-->
					{$FORM_FILE_EXTRA_FIELD}
					<!--&IF($FORM_ATTECHMENT_FIELD,'</td></tr>','')-->
					<!--&IF($FORM_CATEGORY_FIELD,'<tr><td><label for="category">Category:</label></td><td>{$FORM_CATEGORY_FIELD}<small>(Use , to separate)</small></td></tr>','')-->
					{$FORM_EXTRA_COLUMN}
					<tr>
						<td></td>
						<td>{$FORM_COMMENT_FIELD}</td>
					</tr>
					<tr>
						<td colspan="2" id="rules">
							<ul class="rules">
								{$HOOKPOSTINFO}
							</ul>
						</td>
					</tr>
				</tbody>
			</table>
			<!--&IF($FORMBOTTOM,'{$FORMBOTTOM}','')-->
			</td>
			</tr>
			</tbody>
			</table>
			<br clear="ALL">
		</form>
	</div>
	<!--/&POSTFORM-->


	<!--&FOOTER-->
	<a name="bottom"></a>
</body>

</html>
<!--/&FOOTER-->

<!--&ERROR-->
<center>
	<h1 class="error">{$MESG}</h1>
	[<a href="{$SELF2}">{$RETURN_TEXT}</a>]
	[<a href="{$BACK_URL}" onclick="event.preventDefault();history.go(-1);">{$BACK_TEXT}</a>]
	<hr>
</center>
<!--/&ERROR-->

<!--&THREAD-->
<table class="thread {$BOARD_UID}" id="t{$NO}" align="CENTER" width="95%" border="<!--&IF($IS_THREAD,'0','1')-->" cellspacing="7" cellpadding="3"<!--&IF($IS_THREAD,' style="margin:0;width:100%;border:none;padding:0"','')-->>
	<tbody>
		<div class="tnav"><a title="Go to post form" href="#postform">■</a><a title="Go to page top" href="#top">▲</a><a title="Go to page bottom" href="#bottom">▼</a></div>
		<tr>
			<td<!--&IF($IS_THREAD,'','')-->>
				<div class="tnav" align="RIGHT"><small>{$THREADNAV}</small></div>
				<div class="post op" id="p{$NO}">
					<font size="+2"><b class="title"><a href="{$PHP_SELF}?res={$RESTO}">
								<!--&IF($SUB,'{$SUB}','No Title')--></a></b></font>
					<div class="filesize">{$IMG_BAR}</div>
					<!--&IF($IMG_SRC,'{$IMG_SRC}<br clear="ALL">','')-->
					<div class="del" align="RIGHT">[<label>Del:<input type="checkbox" name="{$NO}" value="delete"></label>]</div>
					<dt class="postinfo"><span class="postnum">{$QUOTEBTN}</span> <span class="name">{$NAME}</span> <span class="time">{$NOW}</span>{$POSTINFO_EXTRA}</dt>
					<dd class="body">{$COM}</dd>
					<!--&IF($CATEGORY,'<small class="category"><i>{$CATEGORY_TEXT}{$CATEGORY}</i></small>','')-->
					{$WARN_OLD}{$WARN_BEKILL}{$WARN_ENDREPLY}{$WARN_HIDEPOST}
				</div>
				<!--/&THREAD-->

				<!--&REPLY-->
				<!--&IF($IS_PREVIEW,'<table class="thread" align="CENTER" width="95%" border="1" cellspacing="7" cellpadding="3"><tbody><tr><td>','')-->
				<div class="post reply {$BOARD_UID}" id="p{$BOARD_UID}_{$NO}">
					<div class="filesize">{$IMG_BAR}</div>
					<!--&IF($IMG_SRC,'{$IMG_SRC}<br clear="ALL">','')-->
					<font size="+2"><b class="title"><a href="{$PHP_SELF}?res={$RESTO}#p{$NO}">{$SUB}</a></b></font>
					<div class="del" align="RIGHT">[<label>Del:<input type="checkbox" name="{$NO}" value="delete"></label>]</div>
					<dt class="postinfo"><span class="postnum">{$QUOTEBTN}</span> <span class="name">{$NAME}</span> <span class="time">{$NOW}</span>{$POSTINFO_EXTRA}</dt>
					<dd class="body">{$COM}</dd>
					<!--&IF($CATEGORY,'<small class="category"><i>{$CATEGORY_TEXT}{$CATEGORY}</i></small>','')-->
					{$WARN_BEKILL}
				</div>
				<!--/&REPLY-->

				<!--&SEARCHRESULT-->
				<table class="thread" align="CENTER" width="95%" border="1" cellspacing="7" cellpadding="3">
					<tbody>
						<tr>
							<td>
								<div class="post search">
									<font size="+2"><b class="title">{$SUB}</b></font>
									<dt class="postinfo">{$NO} <span class="name">{$NAME}</span> <span class="time">{$NOW}</span></dt>
									<dd class="body">{$COM}</dd>
									<!--&IF($CATEGORY,'<small class="category"><i>{$CATEGORY_TEXT}{$CATEGORY}</i></small>','')-->
								</div>
							</td>
						</tr>
					</tbody>
				</table>
				<!--&REALSEPARATE/-->
				<!--/&SEARCHRESULT-->

				<!--&THREADSEPARATE-->
			</td>
		</tr>
	</tbody>
</table>
<br clear="all">
<!--/&THREADSEPARATE-->

<!--&REALSEPARATE-->
<br clear="ALL">
<!--/&REALSEPARATE-->

<!--&DELFORM-->
<hr>
<div align="right">
	<table id="userdelete" align="right" cellpadding="0">
		<tbody>
			<tr>
				<td align="right">
					{$DEL_HEAD_TEXT}{$DEL_PASS_FIELD}{$DEL_SUBMIT_BTN}
				</td>
			</tr>
		</tbody>
	</table>
</div>
<!--/&DELFORM-->

<!--&MAIN-->
{$THREADFRONT}
<form name="delform" id="delform" action="{$SELF}" method="post">
	{$THREADS}
	{$THREADREAR}
	<!--&DELFORM/-->
</form>
{$FORMDAT}
<div id="postarea2"></div>
{$PAGENAV}
<br clear="ALL">
<!--/&MAIN-->

<!--&ACCOUNT_PAGE-->
{$HEADER}
	[<a href="{$PHP_SELF2}">Return</a>]
	{$ADMIN_THEADING_BAR}
	<!--&IF($ACCOUNT_LIST,'{$ACCOUNT_LIST}','')-->
	<!--&IF($CREATE_ACCOUNT,'{$CREATE_ACCOUNT}','')-->
	
	{$VIEW_OWN_ACCOUNT}
	
{$FOOTER}
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
				<tr>
					<td class="postblock"><label for="password-reset-confirm"></label></td>
					<td><input type="submit" value="Save"></td>
				</tr>
			</tbody>
		</table>
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
						<tr>
							<td class="postblock"><label for="accountcreateformsubmit"></label></td><td><input id="accountcreateformsubmit" type="submit" value="Create account"></td>
						</tr>
					</tbody>
				</table>
		</form>
<!--/&CREATE_ACCOUNT-->

<!--&BOARD_PAGE-->
{$HEADER}
	{$ADMIN_LINKS}
	{$ADMIN_THEADING_BAR}
	<h2>Create a new board</h2>
	{$CREATE_BOARD}
	<h2>Boards</h2>
	{$BOARD_LIST}
{$FOOTER}
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
						<td class="postblock"><label for="new-board-path">Absolute Directory</label></td>
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
		
		<table  id="board-action-table">
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
{$HEADER}
{$ADMIN_LINKS}
{$ADMIN_THEADING_BAR}
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
{$FOOTER}
<!--/&VIEW_BOARD-->
