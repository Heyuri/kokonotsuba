	<div class="post op" id="p{$BOARD_UID}_{$NO}" {$DATA_ATTRIBUTES}>
		<h1 class="title"><a href="{$LIVE_INDEX_FILE}?res={$RESTO}"><!--&IF($SUB,'{$SUB}','No subject')--></a></h1><!--&IF($TAG,'<span class="tag" title="{$TAG_TITLE}">[{$TAG}]</span>','')-->
		<div class="del">[<label>Del:<input type="checkbox" name="{$POST_UID}" class="deletionCheckbox" value="delete"></label>]</div>
		<div class="postinfo">
			<span class="postnum">{$QUOTEBTN}</span>
			<span class="nameContainer">{$NAME_TEXT}<span class="name">{$NAME}</span></span> 
			<span class="time">{$NOW}</span> 
			<!--&IF($POSTER_HASH,'<span class="idContainer">ID:{$POSTER_HASH}</span>','')--> 
			<span class="postInfoExtra">{$POSTINFO_EXTRA}</span>
			<div class="postMenuContainer"><!--&IF($POST_MENU,'{$POST_MENU}','')--></div>
		</div>
		<div class="imageSourceContainer<!--&IF($MODULE_ATTACHMENT_CSS_CLASSES,'{$MODULE_ATTACHMENT_CSS_CLASSES}','')-->">
			<!--&IF($POST_ATTACHMENTS,'{$POST_ATTACHMENTS}','')-->
		</div>
		<div class="comment">{$COM}</div>
		<div class="belowComment comment">{$BELOW_COMMENT}</div>
		<!--&IF($CATEGORY,'<small class="category"><i>{$CATEGORY_TEXT}{$CATEGORY}</i></small>','')-->
		{$WARN_OLD}{$WARN_BEKILL}{$WARN_ENDREPLY}{$WARN_HIDEPOST}
	</div>