	<div id="pc{$BOARD_UID}_{$NO}" class="op-container">
		<div class="post op<!--&IF($MODULE_POST_CSS_CLASSES,'{$MODULE_POST_CSS_CLASSES}','')-->" id="p{$BOARD_UID}_{$NO}" data-thread-uid="{$THREAD_UID}" {$DATA_ATTRIBUTES}>
			<!--&IF($BOARD_THREAD_NAME,'{$BOARD_THREAD_NAME}','')-->
			<div class="imageSourceContainer<!--&IF($MODULE_ATTACHMENT_CSS_CLASSES,'{$MODULE_ATTACHMENT_CSS_CLASSES}','')-->">
				<!--&IF($POST_ATTACHMENTS,'{$POST_ATTACHMENTS}','')-->
			</div>
			<div class="postinfo">
				<label>
					<input type="checkbox" name="{$POST_UID}" class="deletionCheckbox" value="delete">
					<span class="title">{$SUB}</span><!--&IF($TAG,'<span class="tag" title="{$TAG_TITLE}">[{$TAG}]</span>','')-->
					<span class="nameContainer">
						<!--{$NAME_TEXT}--><span class="name">{$NAME}</span>
					</span>
					<span class="time">{$NOW}</span>
					<!--&IF($POSTER_HASH,'<span class="idContainer" title="{$POSTER_HASH_COUNT}">ID:{$POSTER_HASH}</span>','')-->
				</label>
				<span class="postnum"><!--&IF($QUOTEBTN,'<a href="{$POST_URL}" class="no">No.</a>{$QUOTEBTN}','<a href="{$POST_URL}">No.{$NO}</a>')--></span>
				<span class="postInfoExtra">{$POSTINFO_EXTRA}</span>
				<div class="postMenuContainer"><!--&IF($POST_MENU,'{$POST_MENU}','')--></div>
				<span class="replyButton">{$REPLYBTN}</span> <span class="recentRepliesButton">{$RECENT_REPLIES}</span><span class="backlinks"></span>
			</div>
			<div class="comment">{$COM}</div>
			<div class="belowComment comment">{$BELOW_COMMENT}</div>
			<!--&IF($CATEGORY,'<small class="category"><i>{$CATEGORY_TEXT}{$CATEGORY}</i></small>','')-->
			{$WARN_OLD}{$WARN_BEKILL}{$WARN_ENDREPLY}{$WARN_HIDEPOST}
		</div>
	</div>