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
	<link rel="stylesheet" href="{$STATIC_URL}css/kokoimg/base.css?v=139">
	<link class="linkstyle" rel="stylesheet" href="{$STATIC_URL}css/kokoimg/sakomoto.css?v=4" title="Sakomoto">
	<link class="linkstyle" rel="stylesheet alternate" href="{$STATIC_URL}css/kokoimg/heyuriclassic.css?v=5" title="Heyuri Classic">
	<link class="linkstyle" rel="stylesheet alternate" href="{$STATIC_URL}css/kokoimg/futaba.css?v=2" title="Futaba">
	<link class="linkstyle" rel="stylesheet alternate" href="{$STATIC_URL}css/kokoimg/burichan.css?v=4" title="Burichan">
	<link class="linkstyle" rel="stylesheet alternate" href="{$STATIC_URL}css/kokoimg/fuuka.css?v=4" title="Fuuka">
	<link class="linkstyle" rel="stylesheet alternate" href="{$STATIC_URL}css/kokoimg/gurochan.css?v=29" title="Gurochan">
	<link class="linkstyle" rel="stylesheet alternate" href="{$STATIC_URL}css/kokoimg/photon.css?v=26" title="Photon">
	<link class="linkstyle" rel="stylesheet alternate" href="{$STATIC_URL}css/kokoimg/tomorrow.css?v=5" title="Tomorrow">
	<link class="linkstyle" rel="stylesheet alternate" href="{$STATIC_URL}css/kokoimg/ayashii.css?v=5" title="Ayashii">
	<link class="linkstyle" rel="stylesheet alternate" href="{$STATIC_URL}css/kokoimg/mercury.css?v=5" title="Mercury">
	<link class="linkstyle" rel="stylesheet alternate" href="{$STATIC_URL}css/blank.css?v=2" title="Import custom">
	<script src="{$STATIC_URL}js/koko.js?v=15"></script>
	<script src="{$STATIC_URL}js/qu.js?v=2" defer></script>
	<script src="{$STATIC_URL}js/onlinecounter.js" defer></script>
	<script src="{$STATIC_URL}js/banners.js?v=3"></script>
	<script src="{$STATIC_URL}js/qu2.js?v=2" defer></script>
	<script src="{$STATIC_URL}js/qu3.js?v=23" defer></script>
	<script src="{$STATIC_URL}js/style.js?v=3"></script>
	<script src="{$STATIC_URL}js/css-vars-ponyfill.js" defer></script>
	<script src="{$STATIC_URL}js/img.js?v=4"></script>
	<script src="{$STATIC_URL}js/momo/tegaki.js?v=5" defer></script>
	<script src="{$STATIC_URL}js/inline.js?v=9" defer></script>
	<script src="{$STATIC_URL}js/update.js?v=2" defer></script>
	<script src="{$STATIC_URL}js/addemotes.js?v=9" defer></script>
	<script src="{$STATIC_URL}js/admin.js?v=5" defer></script>
	<script src="{$STATIC_URL}js/filter.js?v=16" defer></script>
	<script src="{$STATIC_URL}js/qr.js?v=18"></script>
	<script src="{$STATIC_URL}js/clipboard.js?v=15" defer></script>
	<script src="{$STATIC_URL}js/ruffle/ruffle.js" defer></script>
	<script src="{$STATIC_URL}js/select-all-feature.js?v=4" defer></script>
	<!--&IF($IS_STAFF,'<script src="{$STATIC_URL}js/admin-frontend-enhancements.js" defer></script>','')-->
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

<!--&POST_AREA-->
	<div id="postarea">
		<!--&IF($POST_FORM,'{$POST_FORM}','')-->
		<!--&IF($MODULE_INFO_HOOK,'{$MODULE_INFO_HOOK}','')-->
	</div>
<!--/&POST_AREA-->

