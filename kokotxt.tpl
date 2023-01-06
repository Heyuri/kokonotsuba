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
    <link class="linkstyle" rel="stylesheet" type="text/css" href="https://k.kncdn.org/csstxt/pseud0ch2.css" title="Pseud0ch" />
	<link class="linkstyle" rel="stylesheet alternate" type="text/css" href="https://k.kncdn.org/csstxt/pseud0ch.css" title="Pseud0ch Mona" />
	<link class="linkstyle" rel="stylesheet alternate" type="text/css" href="https://k.kncdn.org/csstxt/pseud0ch3.css" title="Pseud0ch Times New Roman" />
	<link class="linkstyle" rel="stylesheet alternate" type="text/css" href="https://k.kncdn.org/csstxt/kareha.css" title="Kareha" />
	<link class="linkstyle" rel="stylesheet alternate" type="text/css" href="https://k.kncdn.org/csstxt/mobile.css?v2" title="Mobile" />
	<meta http-equiv="cache-control" content="no-cache" />
	<script type="text/javascript" src="https://k.kncdn.org/js/koko.js"></script>
	<script type="text/javascript" src="https://k.kncdn.org/js/qr.js"></script>
	<script type="text/javascript" src="https://k.kncdn.org/js/qu.js"></script>
	<script type="text/javascript" src="https://k.kncdn.org/js/qu2.js"></script>
	<script type="text/javascript" src="https://k.kncdn.org/js/style.js"></script>
	<script type="text/javascript" src="https://k.kncdn.org/js/img.js"></script>
	<script type="text/javascript" src="https://k.kncdn.org/js/catalog.js"></script>
	<script type="text/javascript" src="https://k.kncdn.org/js/insert.js"></script>
	<script src="https://kncdn.org/knirp.php?js"></script>
	<!--/&HEADER-->

	<!--&TOPLINKS-->
	<div class="boardlist">
		<span class="toplinks">{$TOP_LINKS}</span>
		<div class="adminbar" align="RIGHT">{$HOME} {$SEARCH} {$HOOKLINKS} {$ADMIN}</div>
	</div>
	<!--/&TOPLINKS-->

	<!--&BODYHEAD-->

<body>
	<a name="top"></a>
	<script id="wz_tooltip" type="text/javascript" src="{$STATIC_URL}javascript/wz_tooltip.js"></script>
	<!--&TOPLINKS/-->
	<table class="menu" align="CENTER" width="95%" border="1" cellspacing="7" cellpadding="3">
		<tbody>
			<tr class="t4vv">
				<td>
						<font size="+2">{$TITLE}</font><br />
						{$TITLESUB}
						<p>
						</p>
				</td>
			</tr>
		</tbody>
	</table>
	<br clear="ALL" />
	<!--/&BODYHEAD-->

	<!--&POSTFORM-->
	<div id="postarea">
		<form id="postform" name="postform" action="{$PHP_SELF}" method="POST" <!--&IF($MAX_FILE_SIZE,' enctype="multipart/form-data"','')-->>
				<table class="menu" align="CENTER" width="95%" border="1" cellspacing="7" cellpadding="3"><tbody>
					<tr><td>
						<nobr><font size="+1"><b><!--&IF($IS_THREAD,' New Reply <small>[<a href="{$PHP_SELF2}">Return</a>]</small>','New Thread')--></b></font>
			</nobr>
			{$FORM_HIDDEN}
			<a href="//www.kolyma.org" target="_blank"><img align="RIGHT" src="data:image/png;base64,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" border="1" /></a>
			<table>
				<tbody>
					<tr>
						<td valign="TOP"><label for="sub">Topic:</label></td>
						<td>{$FORM_TOPIC_FIELD}{$FORM_SUBMIT}</td>
					</tr>
					<tr>
						<td valign="TOP"><label for="name">Name:</label></td>
						<td>{$FORM_NAME_FIELD} <label>Email: {$FORM_EMAIL_FIELD}</label></td>
					</tr>
					<!--&IF($FORM_ATTECHMENT_FIELD,'<tr><td valign="TOP"><label><label for="upfile">File:</label></td><td>{$FORM_ATTECHMENT_FIELD}','')-->
					<!--&IF($FORM_NOATTECHMENT_FIELD,'<nobr>[<label>{$FORM_NOATTECHMENT_FIELD}No File</label>]</nobr>','')-->
					<!--&IF($FORM_CONTPOST_FIELD,'<nobr>[<label>{$FORM_CONTPOST_FIELD}Continuous</label>]</nobr>','')-->
					{$FORM_FILE_EXTRA_FIELD}
					<!--&IF($FORM_ATTECHMENT_FIELD,'</td></tr>','')-->
					<!--&IF($FORM_CATEGORY_FIELD,'<tr><td><label for="category">Category:</label></td><td>{$FORM_CATEGORY_FIELD}<small>(Use , to separate)</small></td></tr>','')-->
					<tr>
						<td><label for="pwd">Password:</td>
						<td>{$FORM_DELETE_PASSWORD_FIELD}<small>(for deletion, 8 chars max)</small></td>
					</tr>
					<tr>
						<td></td>
						<td>{$FORM_COMMENT_FIELD}</td>
						{$FORM_EXTRA_COLUMN}
					</tr>
					<tr>
						<td colspan="2" id="rules">
							<ul class="rules">
								{$FORM_NOTICE}
								<!--&IF($FORM_NOTICE_STORAGE_LIMIT,'{$FORM_NOTICE_STORAGE_LIMIT}','')-->
								{$HOOKPOSTINFO}
							</ul>
							{$ADDINFO}
							<input type="button" onClick="insertThisInThere('(;´Д`)','com')" value="(;´Д`)" />
							<input type="button" onClick="insertThisInThere('ヽ(´∇`)ノ','com')" value="ヽ(´∇`)ノ" />
							<input type="button" onClick="insertThisInThere('(´ー`)','com')" value="(´ー`)" />
							<input type="button" onClick="insertThisInThere('（ ’～’）','com')" value="（ ’～’）" />
							<input type="button" onClick="insertThisInThere('ヽ(`Д´)ノ','com')" value="ヽ(`Д´)ノ" />
							<input type="button" onClick="insertThisInThere('( ´ω`)','com')" value="( ´ω`)" />
							<input type="button" onClick="insertThisInThere('(・∀・)','com')" value="(・∀・)" /><br>
						</td>
					</tr>
				</tbody>
			</table>
			<!--&IF($FORMBOTTOM,'{$FORMBOTTOM}','')-->
			</td>
			</tr>
			</tbody>
			</table>
			<br clear="ALL" />
		</form>
	</div>
	<!--/&POSTFORM-->


	<!--&FOOTER-->
	<p align="CENTER" class="footer">
		{$FOOTER}
		{$FOOTTEXT}
	</p>
	<a name="bottom"></a>
