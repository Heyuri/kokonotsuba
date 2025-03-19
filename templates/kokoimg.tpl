<!--&THEMENAME-->Kokonotsuba Imageboard<!--/&THEMENAME-->
<!--&THEMEVER-->v2.0 (HTML5 overhaul)<!--/&THEMEVER-->
<!--&THEMEAUTHOR-->Heyuri<!--/&THEMEAUTHOR-->

<!--&HEADER-->
<!DOCTYPE html>
<html lang="en-US">

<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>{$PAGE_TITLE}</title>
	<meta name="description" content="{$PAGE_TITLE}">
	<meta name="robots" content="follow,archive">
	<link rel="shortcut icon" href="{$STATIC_URL}image/favicon.png">
	<link rel="stylesheet" href="{$STATIC_URL}css/base.css?v=28">
	<link class="linkstyle" rel="stylesheet" href="{$STATIC_URL}css/sakomoto.css?v=2" title="Sakomoto">
	<link class="linkstyle" rel="stylesheet alternate" href="{$STATIC_URL}css/heyuriclassic.css?v=2" title="Heyuri Classic">
	<link class="linkstyle" rel="stylesheet alternate" href="{$STATIC_URL}css/futaba.css?v=2" title="Futaba">
	<link class="linkstyle" rel="stylesheet alternate" href="{$STATIC_URL}css/burichan.css?v=2" title="Burichan">
	<link class="linkstyle" rel="stylesheet alternate" href="{$STATIC_URL}css/fuuka.css?v=2" title="Fuuka">
	<link class="linkstyle" rel="stylesheet alternate" href="{$STATIC_URL}css/tomorrow.css?v=3" title="Tomorrow">
	<link class="linkstyle" rel="stylesheet alternate" href="{$STATIC_URL}css/ayashii.css?v=2" title="Ayashii">
	<link class="linkstyle" rel="stylesheet alternate" href="{$STATIC_URL}css/mercury.css?v=2" title="Mercury">
	<link class="linkstyle" rel="stylesheet alternate" href="{$STATIC_URL}css/blank.css?v=2" title="Import Custom">
	<script src="{$STATIC_URL}js/koko.js?v=4"></script>
	<script src="{$STATIC_URL}js/qu.js?v=2" defer></script>
	<script src="{$STATIC_URL}js/onlinecounter.js" defer></script>
	<script src="{$STATIC_URL}js/banners.js?v=3"></script>
	<script src="{$STATIC_URL}js/qu2.js?v=2" defer></script>
	<script src="{$STATIC_URL}js/qu3.js?v=19" defer></script>
	<script src="{$STATIC_URL}js/style.js?v=2"></script>
	<script src="{$STATIC_URL}js/css-vars-ponyfill.js" defer></script>
	<script src="{$STATIC_URL}js/img.js?v=2"></script>
	<script src="{$STATIC_URL}js/momo/tegaki.js?v=3" defer></script>
	<script src="{$STATIC_URL}js/inline.js?v=4" defer></script>
	<script src="{$STATIC_URL}js/update.js?v=2" defer></script>
	<script src="{$STATIC_URL}js/addemotes.js?v=7" defer></script>
	<script src="{$STATIC_URL}/js/admin.js?v=3" defer></script>
	<script src="{$STATIC_URL}js/filter.js?v=2" defer></script>
	<script src="{$STATIC_URL}js/qr.js?v=3"></script>
	<script src="{$STATIC_URL}js/clipboard.js?v=15" defer></script>
	<script src="{$STATIC_URL}js/ruffle/ruffle.js" defer></script>
<!--/&HEADER-->

<!--&TOPLINKS-->
	<div class="boardlist">
		<div class="toplinks">{$TOP_LINKS}</div>
		<div class="adminbar">{$HOME} {$OVERBOARD} {$HOOKLINKS} {$ADMIN}</div>
	</div>
<!--/&TOPLINKS-->

<!--&BODYHEAD-->
<body id="img">
	<!-- <script id="wz_tooltip" src="{$STATIC_URL}js/wz_tooltip.js"></script> -->
	<div id="top"></div>
	<!--&TOPLINKS/-->
	<div class="logo">
		{$BANNER}
		<h1 class="mtitle">{$TITLE}</h1>
		<div class="subtitle">{$TITLESUB}</div>
		<hr class="hrThin">
	</div>
<!--/&BODYHEAD-->