<!--&POSTFORM-->
		<!--&IF($IS_THREAD,' <h2 class="theading">Posting mode: Reply</h2>','')-->
		<form id="postform" name="postform" action="{$LIVE_INDEX_FILE}" method="POST" <!--&IF($MAX_FILE_SIZE,' enctype="multipart/form-data"','')-->>
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
					<!--&IF($IS_STAFF,'<tr>
						<td class="postblock"><label for="postFormAdmin">Magic</label></td>
						<td class="postformInputCell">
							<div class="postFormAdminContainer">
								<span class="postFormAdminCheckboxes"> 
									{$FORM_STAFF_CHECKBOXES} 
								</span>
							</div>
						</td>
					</tr>','')-->
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
<!--/&POSTFORM-->

<!--&MODULE_INFO_HOOK-->
	<div class="mod-extra-info">
		{$BLOTTER}
		<hr>
		<!--&IF($GLOBAL_MESSAGE,'<div id="globalmsg">{$GLOBAL_MESSAGE}</div><hr id="globalmsgSeparator">','')-->
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
		<div class="thread" id="t{$BOARD_UID}_{$THREAD_NO}">
			{$BOARD_THREAD_NAME}
			<div class="tnav">{$THREADNAV}</div>
			{$THREAD_OP}
			{$REPLIES}
		</div>
<!--/&THREAD-->

<!--&OP-->
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
					<!--&IF($POSTER_HASH,'<span class="idContainer">ID:{$POSTER_HASH}</span>','')-->
				</label>
				<span class="postnum"><!--&IF($QUOTEBTN,'<a href="{$BOARD_URL}{$LIVE_INDEX_FILE}?res={$RESTO}#p{$BOARD_UID}_{$NO}" class="no">No.</a>{$QUOTEBTN}','<a href="{$BOARD_URL}{$LIVE_INDEX_FILE}?res={$RESTO}#p{$BOARD_UID}_{$NO}">No.{$NO}</a>')--></span>
				<span class="postInfoExtra">{$POSTINFO_EXTRA}</span>
				<span class="replyButton">{$REPLYBTN}</span><span class="backlinks"></span>
			</div>
			<div class="comment">{$COM}</div>
			<!--&IF($CATEGORY,'<small class="category"><i>{$CATEGORY_TEXT}{$CATEGORY}</i></small>','')-->
			{$WARN_OLD}{$WARN_BEKILL}{$WARN_ENDREPLY}{$WARN_HIDEPOST}
		</div>
<!--/&OP-->

<!--&REPLY-->
			<div id="pc{$BOARD_UID}_{$NO}" class="reply-container">
				<div class="doubledash">
					&gt;&gt;
				</div>
				<div class="post reply" id="p{$BOARD_UID}_{$NO}">
					<div class="postinfo">
						<label>
							<!--&IF($POST_POSITION_ENABLED,'<span class="replyPosition">{$POST_POSITION}</span>','')--> <input type="checkbox" name="{$POST_UID}" value="delete"> <span class="title">{$SUB}</span>
							<span class="nameContainer">
								<!--{$NAME_TEXT}--><span class="name">{$NAME}</span>
							</span>
							<span class="time">{$NOW}</span>
							<!--&IF($POSTER_HASH,'<span class="idContainer">ID:{$POSTER_HASH}</span>','')-->
						</label>
						<span class="postnum"><!--&IF($QUOTEBTN,'<a href="{$BOARD_URL}{$LIVE_INDEX_FILE}?res={$RESTO}#p{$BOARD_UID}_{$NO}" class="no">No.</a>{$QUOTEBTN}','<a href="{$BOARD_URL}{$LIVE_INDEX_FILE}?res={$RESTO}#p{$BOARD_UID}_{$NO}">No.{$NO}</a>')--></span>
						<span class="postInfoExtra">{$POSTINFO_EXTRA}</span><span class="backlinks"></span>
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
	<!--&IF($IS_THREAD,'<div class="threadNavBar">[<a href="{$STATIC_INDEX_FILE}">Return</a>]</div>','')-->
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
