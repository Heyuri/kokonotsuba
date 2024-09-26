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
	<div class="boardlist">
		<span class="toplinks">{$TOP_LINKS}</span>
		<div class="adminbar" align="RIGHT">{$HOME} {$HOOKLINKS} {$ADMIN}</div>
	</div>
	<!--/&TOPLINKS-->

	<!--&BODYHEAD-->

<body>
	<a name="top"></a>
	<script id="wz_tooltip" type="text/javascript" src="{$STATIC_URL}js/wz_tooltip.js"></script>
	<!--&TOPLINKS/-->
	<table class="menu" align="CENTER" width="95%" border="1" cellspacing="7" cellpadding="3">
		<tbody>
			<tr class="t4vv">
				<td>
						<font size="+2">{$TITLE}</font><br>
						{$TITLESUB}
						<p>
						</p>
				</td>
			</tr>
		</tbody>
	</table>
	<br clear="ALL">
	<!--/&BODYHEAD-->

	<!--&POSTFORM-->
	<div id="postarea">
		<form id="postform" name="postform" action="{$PHP_SELF}" method="POST" <!--&IF($MAX_FILE_SIZE,' enctype="multipart/form-data"','')-->>
				<table class="menu" align="CENTER" width="95%" border="1" cellspacing="7" cellpadding="3"><tbody>
					<tr><td>
						<nobr><font size="+1"><b><!--&IF($IS_THREAD,' New Reply <small>[<a href="{$PHP_SELF2}">Return</a>]</small>','New Thread')--></b></font>
			</nobr>
			{$FORM_HIDDEN}
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
							<input type="button" onClick="insertThisInThere('(;´Д`)','com')" value="(;´Д`)">
							<input type="button" onClick="insertThisInThere('ヽ(´∇`)ノ','com')" value="ヽ(´∇`)ノ">
							<input type="button" onClick="insertThisInThere('(´ー`)','com')" value="(´ー`)">
							<input type="button" onClick="insertThisInThere('（ ’～’）','com')" value="（ ’～’）">
							<input type="button" onClick="insertThisInThere('ヽ(`Д´)ノ','com')" value="ヽ(`Д´)ノ">
							<input type="button" onClick="insertThisInThere('( ´ω`)','com')" value="( ´ω`)">
							<input type="button" onClick="insertThisInThere('(・∀・)','com')" value="(・∀・)"><br>
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
	<hr>
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
					<!--&IF($IMG_SRC,'{$IMG_SRC}<br clear="ALL">','')-->
					<div class="del" align="RIGHT">[<label>Del:<input type="checkbox" name="{$NO}" value="delete"></label>]</div>
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
					<!--&IF($IMG_SRC,'{$IMG_SRC}<br clear="ALL">','')-->
					<font size="+2"><b class="title"><a href="{$PHP_SELF}?res={$RESTO}#p{$NO}">{$SUB}</a></b></font>
					<div class="del" align="RIGHT">[<label>Del:<input type="checkbox" name="{$NO}" value="delete"></label>]</div>
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
<br clear="all">
<!--/&THREADSEPARATE-->

<!--&REALSEPARATE-->
<br clear="ALL">
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
<br clear="ALL">
<!--/&MAIN-->
