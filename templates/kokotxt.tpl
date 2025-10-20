<!--&THEMENAME-->Kokonotsuba Textboard<!--/&THEMENAME-->
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
	<link class="linkstyle" rel="stylesheet alternate" href="{$STATIC_URL}css/kokotxt/gochannel.css?v=1" title="Gochannel">
	<link class="linkstyle" rel="stylesheet alternate" href="{$STATIC_URL}css/kokotxt/tomorrow.css?v=10" title="Tomorrow">
	<link class="linkstyle" rel="stylesheet alternate" href="{$STATIC_URL}css/kokotxt/ayashii.css?v=1" title="Ayashii">
	<link class="linkstyle" rel="stylesheet alternate" href="{$STATIC_URL}css/kokotxt/bluemoon.css?v=1" title="Blue Moon">
	<link class="linkstyle" rel="stylesheet alternate" href="{$STATIC_URL}css/kokotxt/futaba.css?v=1" title="Futaba">
	<link class="linkstyle" rel="stylesheet alternate" href="{$STATIC_URL}css/kokotxt/headline.css?v=37" title="Headline">
	<link class="linkstyle" rel="stylesheet alternate" href="{$STATIC_URL}css/kokotxt/mercury.css?v=1" title="Mercury">
	<link class="linkstyle" rel="stylesheet alternate" href="{$STATIC_URL}css/kokotxt/toothpaste.css?v=1" title="Toothpaste">
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
	<script src="{$STATIC_URL}js/addemotestxt.js?v=35" defer></script>
	<script src="{$STATIC_URL}js/admin.js?v=5" defer></script>
	<script src="{$STATIC_URL}js/select-all-feature.js?v=4" defer></script>
	<script src="{$STATIC_URL}js/message.js" defer></script>
	<!--&IF($MODULE_HEADER_HTML,'{$MODULE_HEADER_HTML}','')-->
	
<!--/&HEADER-->

<!--&TOPLINKS-->
	<div class="boardlist">
		<div class="toplinks">{$TOP_LINKS}</div>
		<div class="adminbar">{$HOME} {$OVERBOARD} {$HOOKLINKS} {$ADMIN}</div>
	</div>
<!--/&TOPLINKS-->

<!--&BODYHEAD-->
<body id="txt">
	<!-- <script id="wz_tooltip" src="{$STATIC_URL}js/wz_tooltip.js"></script> -->
	<div id="top"></div>
	<!--&TOPLINKS/-->
	<div id="titleBox" class="menu outerbox">
		<div class="innerbox">
			<!-- {$BANNER} -->
			<h1 class="mtitle">{$TITLE}</h1>
			<div class="subtitle">{$TITLESUB}</div>
		</div>
	</div>
<!--/&BODYHEAD-->

<!--&POST_AREA-->
	<div id="postarea" class="menu outerbox">
		<!--&IF($POST_FORM,'{$POST_FORM}','')-->
		<!--&IF($MODULE_INFO_HOOK,'{$MODULE_INFO_HOOK}','')-->
	</div>
<!--/&POST_AREA-->