<!--&POSTFORM-->
	<div id="postarea">
		<!--&IF($IS_THREAD,'[<a href="{$PHP_SELF2}">Return</a>]','')-->
		<!--&IF($IS_THREAD,' <h2 class="theading">Posting mode: Reply</h2>','')-->
		<form id="postform" name="postform" action="{$PHP_SELF}" method="POST" <!--&IF($MAX_FILE_SIZE,' enctype="multipart/form-data"','')-->>
			{$FORM_HIDDEN}
			<table id="postformTable">
				<tbody>
					<tr>
						<td class="postblock"><label for="name">Name</label></td>
						<td class="postformInputCell">{$FORM_NAME_FIELD}</td>
					</tr>
					<tr>
						<td class="postblock"><label for="email">Email</label></td>
						<td class="postformInputCell">{$FORM_EMAIL_FIELD}</td>
					</tr>
					<tr>
						<td class="postblock">
							<label for="sub">Subject</label></td>
						<td class="postformInputCell">{$FORM_TOPIC_FIELD}{$FORM_SUBMIT}</td>
					</tr>
					<tr>
						<td class="postblock">
							<label for="com">Comment</label></td>
						<td class="postformInputCell">{$FORM_COMMENT_FIELD}</td>
					</tr>
					<!--&IF($FORM_ATTECHMENT_FIELD,'<tr>
						<td class="postblock"><label for="upfile">File</label></td>
						<td class="postformInputCell">{$FORM_ATTECHMENT_FIELD}
							<div id="postformFileOptionsContainer">','')-->
								<!--&IF($FORM_CONTPOST_FIELD,'<div id="continuousContainer"><label id="continuousLabel">{$FORM_CONTPOST_FIELD}Continuous</label></div>','')-->
								<!--&IF($FORM_ATTECHMENT_FIELD,'
							{$FORM_FILE_EXTRA_FIELD}
							</div>
						</td>
					</tr>','')-->
					<!--&IF($FORM_CATEGORY_FIELD,'<tr>
						<td class="postblock"><label for="category">Category</label></td>
						<td class="postformInputCell">{$FORM_CATEGORY_FIELD}<small></small></td>
					</tr>','')-->
					<tr>
						<td class="postblock"><label for="pwd">Password</label></td>
						<td class="postformInputCell">{$FORM_DELETE_PASSWORD_FIELD}<span id="delPasswordInfo">(for deletion, 8 chars max)</span>{$FORM_EXTRA_COLUMN}</td>
					</tr>
					<tr>
						<td id="rules" colspan="2">
							<ul class="rules">
								{$FORM_NOTICE}
								<!--&IF($FORM_NOTICE_STORAGE_LIMIT,'{$FORM_NOTICE_STORAGE_LIMIT}','')-->
								{$HOOKPOSTINFO}
							</ul>
						</td>
					</tr>
				</tbody>
			</table>
			<hr>
		</form>
	</div>
	<!--&IF($FORMBOTTOM,'{$FORMBOTTOM}','')-->
<!--/&POSTFORM-->

<!--&MODULE_INFO_HOOK-->
	<div class="mod-extra-info">
		{$BLOTTER}
		<hr>
		<div id="globalmsg">
			{$GLOBAL_MESSAGE}
		</div>
		<hr>
	</div>
<!--/&MODULE_INFO_HOOK-->

<!--&FOOTER-->
	<div id="footer">
		{$FOOTER}
		<div id="footerText">{$FOOTTEXT}</div>
	</div>
	<div id="bottom"></div>
</body>
</html>
<!--/&FOOTER-->

<!--&ERROR-->
	<div class="centerText">
		<h2 class=" error">{$MESG}</h2>
		<p>
			[<a href="{$SELF2}">{$RETURN_TEXT}</a>]
			[<a href="{$BACK_URL}" onclick="event.preventDefault();history.go(-1);">{$BACK_TEXT}</a>]
		</p>
		<hr>
	</div>
<!--/&ERROR-->

<!--&THREAD-->
		<div class="thread" id="t{$BOARD_UID}_{$NO}">
			{$BOARD_THREAD_NAME}
			<div class="tnav">{$THREADNAV}</div>
			<div class="post op" id="p{$BOARD_UID}_{$NO}">
				<div class="filesize">{$IMG_BAR}</div>
				{$IMG_SRC}
				<div class="postinfo">
					<label>
						<input type="checkbox" name="{$POST_UID}" value="delete"><span class="title">{$SUB}</span>
						<span class="nameContainer">
							<!--{$NAME_TEXT}--><span class="name">{$NAME}</span>
						</span>
						<span class="time">{$NOW}</span>
					</label>
					<span class="postnum"><!--&IF($QUOTEBTN,'<a href="{$BOARD_URL}{$SELF}?res={$RESTO}#p{$BOARD_UID}_{$NO}" class="no">No.</a>{$QUOTEBTN}','<a href="{$BOARD_URL}{$SELF}?res={$RESTO}#p{$BOARD_UID}_{$NO}">No.{$NO}</a>')--></span>
					<span class="postInfoExtra">{$POSTINFO_EXTRA}</span>
					<span class="replyButton">{$REPLYBTN}</span><span class="backlinks">{$BACKLINKS}</span>
				</div>
				<div class="comment">{$COM}</div>
				<!--&IF($CATEGORY,'<small class="category"><i>{$CATEGORY_TEXT}{$CATEGORY}</i></small>','')-->
				{$WARN_OLD}{$WARN_BEKILL}{$WARN_ENDREPLY}{$WARN_HIDEPOST}
			</div>
<!--/&THREAD-->

<!--&REPLY-->
			<div id="pc{$BOARD_UID}_{$NO}" class="reply-container">
				<div class="doubledash">
					&gt;&gt;
				</div>
				<div class="post reply" id="p{$BOARD_UID}_{$NO}">
					<div class="postinfo">
						<label>
							<input type="checkbox" name="{$POST_UID}" value="delete"><span class="title">{$SUB}</span>
							<span class="nameContainer">
								<!--{$NAME_TEXT}--><span class="name">{$NAME}</span>
							</span>
							<span class="time">{$NOW}</span>
						</label>
						<span class="postnum"><!--&IF($QUOTEBTN,'<a href="{$BOARD_URL}{$SELF}?res={$RESTO}#p{$BOARD_UID}_{$NO}" class="no">No.</a>{$QUOTEBTN}','<a href="{$BOARD_URL}{$SELF}?res={$RESTO}#p{$BOARD_UID}_{$NO}">No.{$NO}</a>')--></span>
						<span class="postInfoExtra">{$POSTINFO_EXTRA}</span><span class="backlinks">{$BACKLINKS}</span>
					</div>
					<div class="filesize">{$IMG_BAR}</div>
					{$IMG_SRC}
					<div class="comment">{$COM}</div>
					<!--&IF($CATEGORY,'<small class="category"><i>{$CATEGORY_TEXT}{$CATEGORY}</i></small>','')-->
					{$WARN_BEKILL}
				</div>
			</div>
<!--/&REPLY-->

<!--&SEARCHRESULT-->
		<div class="post op">
			<div class="postinfo">
				<span class="title">{$SUB}</span>
				<span class="nameContainer">
					<!--{$NAME_TEXT}--><span class="name">{$NAME}</span>
				</span>
				<span class="time">{$NOW}</span>
				<span class="postnum">No.{$NO}</span>
			</div>
			<div class="comment">{$COM}</div>
			<!--&IF($CATEGORY,'<div class="category">{$CATEGORY_TEXT}{$CATEGORY}</div>','')-->
		</div>
		<!--&REALSEPARATE/-->
<!--/&SEARCHRESULT-->

<!--&THREADSEPARATE-->
		</div>
		<hr class="threadSeparator">
<!--/&THREADSEPARATE-->

<!--&REALSEPARATE-->
		<hr class="realSeparator">
<!--/&REALSEPARATE-->

<!--&DELFORM-->
		<div id="userdelete">
			<div id="fileOnlyRow">{$DEL_HEAD_TEXT}[<label>{$DEL_IMG_ONLY_FIELD}{$DEL_IMG_ONLY_TEXT}</label>]</div>
			<div id="passwordRow"><label>{$DEL_PASS_TEXT}{$DEL_PASS_FIELD}</label>{$DEL_SUBMIT_BTN}</div>
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
	{$PAGENAV}
	<div id="postarea2"></div>
<!--/&MAIN-->

<!--&ACCOUNT_PAGE-->
{$HEADER}
	{$ADMIN_LINKS}
	{$ADMIN_THEADING_BAR}
		{$VIEW_OWN_ACCOUNT}
		{$CREATE_ACCOUNT}
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