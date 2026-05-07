	<div id="pc{$BOARD_UID}_{$NO}" class="op-container">
		<h2 class="title"><a href="{$POST_URL}"><!--&IF($SUB,'{$SUB}','No subject')--></a></h2><!--&IF($TAG,'<span class="tag" title="{$TAG_TITLE}">[{$TAG}]</span>','')-->
		<div class="post op<!--&IF($MODULE_POST_CSS_CLASSES,'{$MODULE_POST_CSS_CLASSES}','')-->" id="p{$BOARD_UID}_{$NO}" data-thread-uid="{$THREAD_UID}" {$DATA_ATTRIBUTES}>
			<div class="del">[<label>Del:<input type="checkbox" name="{$POST_UID}" class="deletionCheckbox" value="delete"></label>]</div>
			<div class="postinfo"><span class="postnum">{$QUOTEBTN}</span>
				<span class="nameContainer">{$NAME_TEXT}<span class="name">{$NAME}</span></span>
				<span class="time">{$NOW}</span> <!--&IF($POSTER_HASH,'<span class="idContainer">ID:{$POSTER_HASH}</span>','')--> 
				<span class="postInfoExtra">{$POSTINFO_EXTRA}</span>
				<div class="postMenuContainer"><!--&IF($POST_MENU,'{$POST_MENU}','')--></div>
			</div>
			<div class="imageSourceContainer<!--&IF($MODULE_ATTACHMENT_CSS_CLASSES,'{$MODULE_ATTACHMENT_CSS_CLASSES}','')-->">
				<!--&IF($POST_ATTACHMENTS,'{$POST_ATTACHMENTS}','')-->
			</div>
			<div class="comment">{$COM}</div>
			<div class="belowComment comment">{$BELOW_COMMENT}</div>
			<!--&IF($CATEGORY,'<small class="category"><i>{$CATEGORY_TEXT}{$CATEGORY}</i></small>','')-->
			<div class="warningsSection">{$WARN_OLD}{$WARN_BEKILL}{$WARN_ENDREPLY}{$WARN_HIDEPOST}</div>
		</div>
	</div>