<!--&POSTFORM-->
		<div id="postformBox" class="innerbox">
			<!--&IF($MAX_FILE_SIZE,'<form id="postform" name="postform" action="{$LIVE_INDEX_FILE}" method="POST" enctype="multipart/form-data">','<form id="postform" name="postform" action="{$LIVE_INDEX_FILE}" method="POST">')-->
				<h2 class="formTitle"><!--&IF($IS_THREAD,' New reply [<a href="{$STATIC_INDEX_FILE}">Return</a>]','New thread')--></h2>
				{$FORM_HIDDEN}
				<div id="postformTable">
					<div class="postformItem"><label for="sub">Topic:</label>{$FORM_TOPIC_FIELD}{$FORM_SUBMIT}</div>
					<div class="postformCombinedItems">
						<div class="postformItem"><label for="name">Name:</label>{$FORM_NAME_FIELD}</div>
						<div class="postformItem"><label for="email">Email:</label>{$FORM_EMAIL_FIELD}</div>
					</div>
					<!--&IF($FORM_ATTECHMENT_FIELD,'<div class="postformItem"><label for="upfile">File:</label>{$FORM_ATTECHMENT_FIELD}','')-->
					<!--&IF($FORM_NOATTECHMENT_FIELD,'<span class="nowrap">[<label>{$FORM_NOATTECHMENT_FIELD}No File</label>]</span>','')-->
					<!--&IF($FORM_CONTPOST_FIELD,'<span class="nowrap">[<label>{$FORM_CONTPOST_FIELD}Continuous</label>]</span>','')-->
					{$FORM_FILE_EXTRA_FIELD}
					<!--&IF($FORM_ATTECHMENT_FIELD,'</div>','')-->
					<!--&IF($FORM_CATEGORY_FIELD,'<div class="postformItem"><label for="category">Category:</label>{$FORM_CATEGORY_FIELD}<small>(Use , to separate)</small></div>','')-->
					<div class="postformItem"><label for="com">Comment:</label>
						<div class="commentArea">{$FORM_COMMENT_FIELD}</div>
					</div>
					<div class="postformItem"><label for="pwd">Password:</label><input type="password" name="pwd" id="pwd" value="" class="inputtext" maxlength="{$INPUT_MAX}"><span id="delPasswordInfo">(for deletion)</span></div>
					<div class="postformItem">{$FORM_EXTRA_COLUMN}</div>
					<div id="rules">
						<ul class="rules">
							{$FORM_NOTICE}
							<!--&IF($FORM_NOTICE_STORAGE_LIMIT,'{$FORM_NOTICE_STORAGE_LIMIT}','')-->
							{$HOOKPOSTINFO}
						</ul>
					</div>
				</div>
			</form>
		</div>
<!--/&POSTFORM-->

<!--&MODULE_INFO_HOOK-->
		<div class="mod-extra-info innerbox">
			{$BLOTTER}
		</div>
		<!--&IF($GLOBAL_MESSAGE,'<div id="globalmsg" class="innerbox">{$GLOBAL_MESSAGE}</div>','')-->
<!--/&MODULE_INFO_HOOK-->

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
	<div class="centerBlock">
		<h1 class="error">{$MESG}</h1>
		[<a href="{$SELF2}">{$RETURN_TEXT}</a>]
		[<a href="{$BACK_URL}" onclick="event.preventDefault();history.go(-1);">{$BACK_TEXT}</a>]
		<hr>
	</div>
<!--/&ERROR-->

<!--&THREAD-->
	<div class="thread<!--&IF($MODULE_THREAD_CSS_CLASSES,'{$MODULE_THREAD_CSS_CLASSES}','')-->" id="t{$BOARD_UID}_{$THREAD_NO}">
		<div class="innerbox">
			{$BOARD_THREAD_NAME}
			<div class="tnav">{$THREADNAV}</div>
			{$THREAD_OP}
			<div class="repliesOmitted"></div>
			<div class="latestReplies">
				{$REPLIES}
			</div>
		</div>
	</div>
<!--/&THREAD-->

<!--&OP-->
	<h2 class="title"><a href="{$BOARD_URL}{$LIVE_INDEX_FILE}?res={$RESTO}"><!--&IF($SUB,'{$SUB}','No subject')--></a></h2>
	<div class="post op<!--&IF($MODULE_POST_CSS_CLASSES,'{$MODULE_POST_CSS_CLASSES}','')-->" id="p{$BOARD_UID}_{$NO}">
		<div class="del">[<label>Del:<input type="checkbox" name="{$POST_UID}" value="delete"></label>]</div>
		<div class="postinfo"><span class="postnum">{$QUOTEBTN}</span> <span class="nameContainer">{$NAME_TEXT}<span class="name">{$NAME}</span></span> <span class="time">{$NOW}</span> <!--&IF($POSTER_HASH,'<span class="idContainer">ID:{$POSTER_HASH}</span>','')--> <span class="postInfoExtra">{$POSTINFO_EXTRA}</span></div>
		<div class="imageSourceContainer<!--&IF($MODULE_ATTACHMENT_CSS_CLASSES,'{$MODULE_ATTACHMENT_CSS_CLASSES}','')-->">
			<!--&IF($IMG_BAR,'<div class="filesize">{$IMG_BAR}</div>','')-->
			<!--&IF($IMG_SRC,'{$IMG_SRC}','')-->
		</div>
		<div class="comment">{$COM}</div>
		<!--&IF($CATEGORY,'<small class="category"><i>{$CATEGORY_TEXT}{$CATEGORY}</i></small>','')-->
		<div class="warningsSection">{$WARN_OLD}{$WARN_BEKILL}{$WARN_ENDREPLY}{$WARN_HIDEPOST}</div>
	</div>
