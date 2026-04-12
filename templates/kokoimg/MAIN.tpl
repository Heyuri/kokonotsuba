	<!--&IF($IS_THREAD,'<div class="threadNavBar">[<a href="{$STATIC_INDEX_FILE}">Return</a>] [<a href="#bottom">Bottom</a>]</div>','')-->
	{$FORMDAT}
	{$THREADFRONT}
	<!--&IF($TOP_PAGENAV,'{$TOP_PAGENAV}<hr class="threadSeparator topPagerSeparator">','')-->
	<form name="delform" id="delform" action="{$LIVE_INDEX_FILE}" method="post">
		{$DELFORM_CSRF}
		{$THREADS}
		{$THREADREAR}
		<!--&IF($IS_THREAD,'<div class="threadNavBar threadRear">[<a href="#top">Top</a>]</div><hr>','')-->
		<!--&DELFORM/-->
	</form>
	<!--&IF($BOTTOM_PAGENAV,'{$BOTTOM_PAGENAV}','')-->
	<div id="postarea2"></div>