<!--&THEMENAME-->Kokonotsuba File Board<!--/&THEMENAME-->
<!--&THEMEVER-->v2.0 (HTML5 overhaul)<!--/&THEMEVER-->
<!--&THEMEAUTHOR-->Heyuri, Hachikuji<!--/&THEMEAUTHOR-->

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
	<link rel="stylesheet" href="{$STATIC_URL}css/kokoimg/base.css?v=133">
	<link class="linkstyle" rel="stylesheet" href="{$STATIC_URL}css/kokoimg/sakomoto.css?v=4" title="Sakomoto">
	<link class="linkstyle" rel="stylesheet alternate" href="{$STATIC_URL}css/kokoimg/heyuriclassic.css?v=5" title="Heyuri Classic">
	<link class="linkstyle" rel="stylesheet alternate" href="{$STATIC_URL}css/kokoimg/futaba.css?v=2" title="Futaba">
	<link class="linkstyle" rel="stylesheet alternate" href="{$STATIC_URL}css/kokoimg/burichan.css?v=4" title="Burichan">
	<link class="linkstyle" rel="stylesheet alternate" href="{$STATIC_URL}css/kokoimg/fuuka.css?v=4" title="Fuuka">
	<link class="linkstyle" rel="stylesheet alternate" href="{$STATIC_URL}css/kokoimg/tomorrow.css?v=4" title="Tomorrow">
	<link class="linkstyle" rel="stylesheet alternate" href="{$STATIC_URL}css/kokoimg/ayashii.css?v=4" title="Ayashii">
	<link class="linkstyle" rel="stylesheet alternate" href="{$STATIC_URL}css/kokoimg/mercury.css?v=4" title="Mercury">
	<link class="linkstyle" rel="stylesheet alternate" href="{$STATIC_URL}css/blank.css?v=2" title="Import custom">
	<script src="{$STATIC_URL}js/koko.js?v=15"></script>
	<script src="{$STATIC_URL}js/onlinecounter.js" defer></script>
	<script src="{$STATIC_URL}js/banners.js?v=3"></script>
	<script src="{$STATIC_URL}js/style.js?v=3"></script>
	<script src="{$STATIC_URL}js/css-vars-ponyfill.js" defer></script>
	<script src="{$STATIC_URL}js/inline.js?v=9" defer></script>
	<script src="{$STATIC_URL}js/addemotes.js?v=8" defer></script>
	<script src="{$STATIC_URL}js/admin.js?v=5" defer></script>
	<script src="{$STATIC_URL}js/qr.js?v=18"></script>
	<script src="{$STATIC_URL}js/ruffle/ruffle.js" defer></script>
	<script src="{$STATIC_URL}js/flashembed.js?v=2"></script>
	<script src="{$STATIC_URL}js/select-all-feature.js?v=4" defer></script>
<!--/&HEADER-->

<!--&TOPLINKS-->
	<div class="boardlist">
		<div class="toplinks">{$TOP_LINKS}</div>
		<div class="adminbar">{$HOME} {$OVERBOARD} {$HOOKLINKS} {$ADMIN}</div>
	</div>
<!--/&TOPLINKS-->

<!--&BODYHEAD-->
<body id="flash">
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
	<tr class="thread" id="t{$BOARD_UID}_{$THREAD_NO}">
		<td><a href="{$SELF}?res={$THREAD_NO}#p{$BOARD_UID}_{$THREAD_NO}" class="no">{$THREAD_NO}</a></td>
		<td class="name">{$NAME}</td>
		<td class="filecol">[<a href="{$FILE_LINK}" download="{$FILE_NAME}">{$FILE_NAME}</a>]</td>
			<td>[<a class="flashboardEmbedText" onclick="openFlashEmbedWindow('{$FILE_LINK}', '{$ESCAPED_FILE_NAME}', {$FILE_WIDTH}, {$FILE_HEIGHT})">Embed</a>]</td>
			<td class="title">{$SUB}</td>
			<td>{$FILE_SIZE}</td>
			<td class="time"> {$NOW} </td>
			<td>{$REPLYNUM}</td>
			<td>{$REPLYBTN}</td>
	</tr>
<!--/&THREAD-->

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
<!--/&THREADSEPARATE-->

<!--&REALSEPARATE-->
		<hr class="realSeparator">
<!--/&REALSEPARATE-->

<!--&DELFORM-->
		<div id="userdelete"></div>
<!--/&DELFORM-->

<!--&MAIN-->
	{$FORMDAT}
	{$THREADFRONT}
	<form name="delform" id="delform" action="{$SELF}" method="post">
		<div class="centerText">
			<table class="flashboardList" id="filelist">
				<thead>
					<tr>
						<th class="postblock">No.</th>
						<th class="postblock">Name</th>
						<th class="postblock">File</th>
						<th class="postblock"></th>
						<th class="postblock">Subject</th>
						<th class="postblock">Size</th>
						<th class="postblock">Date</th>
						<th class="postblock">Replies</th>
						<th class="postblock"></th>
					</tr>
				</thead>				
				<tbody>
					{$THREADS}
				</tbody>
			</table>
			<hr>
			{$THREADREAR}
		</div>
		<!--&DELFORM/-->
	</form>
	<div id="postarea2"></div>
	{$PAGENAV}
<!--/&MAIN-->
