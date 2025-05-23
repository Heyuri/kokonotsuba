<!--&THEMENAME-->Kokonotsuba Textboard: Reply<!--/&THEMENAME-->
<!--&THEMEVER-->v2.0<!--/&THEMEVER-->
<!--&THEMEAUTHOR-->Heyuri (original by Deadking)<!--/&THEMEAUTHOR-->

<!--&HEADER-->
<!DOCTYPE html>
<html lang="en-US">

<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>{$PAGE_TITLE}</title>
	<meta name="robots" content="follow,archive">
	<link rel="shortcut icon" href="{$STATIC_URL}image/favicon.png">
	<link rel="stylesheet" href="{$STATIC_URL}css/kokotxt/base.css?v=97">
	<link class="linkstyle" rel="stylesheet" href="{$STATIC_URL}css/kokotxt/pseud0ch.css?v=8" title="Pseud0ch">
	<link class="linkstyle" rel="stylesheet alternate" href="{$STATIC_URL}css/kokotxt/pseud0ch2.css?v=7" title="Pseud0ch (serif)">
	<link class="linkstyle" rel="stylesheet alternate" href="{$STATIC_URL}css/kokotxt/pseud0ch3.css?v=7" title="Pseud0ch (sans-serif)">
	<link class="linkstyle" rel="stylesheet alternate" href="{$STATIC_URL}css/kokotxt/tomorrow.css?v=10" title="Tomorrow">
	<link class="linkstyle" rel="stylesheet alternate" href="{$STATIC_URL}css/kokotxt/headline.css?v=37" title="Headline">
	<link class="linkstyle" rel="stylesheet alternate" href="{$STATIC_URL}css/kokotxt/vipper.css?v=2" title="VIPPER">
	<link class="linkstyle" rel="stylesheet alternate" href="{$STATIC_URL}css/blank.css?v=2" title="Import custom">
	<script src="{$STATIC_URL}js/koko.js?v=15"></script>
	<script src="{$STATIC_URL}js/qr.js?v=18"></script>
	<script src="{$STATIC_URL}js/qu.js?v=2" defer></script>
	<script src="{$STATIC_URL}js/qu2.js?v=2" defer></script>
	<script src="{$STATIC_URL}js/qu3.js?v=23" defer></script>
	<script src="{$STATIC_URL}js/style.js?v=3"></script>
	<script src="{$STATIC_URL}js/css-vars-ponyfill.js" defer></script>
	<script src="{$STATIC_URL}js/filter.js?v=16" defer></script>
	<script src="{$STATIC_URL}js/catalog.js"></script>
	<script src="{$STATIC_URL}js/insert.js"></script>
	<script src="{$STATIC_URL}js/update-txt.js" defer></script>
	<script src="{$STATIC_URL}js/addemotestxt.js?v=35" defer></script>
	<script src="{$STATIC_URL}js/admin.js?v=5" defer></script>
	<script src="{$STATIC_URL}js/select-all-feature.js?v=4" defer></script>
	<!--&IF($IS_STAFF,'<script src="{$STATIC_URL}js/admin-frontend-enhancements.js" defer></script>','')-->
<!--/&HEADER-->

<!--&TOPLINKS-->
	<div class="boardlist"<!--&IF($IS_THREAD,' style="display:none"','')-->>
		<div class="toplinks">{$TOP_LINKS}</div>
		<div class="adminbar">{$HOME} {$OVERBOARD} {$HOOKLINKS} {$ADMIN}</div>
	</div>
<!--/&TOPLINKS-->

<!--&BODYHEAD-->
<body id="txtreply">
	<!--&TOPLINKS/-->
	<div class="threadNavBar">[<a href="{$PHP_SELF2}">Return</a>] {$HOME}</div>
	<hr>
<!--/&BODYHEAD-->

<!--&POSTFORM-->
	<div id="postarea">
		<!--&IF($MAX_FILE_SIZE,'<form id="postform" name="postform" action="{$PHP_SELF}" method="POST" enctype="multipart/form-data">','<form id="postform" name="postform" action="{$PHP_SELF}" method="POST">')-->
			<h2 id="newReplyTitle"><!--&IF($IS_THREAD,'New reply','New thread')--></h2>
			{$FORM_HIDDEN}
			<div id="postformTable">
				<div id="rowPostNameEmail" class="postformCombinedItems">
					<div class="postformItem"><label for="name">Name:</label>{$FORM_NAME_FIELD}</div>
					<div class="postformItem"><label for="email">Email:</label>{$FORM_EMAIL_FIELD}</div>
					<div class="postformItem">{$FORM_SUBMIT}</div>
				</div>
				<!--&IF($FORM_ATTECHMENT_FIELD,'<div class="postformItem"><label for="upfile">File:</label>{$FORM_ATTECHMENT_FIELD}','')-->
					<!--&IF($FORM_NOATTECHMENT_FIELD,'<span class="nowrap">[<label>{$FORM_NOATTECHMENT_FIELD}No File</label>]</span>','')-->
					<!--&IF($FORM_CONTPOST_FIELD,'<span class="nowrap">[<label>{$FORM_CONTPOST_FIELD}Continuous</label>]</span>','')-->
					{$FORM_FILE_EXTRA_FIELD}
				<!--&IF($FORM_ATTECHMENT_FIELD,'</div>','')-->
				<!--&IF($FORM_CATEGORY_FIELD,'<div class="postformItem"><label for="category">Category:</label>{$FORM_CATEGORY_FIELD}<small>(Use , to separate)</small></div>','')-->
				<div class="postformItem"><label for="com">Comment:</label><div class="commentArea">{$FORM_COMMENT_FIELD}</div></div>
				<div class="postformItem"><label for="pwd">Password:</label>{$FORM_DELETE_PASSWORD_FIELD}<span id="delPasswordInfo">(for deletion, 8 chars max)</span></div>
				<div class="postformItem">{$FORM_EXTRA_COLUMN}</div>
			</div>
			<!--&IF($FORMBOTTOM,'{$FORMBOTTOM}','')-->
		</form>
	</div>
