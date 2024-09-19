<!--&THEMENAME--> Kokonotsuba File Board <!--/&THEMENAME-->
<!--&THEMEVER--> <!--/&THEMEVER-->
<!--&THEMEAUTHOR--> Hachikuji <!--/&THEMEAUTHOR-->

<!--&HEADER-->
<!DOCTYPE html>
<html lang="en-US">

<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<meta http-equiv="cache-control" content="max-age=0" />
	<meta http-equiv="cache-control" content="no-cache" />
	<meta http-equiv="expires" content="0" />
	<meta http-equiv="expires" content="Tue, 01 Jan 1980 1:00:00 GMT" />
	<meta http-equiv="pragma" content="no-cache" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<meta name="Berry" content="no" />
	<title>{$PAGE_TITLE}</title>
	<meta name="robots" content="follow,archive" />
	<link class="linkstyle" rel="stylesheet" type="text/css" href="{$STATIC_URL}css/heyuriclassic.css" title="Heyuri Classic" />
	<link class="linkstyle" rel="stylesheet alternate" type="text/css" href="{$STATIC_URL}css/futaba.css" title="Futaba" />
	<link class="linkstyle" rel="stylesheet alternate" type="text/css" href="{$STATIC_URL}css/oldheyuri.css" title="Sakomoto" />
	<link class="linkstyle" rel="stylesheet alternate" type="text/css" href="{$STATIC_URL}css/burichan.css" title="Burichan" />
	<link class="linkstyle" rel="stylesheet alternate" type="text/css" href="{$STATIC_URL}css/base.css" title="Import Custom" />
	<link rel="shortcut icon" href="{$STATIC_URL}image/favicon.png" />
	<script type="text/javascript" src="{$STATIC_URL}js/koko.js"></script>
	<script type="text/javascript" src="{$STATIC_URL}js/style.js"></script>
	<script type="text/javascript" src="{$STATIC_URL}js/inline.js"></script>
	<script type="text/javascript" src="{$STATIC_URL}js/addemotes.js" defer></script>
	<script type="text/javascript" src="{$STATIC_URL}js/admin.js" defer></script>
	<script type="text/javascript" src="{$STATIC_URL}js/ruffle/ruffle.js"></script>
	<script type="text/javascript" src="{$STATIC_URL}js/flashembed.js"></script>
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
			<br />
			<noscript><img border="1" src="./Utilities/banners.php" /></noscript>
			<script src="{$STATIC_URL}js/banners.js"></script>
			<div id="bannerContainer"></div>
			<h1 class="mtitle">{$TITLE}</h1>
			{$TITLESUB}
			<hr size="1" />
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
									{$ADDINFO}
								</td>
							</tr>
						</tbody>
					</table>
					<hr size="1" />
				<!--&FILE('./static/html/blotter.inc.html')-->
			</center>
			<hr size="1" />
		</form>
	</div>
	<center>
		<!--&FILE('./globalmsg.txt')-->
	</center>
	<!--&IF($FORMBOTTOM,'{$FORMBOTTOM}','')-->
	<!--/&POSTFORM-->

	<!--&FOOTER-->
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
		[<a href="'.$_SERVER['HTTP_REFERER'].'" onclick="event.preventDefault();history.go(-1);">{$BACK_TEXT}</a>]
		<hr />
		</center>
		<!--/&ERROR-->
		<!--&THREAD-->
		<tr class="thread" id="t{$NO}">
				<td> <a href="{$SELF}?res={$RESTO}#p{$NO}" class="no">{$NO}</a> </td>
				<td class="name"> {$NAME} </td>
				<td class="filecol"> [<a href="{$FILE_LINK}" download="{$FILE_NAME}">{$FILE_NAME}</a>]</td>
				<td> [<a id="flashboardEmbedText" onclick="openFlashEmbedWindow('{$FILE_LINK}', '{$ESCAPED_FILE_NAME}', {$FILE_WIDTH}, {$FILE_HEIGHT})">Embed</a>] </td>
				<td class="title"> {$SUB}</td>
				<td><div>{$FILE_SIZE}</div></td>
				<td class="time"> {$NOW} </td>
				<td>{$REPLYNUM}</td>
				<td> {$REPLYBTN} </td>
			</tr>
			<!--/&THREAD-->

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

		<!--/&THREADSEPARATE-->

		<!--&DELFORM-->
		<div align="right">
			<table id="userdelete" align="right" cellpadding="0">
				<tbody>
					<tr>
						<td align="right">
							{$DEL_HEAD_TEXT}[<label>{$DEL_IMG_ONLY_FIELD}{$DEL_IMG_ONLY_TEXT}</label>]<br />
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
		<center>
			<table class="flashboardList" id="filelist">
				<tbody>
					<thead>
						<td class="postblock">No.</td>
						<td class="postblock">Name</td>
						<td class="postblock">File</td>
						<td class="postblock"></td>
						<td class="postblock">Subject</td>
						<td class="postblock">Size</td>
						<td class="postblock">Date</td>
						<td class="postblock">Replies</td>
						<td class="postblock"></td>
					</thead>				
					{$THREADS}
					{$THREADREAR}
				</tbody>
			</table>
			</center>
			<!--&DELFORM/-->
		</form>
		<div id="postarea2"></div>
		{$PAGENAV}
		<br clear="ALL" />
		<!--/&MAIN-->
