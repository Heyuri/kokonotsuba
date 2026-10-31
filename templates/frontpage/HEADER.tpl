<!--&HEADER_IMG_BASE/-->
	{$HEAD_META}
	<script src="{$STATIC_URL}js/koko.js?v=25"></script>
	<script src="{$STATIC_URL}js/style.js?v=4"></script>
	<script src="{$STATIC_URL}js/css-vars-ponyfill.js" defer></script>
	<!--&IF($MODULE_HEADER_HTML,'{$MODULE_HEADER_HTML}','')-->
</head><body id="img" class="frontpage">
	{$EARLY_JS_SETTINGS}
	<div id="top"></div>
	<!--&TOPLINKS/-->
	<div class="logo">
		{$LOGO}
		<!--&IF($SHOW_TITLE,'<h1 class="fronttitle">{$TITLE}</h1>','')-->
		{$TITLESUB}
		{$POST_STATS}
		<hr class="hrThin">
	</div>
	{$FRONT_INFO}