<!--/&POSTFORM-->


<!--&FOOTER-->
	<div id="footer">
		{$FOOTER}
		{$FOOTTEXT}
	</div>
	<div id="bottom"></div>
</body>
</html>
<!--/&FOOTER-->

<!--&ERROR-->
<div class="centerText">
	<h1 class="error">{$MESG}</h1>
	[<a href="{$SELF2}">{$RETURN_TEXT}</a>]
	[<a href="{$BACK_URL}" onclick="event.preventDefault();history.go(-1);">{$BACK_TEXT}</a>]
	<hr>
</div>
<!--/&ERROR-->

<!--&THREAD-->
	<div class="thread" id="t{$BOARD_UID}_{$THREAD_NO}">
		<div class="tnav">{$THREADNAV}</div>
		{$THREAD_OP}
		{$REPLIES}
	</div>
<!--/&THREAD-->

<!--&OP-->
	<div class="post op" id="p{$BOARD_UID}_{$NO}">
		<h1 class="title"><a href="{$PHP_SELF}?res={$RESTO}"><!--&IF($SUB,'{$SUB}','No subject')--></a></h1>
		<div class="del">[<label>Del:<input type="checkbox" name="{$POST_UID}" value="delete"></label>]</div>
		<div class="postinfo"><span class="postnum">{$QUOTEBTN}</span> <span class="nameContainer">{$NAME_TEXT}<span class="name">{$NAME}</span></span> <span class="time">{$NOW}</span><span class="postInfoExtra">{$POSTINFO_EXTRA}</span></div>
		<div class="filesize">{$IMG_BAR}</div>
		<!--&IF($IMG_SRC,'{$IMG_SRC}','')-->
		<div class="comment">{$COM}</div>
		<!--&IF($CATEGORY,'<small class="category"><i>{$CATEGORY_TEXT}{$CATEGORY}</i></small>','')-->
		{$WARN_OLD}{$WARN_BEKILL}{$WARN_ENDREPLY}{$WARN_HIDEPOST}
	</div>
<!--/&OP-->

<!--&REPLY-->
<!--&IF($IS_PREVIEW,'<table class="thread" align="CENTER" width="95%" border="1" cellspacing="7" cellpadding="3"><tbody><tr><td>','')-->
<div class="post reply" id="p{$BOARD_UID}_{$NO}">
	<span class="title"><a href="{$PHP_SELF}?res={$RESTO}#p{$BOARD_UID}_{$NO}">{$SUB}</a></span>
	<div class="del">[<label>Del:<input type="checkbox" name="{$POST_UID}" value="delete"></label>]</div>
	<div class="postinfo"><span class="postnum">{$QUOTEBTN}</span> <span class="nameContainer">{$NAME_TEXT}<span class="name">{$NAME}</span></span> <span class="time">{$NOW}</span><span class="postInfoExtra">{$POSTINFO_EXTRA}</span></div>
	<div class="filesize">{$IMG_BAR}</div>
	<!--&IF($IMG_SRC,'{$IMG_SRC}','')-->
	<div class="comment">{$COM}</div>
	<!--&IF($CATEGORY,'<small class="category"><i>{$CATEGORY_TEXT}{$CATEGORY}</i></small>','')-->
	{$WARN_BEKILL}
</div>
<!--/&REPLY-->

<!--&SEARCHRESULT-->
	<div class="thread outerbox">
		<div class="post search innerbox">
			<span class="title">{$SUB}</span>
			<div class="postinfo"><span class="postnum">{$NO}</span> <span class="nameContainer">{$NAME_TEXT}<span class="name">{$NAME}</span></span> <span class="time">{$NOW}</span></div>
			<div class="comment">{$COM}</div>
			<!--&IF($CATEGORY,'<small class="category"><i>{$CATEGORY_TEXT}{$CATEGORY}</i></small>','')-->
		</div>
	</div>
	<!--&REALSEPARATE/-->
<!--/&SEARCHRESULT-->

<!--&THREADSEPARATE-->
<!--/&THREADSEPARATE-->

<!--&REALSEPARATE-->
<!--/&REALSEPARATE-->

<!--&DELFORM-->
	<div id="userdelete">
		<div id="passwordRow"><label>{$DEL_HEAD_TEXT}{$DEL_PASS_FIELD}</label>{$DEL_SUBMIT_BTN}</div>
	</div>
<!--/&DELFORM-->

<!--&MAIN-->
	{$THREADFRONT}
	<form name="delform" id="delform" action="{$SELF}" method="post">
		{$THREADS}
		<hr>
		{$THREADREAR}
		<!--&DELFORM/-->
	</form>
	{$PAGENAV}
	{$FORMDAT}
<!--/&MAIN-->
