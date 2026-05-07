			<div id="pc{$BOARD_UID}_{$NO}" class="reply-container">
				<div class="doubledash">
					&gt;&gt;
				</div>
				<div class="post reply <!--&IF($MODULE_POST_CSS_CLASSES,'{$MODULE_POST_CSS_CLASSES}','')-->" id="p{$BOARD_UID}_{$NO}" {$DATA_ATTRIBUTES}>
					<div class="postinfo">
						<label>
							<!--&IF($POST_POSITION_ENABLED,'<span class="replyPosition">{$POST_POSITION}</span>','')-->
							<input type="checkbox" name="{$POST_UID}" class="deletionCheckbox" value="delete">
							<span class="title">{$SUB}</span><!--&IF($TAG,'<span class="tag" title="{$TAG_TITLE}">[{$TAG}]</span>','')-->
							<span class="nameContainer">
								<!--{$NAME_TEXT}--><span class="name">{$NAME}</span>
							</span>
							<span class="time">{$NOW}</span>
							<!--&IF($POSTER_HASH,'<span class="idContainer" title="{$POSTER_HASH_COUNT}">ID:{$POSTER_HASH}</span>','')-->
						</label>
						<span class="postnum"><!--&IF($QUOTEBTN,'<a href="{$POST_URL}" class="no">No.</a>{$QUOTEBTN}','<a href="{$POST_URL}">No.{$NO}</a>')--></span>
						<span class="postInfoExtra">{$POSTINFO_EXTRA}</span><span class="backlinks"></span>
						<div class="postMenuContainer"><!--&IF($POST_MENU,'{$POST_MENU}','')--></div>
					</div>
					<div class="imageSourceContainer<!--&IF($MODULE_ATTACHMENT_CSS_CLASSES,'{$MODULE_ATTACHMENT_CSS_CLASSES}','')-->">
						<!--&IF($POST_ATTACHMENTS,'{$POST_ATTACHMENTS}','')-->
					</div>
					<div class="comment">{$COM}</div>
					<div class="belowComment comment">{$BELOW_COMMENT}</div>
					<!--&IF($CATEGORY,'<small class="category"><i>{$CATEGORY_TEXT}{$CATEGORY}</i></small>','')-->
					{$WARN_BEKILL}
				</div>
			</div>