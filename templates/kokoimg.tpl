<!--&THEMENAME-->Kokonotsuba Imageboard
<!--/&THEMENAME-->
<!--&THEMEVER-->
<!--/&THEMEVER-->
<!--&THEMEAUTHOR-->
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
	<link class="linkstyle" rel="stylesheet" type="text/css" href="{$STATIC_URL}css/heyuriclassic.css" title="Heyuri Classic">
	<link class="linkstyle" rel="stylesheet alternate" type="text/css" href="{$STATIC_URL}css/futaba.css" title="Futaba">
	<link class="linkstyle" rel="stylesheet alternate" type="text/css" href="{$STATIC_URL}css/oldheyuri.css" title="Sakomoto">
	<link class="linkstyle" rel="stylesheet alternate" type="text/css" href="{$STATIC_URL}css/burichan.css" title="Burichan">
	<link class="linkstyle" rel="stylesheet alternate" type="text/css" href="{$STATIC_URL}css/base.css" title="Import Custom">
	<link rel="shortcut icon" href="{$STATIC_URL}image/favicon.png">
	<script type="text/javascript" src="{$STATIC_URL}js/koko.js"></script>
	<script type="text/javascript" src="{$STATIC_URL}js/qu.js"></script>
	<script type="text/javascript" src="{$STATIC_URL}js/onlinecounter.js"></script>
	<script type="text/javascript" src="{$STATIC_URL}js/banners.js"></script>
	<script type="text/javascript" src="{$STATIC_URL}js/qu2.js"></script>
	<script type="text/javascript" src="{$STATIC_URL}js/qu3.js" defer></script>
	<script type="text/javascript" src="{$STATIC_URL}js/style.js"></script>
	<script type="text/javascript" src="{$STATIC_URL}js/img.js"></script>
	<script type="text/javascript" src="{$STATIC_URL}js/momo/tegaki.js" defer></script>
	<script type="text/javascript" src="{$STATIC_URL}js/inline.js"></script>
	<script type="text/javascript" src="{$STATIC_URL}js/update.js"></script>
	<script type="text/javascript" src="{$STATIC_URL}js/addemotes.js" defer></script>
	<script type="text/javascript" src="{$STATIC_URL}/js/admin.js" defer></script>
	<script type="text/javascript" src="{$STATIC_URL}js/ruffle/ruffle.js"></script>
	<!--/&HEADER-->

	<!--&TOPLINKS-->
	<div class="boardlist">
		<small class="toplinks">{$TOP_LINKS}</small>
		<div class="adminbar">{$HOME} {$HOOKLINKS} {$ADMIN}</div>
	</div>
	<!--/&TOPLINKS-->

	<!--&BODYHEAD-->