<!--/&OP-->

<!--&REPLY-->
			<!--&IF($IS_PREVIEW,'<table class="thread"><tbody><tr><td>','')-->
			<div class="post reply<!--&IF($MODULE_POST_CSS_CLASSES,'{$MODULE_POST_CSS_CLASSES}','')-->" id="p{$BOARD_UID}_{$NO}"><span class="title"><a href="{$BOARD_URL}{$LIVE_INDEX_FILE}?res={$RESTO}#p{$BOARD_UID}_{$NO}">{$SUB}</a></span>
				<div class="del">[<label>Del:<input type="checkbox" name="{$POST_UID}" value="delete"></label>]</div>
				<div class="postinfo">
				<!--&IF($POST_POSITION_ENABLED,'<span class="replyPosition">{$POST_POSITION}</span>','')--> <span class="postnum">{$QUOTEBTN}</span> <span class="nameContainer">{$NAME_TEXT}<span class="name">{$NAME}</span></span> <span class="time">{$NOW}</span> <!--&IF($POSTER_HASH,'<span class="idContainer">ID:{$POSTER_HASH}</span>','')--> <span class="postInfoExtra">{$POSTINFO_EXTRA}</span></div>
				<div class="imageSourceContainer<!--&IF($MODULE_ATTACHMENT_CSS_CLASSES,'{$MODULE_ATTACHMENT_CSS_CLASSES}','')-->">
					<div class="filesize">{$IMG_BAR}</div>
					<!--&IF($IMG_SRC,'{$IMG_SRC}','')-->
				</div>
				<div class="comment">{$COM}</div>
				<!--&IF($CATEGORY,'<small class="category"><i>{$CATEGORY_TEXT}{$CATEGORY}</i></small>','')-->
				<div class="warningsSection">{$WARN_BEKILL}</div>
			</div>
<!--/&REPLY-->

<!--&SEARCHRESULT-->
	<div class="thread">
		<div class="post search">
			<span class="title">{$SUB}</span>
			<div class="postinfo"><span class="postnum">{$NO}</span> <span class="nameContainer">{$NAME_TEXT}<span class="name">{$NAME}</span></span> <span class="time">{$NOW}</span> <!--&IF($POSTER_HASH,'<span class="idContainer">ID:{$POSTER_HASH}</span>','')--> </div>
			<div class="comment">{$COM}</div>
			<!--&IF($CATEGORY,'<small class="category"><i>{$CATEGORY_TEXT}{$CATEGORY}</i></small>','')-->
		</div>
	</div>
	<!--&REALSEPARATE/-->
<!--/&SEARCHRESULT-->

<!--&THREADSEPARATE-->
	<hr class="threadSeparator">
<!--/&THREADSEPARATE-->

<!--&REALSEPARATE-->
<!--/&REALSEPARATE-->

<!--&DELFORM-->
	<div id="userdelete">
		<div id="passwordRow"><label>{$DEL_HEAD_TEXT}<input type="hidden" name="func" value="delete"> <input type="password" class="inputtext" name="pwd" id="pwd2" value=""></label>{$DEL_SUBMIT_BTN}</div>
	</div>
<!--/&DELFORM-->

<!--&MAIN-->
	{$FORMDAT}
	{$THREADFRONT}
	<form name="delform" id="delform" action="{$LIVE_INDEX_FILE}" method="post">
		{$THREADS}
		{$THREADREAR}
		<!--&DELFORM/-->
	</form>
	{$PAGENAV}
	<div id="postarea2"></div>
<!--/&MAIN-->