</body>

</html>
<!--/&FOOTER-->

<!--&ERROR-->
<center>
	<h1 class="error">{$MESG}</h1>
	[<a href="{$SELF2}">{$RETURN_TEXT}</a>]
	[<a href="'.$_SERVER['HTTP_REFERER'].'" onclick="event.preventDefault();history.go(-1);">{$BACK_TEXT}</a>]
	<hr />
</center>
<!--/&ERROR-->

<!--&THREAD-->
<table class="thread" id="t{$NO}" align="CENTER" width="95%" border="1" cellspacing="7" cellpadding="3">
	<tbody>
			<tr class="t4vv">
			<td>
				<div class="tnav" align="RIGHT"><small>{$THREADNAV}</small></div>
				<div class="post op" id="p{$NO}">
					<font size="+2"><b class="title"><a href="{$PHP_SELF}?res={$RESTO}">
								<!--&IF($SUB,'{$SUB}','No Title')--></a></b></font>
					<div class="filesize">{$IMG_BAR}</div>
					<!--&IF($IMG_SRC,'{$IMG_SRC}<br clear="ALL" />','')-->
					<div class="del" align="RIGHT">[<label>Del:<input type="checkbox" name="{$NO}" value="delete" /></label>]</div>
					<dt class="postinfo"><span class="postnum">{$QUOTEBTN}</span> Name:<span class="name">{$NAME}</span> <span class="time">{$NOW}</span>{$POSTINFO_EXTRA}</dt>
					<dd class="body">{$COM}</dd>
					<!--&IF($CATEGORY,'<small class="category"><i>{$CATEGORY_TEXT}{$CATEGORY}</i></small>','')-->
					{$WARN_OLD}{$WARN_BEKILL}{$WARN_ENDREPLY}{$WARN_HIDEPOST}
				</div>
				<!--/&THREAD-->

				<!--&REPLY-->
				<!--&IF($IS_PREVIEW,'<table class="thread" align="CENTER" width="95%" border="1" cellspacing="7" cellpadding="3"><tbody><tr><td>','')-->
				<div class="post reply" id="p{$NO}">
					<div class="filesize">{$IMG_BAR}</div>
					<!--&IF($IMG_SRC,'{$IMG_SRC}<br clear="ALL" />','')-->
					<font size="+2"><b class="title"><a href="{$PHP_SELF}?res={$RESTO}#p{$NO}">{$SUB}</a></b></font>
					<div class="del" align="RIGHT">[<label>Del:<input type="checkbox" name="{$NO}" value="delete" /></label>]</div>
					<dt class="postinfo"><span class="postnum">{$QUOTEBTN}</span> Name:<span class="name">{$NAME}</span> <span class="time">{$NOW}</span>{$POSTINFO_EXTRA}</dt>
					<dd class="body">{$COM}</dd>
					<!--&IF($CATEGORY,'<small class="category"><i>{$CATEGORY_TEXT}{$CATEGORY}</i></small>','')-->
					{$WARN_BEKILL}
				</div>
				<!--/&REPLY-->

				<!--&SEARCHRESULT-->
				<table class="thread" align="CENTER" width="95%" border="1" cellspacing="7" cellpadding="3">
					<tbody>
			<tr class="t4vv">
							<td>
								<div class="post search">
									<font size="+2"><b class="title">{$SUB}</b></font>
									<dt class="postinfo">{$NO} Name:<span class="name">{$NAME}</span> <span class="time">{$NOW}</span></dt>
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
<br clear="all" />
<!--/&THREADSEPARATE-->

<!--&REALSEPARATE-->
<br clear="ALL" />
<!--/&REALSEPARATE-->

<!--&DELFORM-->
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
{$FORMDAT}
{$THREADFRONT}
<form name="delform" id="delform" action="{$SELF}" method="post">
	{$THREADS}
	{$THREADREAR}
	<!--&DELFORM/-->
</form>
<div id="postarea2"></div>
{$PAGENAV}
<br clear="ALL" />
<!--/&MAIN-->
