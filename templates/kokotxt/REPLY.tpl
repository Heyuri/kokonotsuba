	<div id="pc{$BOARD_UID}_{$NO}" class="reply-container">
		<!--&IF($IS_PREVIEW,'<table class="thread"><tbody><tr><td>','')-->
		<div class="post reply<!--&IF($MODULE_POST_CSS_CLASSES,'{$MODULE_POST_CSS_CLASSES}','')-->" id="p{$BOARD_UID}_{$NO}" {$DATA_ATTRIBUTES}><span class="title"><a href="{$POST_URL}#p{$BOARD_UID}_{$NO}">{$SUB}</a></span>
			<div class="del">[<label>Del:<input type="checkbox" name="{$POST_UID}" class="deletionCheckbox" value="delete"></label>]</div>
			<div class="postinfo">
			<!--&IF($POST_POSITION_ENABLED,'
				<span class="replyPosition">{$POST_POSITION}</span>','')-->
				<span class="postnum">{$QUOTEBTN}</span>
				<span class="nameContainer">{$NAME_TEXT}<span class="name">{$NAME}</span></span> 
				<span class="time">{$NOW}</span> 
				<!--&IF($POSTER_HASH,'<span class="idContainer">ID:{$POSTER_HASH}</span>','')-->
				<!--&IF($TAG,'<span class="tag" title="{$TAG_TITLE}">[{$TAG}]</span>','')-->
				<span class="postInfoExtra">{$POSTINFO_EXTRA}</span>
				<div class="postMenuContainer"><!--&IF($POST_MENU,'{$POST_MENU}','')--></div>
			</div>
			<div class="imageSourceContainer<!--&IF($MODULE_ATTACHMENT_CSS_CLASSES,'{$MODULE_ATTACHMENT_CSS_CLASSES}','')-->">
				<!--&IF($POST_ATTACHMENTS,'{$POST_ATTACHMENTS}','')-->
			</div>
			<div class="comment">{$COM}</div>
			<div class="belowComment comment">{$BELOW_COMMENT}</div>
			<!--&IF($CATEGORY,'<small class="category"><i>{$CATEGORY_TEXT}{$CATEGORY}</i></small>','')-->
			<div class="warningsSection">{$WARN_BEKILL}</div>
		</div>
	</div>