<body>
	<script id="wz_tooltip" type="text/javascript" src="{$STATIC_URL}js/wz_tooltip.js"></script>
	<a name="top"></a>
	<!--&TOPLINKS/-->
	<center id="header">
		<div class="logo">
			<br>
			{$BANNER}
			<h1 class="mtitle">{$TITLE}</h1>
			{$TITLESUB}
			<hr size="1">
		</div>
	</center>
	<!--/&BODYHEAD-->

	<!--&POSTFORM-->
	<div id="postarea">
		<!--&IF($IS_THREAD,'[<a href="{$PHP_SELF2}">Return</a>]','')-->
		<!--&IF($IS_THREAD,' <center class="theading"><b>Posting mode: Reply</b></center>','')-->
		<form id="postform" name="postform" action="{$PHP_SELF}" method="POST" <!--&IF($MAX_FILE_SIZE,' enctype="multipart/form-data"','')-->>
			{$FORM_HIDDEN}
			<center>
					<table cellspacing="2" cellpadding="1">
						<tbody>
							<tr>
								<td class="postblock" align="left"><label for="name"><b>Name</b></label></td>
								<td>{$FORM_NAME_FIELD}</td>
							</tr>
							<tr>
								<td class="postblock" align="left"><label for="email"><b>Email</b></label></td>
								<td>{$FORM_EMAIL_FIELD}</td>
							</tr>
							<tr>
								<td class="postblock" align="left">
									<label for="sub"><b>Subject</b></label></td>
								<td>{$FORM_TOPIC_FIELD}{$FORM_SUBMIT}</td>
							</tr>
							<tr>
								<td class="postblock" align="left">
									<label for="com"><b>Comment</b></label></td>
								<td>{$FORM_COMMENT_FIELD}</td>
							</tr>
							<!--&IF($FORM_ATTECHMENT_FIELD,'<tr>
							<td class="postblock"><label for="upfile"><b>File</b></label></td>
							<td>{$FORM_ATTECHMENT_FIELD}','')-->
							<!--&IF($FORM_CONTPOST_FIELD,'<nobr>[<label>{$FORM_CONTPOST_FIELD}Continuous</label>]</nobr>','')-->
							{$FORM_FILE_EXTRA_FIELD}
							<!--&IF($FORM_ATTECHMENT_FIELD,'</td></tr>','')-->
							<!--&IF($FORM_CATEGORY_FIELD,'<tr><td class="postblock"><label for="category"><b>Category</b></label></td><td>{$FORM_CATEGORY_FIELD}<small></small></td></tr>','')-->
		<tr>
			<td class="postblock"><label for="pwd"><b>Password</b></label></td>
			<td>{$FORM_DELETE_PASSWORD_FIELD}<small>(for deletion, 8 chars max)</small></td>
		</tr>
							{$FORM_EXTRA_COLUMN}
							<tr>
								<td colspan="2" align="LEFT" id="rules">
									<ul class="rules">
										{$FORM_NOTICE}
										<!--&IF($FORM_NOTICE_STORAGE_LIMIT,'{$FORM_NOTICE_STORAGE_LIMIT}','')-->
										{$HOOKPOSTINFO}
									</ul>
								</td>
							</tr>
						</tbody>
					</table>
					<hr size="1">
			</center>
		</form>
	</div>
	<!--&IF($FORMBOTTOM,'{$FORMBOTTOM}','')-->
	<!--/&POSTFORM-->

	 <!--&MODULE_INFO_HOOK-->
	 <div class="mod-extra-info">
		{$BLOTTER}
		{$GLOBAL_MESSAGE}
	</div>
	<!--/&MODULE_INFO_HOOK-->

	<!--&FOOTER-->
	<hr size=1>
	<center class="footer">
		{$FOOTER}
		{$FOOTTEXT}
	</center>
	<a name="bottom"></a>
    </body>
</html>
<!--/&FOOTER-->

<!--&ERROR-->
        <center>
            <h1 class=" error">{$MESG}</h1>
		[<a href="{$SELF2}">{$RETURN_TEXT}</a>]
		[<a href="{$BACK_URL}" onclick="event.preventDefault();history.go(-1);">{$BACK_TEXT}</a>]
		<hr>
		</center>
		<!--/&ERROR-->


		<!--&THREAD-->
		<div class="thread" id="t{$BOARD_UID}_{$NO}">
			{$BOARD_THREAD_NAME}
			<div class="tnav">{$THREADNAV}</div>
			<div class="post op" id="p{$BOARD_UID}_{$NO}">
				<div class="filesize">{$IMG_BAR}</div>
				{$IMG_SRC}
				<span class="postinfo"><label><input type="checkbox" name="{$POST_UID}" value="delete"><big class="title"><b>{$SUB}</b></big> {$NAME_TEXT}<span class="name">{$NAME}</span> <span class="time">{$NOW}</span></label>
					<nobr><span class="postnum">
							<!--&IF($QUOTEBTN,'<a href="{$BOARD_URL}{$SELF}?res={$RESTO}#p{$NO}" class="no">No.</a>{$QUOTEBTN}','<a href="{$BOARD_URL}{$SELF}?res={$RESTO}#p{$NO}">No.{$NO}</a>')--></span>{$POSTINFO_EXTRA} {$REPLYBTN}</nobr>
					<small><i class="backlinks">{$BACKLINKS}</i></small>
				</span>
				<blockquote class="comment">{$COM}</blockquote>
				<!--&IF($CATEGORY,'<small class="category"><i>{$CATEGORY_TEXT}{$CATEGORY}</i></small>','')-->
				{$WARN_OLD}{$WARN_BEKILL}{$WARN_ENDREPLY}{$WARN_HIDEPOST}
			</div>
			<!--/&THREAD-->

			<!--&REPLY-->
			<div id="pc{$BOARD_UID}_{$NO}" class="reply-container">
				<div class="doubledash" valign="top">
					&gt;&gt;
				</div>
				<div class="post reply" id="p{$NO}">
					<div class="postinfo"><label><input type="checkbox" name="{$POST_UID}" value="delete"><big class="title"><b>{$SUB}</b></big> {$NAME_TEXT}<span class="name">{$NAME}</span> <span class="time">{$NOW}</span></label>
					<nobr><span class="postnum">
					<!--&IF($QUOTEBTN,'<a href="{$BOARD_URL}{$SELF}?res={$RESTO}#p{$NO}" class="no">No.</a>{$QUOTEBTN}','<a href="{$BOARD_URL}{$SELF}?res={$RESTO}#p{$NO}">No.{$NO}</a>')--></span>{$POSTINFO_EXTRA}</nobr>
						<small><i class="backlinks">{$BACKLINKS}</i></small>
					</div>
					<div class="filesize">{$IMG_BAR}</div>
						{$IMG_SRC}
						<blockquote class="comment">{$COM}</blockquote>
						<!--&IF($CATEGORY,'<small class="category"><i>{$CATEGORY_TEXT}{$CATEGORY}</i></small>','')-->
						{$WARN_BEKILL}
				</div>
			</div>
			<!--/&REPLY-->

			<!--&SEARCHRESULT-->
			<div class="post op">
				<label><big class="title">{$SUB}</big>
					{$NAME_TEXT}<span class="name">{$NAME}</span>
					<span class="time">{$NOW}</span></label> No.{$NO}
				<blockquote>{$COM}</blockquote>
				<!--&IF($CATEGORY,'<div class="category">{$CATEGORY_TEXT}{$CATEGORY}</div>','')-->
			</div>
			<!--&REALSEPARATE/-->
			<!--/&SEARCHRESULT-->

			<!--&THREADSEPARATE-->
		</div>
		<br clear="ALL">
		<hr>
		<!--/&THREADSEPARATE-->

		<!--&REALSEPARATE-->
		<hr>
		<!--/&REALSEPARATE-->

		<!--&DELFORM-->
		<div align="right">
			<table id="userdelete" align="right" cellpadding="0">
				<tbody>
					<tr>
						<td align="right">
							{$DEL_HEAD_TEXT}[<label>{$DEL_IMG_ONLY_FIELD}{$DEL_IMG_ONLY_TEXT}</label>]<br>
							{$DEL_PASS_TEXT}{$DEL_PASS_FIELD}{$DEL_SUBMIT_BTN}
						</td>
					</tr>
				</tbody>
			</table>
		</div>
		<!--/&DELFORM-->

		<!--&MAIN-->
		{$FORMDAT}
		{$THREADFRONT}
		<form name="delform" id="delform" action="{$SELF}" method="post">
			{$THREADS}
			{$THREADREAR}
			<!--&DELFORM/-->
		</form>
		<div id="postarea2"></div>
		{$PAGENAV}
		<br clear="ALL">
		<!--/&MAIN-->
		
		
<!--&ACCOUNT_PAGE-->
{$HEADER}
	{$ADMIN_LINKS}
	{$ADMIN_THEADING_BAR}
		{$VIEW_OWN_ACCOUNT}
		<!--&IF($CREATE_ACCOUNT,'<li>{$CREATE_ACCOUNT}</li>','')-->
		<!--&IF($ACCOUNT_LIST,'<h3>Staff List</h3>{$ACCOUNT_LIST}','')-->
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
			<table id="account-create-table">
				<tbody>
					<tr>
						<td class="postblock"><label for="new-board-title">Title</label></td>
						<td><input required  id="new-board-title" name="new-board-title"></td> <td>Title of the board.</td>
					</tr>
					<tr>
						<td class="postblock"><label for="new-board-sub-title">Sub-title</label></td>
						<td><input required  id="new-board-sub-title" name="new-board-sub-title"></td> <td>Smaller text beneath the board title on the page, typically providing a description of the board</td>
					</tr>
					<tr>
						<td class="postblock"><label for="new-board-identifier">Identifier</label></td>
						<td><input required id="new-board-identifier" name="new-board-identifier" placeholder="b"></td> <td>The string that represents the board in the URL and file storage. e.g the 'b' in "/b/" or "boards.example.net/b/"</td>
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
					<td> <input required id="edit-board-identifier" name="edit-board-identifier" value="{$BOARD_IDENTIFIER}"></td>
				</tr>
				<tr>
					<td class="postblock"><label for="edit-board-title">Title</label></td>
					<td> <input required id="edit-board-title" name="edit-board-title" value="{$BOARD_TITLE}"></td>
				</tr>
				<tr>
					<td class="postblock"><label for="edit-board-sub-title">Sub-title</label></td>
					<td> <input required id="edit-board-sub-title" name="edit-board-sub-title" value="{$BOARD_SUB_TITLE}"></td>
				</tr>
				<tr>
					<td class="postblock"><label for="edit-board-config-path">Config File</label></td>
					<td> <input id="edit-board-config-path" class="url-input" name="edit-board-config-path" value="{$BOARD_CONFIG_FILE}" required></td>
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
