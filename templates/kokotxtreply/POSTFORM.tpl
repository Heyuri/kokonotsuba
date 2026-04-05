	<div id="postformBox">
		<!--&IF($MAX_FILE_SIZE,'<form id="postform" name="postform" action="{$LIVE_INDEX_FILE}" method="POST" enctype="multipart/form-data" {$ALWAYS_NOKO}>','<form id="postform" name="postform" action="{$LIVE_INDEX_FILE}" method="POST" {$ALWAYS_NOKO}>')-->
			<h2 id="newReplyTitle"><!--&IF($IS_THREAD,'New reply','New thread')--></h2>
			{$FORM_HIDDEN}
			<div id="postformTable">
				<div id="rowPostNameEmail" class="postformCombinedItems">
					<div class="postformItem"><label for="name">Name:</label>{$FORM_NAME_FIELD}</div>
					<div class="postformItem"><label for="email">Email:</label>{$FORM_EMAIL_FIELD}</div>
					<div class="postformItem">{$FORM_SUBMIT}</div>
				</div>
				<!--&IF($FORM_ATTECHMENT_FIELD,'<div class="postformItem"><label for="upfile">File:</label>{$FORM_ATTECHMENT_FIELD}','')-->
					<!--&IF($FORM_NOATTECHMENT_FIELD,'<span class="nowrap">[<label>{$FORM_NOATTECHMENT_FIELD}No File</label>]</span>','')-->
					<!--&IF($FORM_CONTPOST_FIELD,'<span class="nowrap">[<label>{$FORM_CONTPOST_FIELD}Continuous</label>]</span>','')-->
					{$FORM_FILE_EXTRA_FIELD}
				<!--&IF($FORM_ATTECHMENT_FIELD,'</div>','')-->
				<!--&IF($FORM_CATEGORY_FIELD,'<div class="postformItem"><label for="category">Category:</label>{$FORM_CATEGORY_FIELD}<small>(Use , to separate)</small></div>','')-->
				<div class="postformItem"><label for="com">Comment:</label><div class="commentArea">{$FORM_COMMENT_FIELD}</div></div>
				<div class="postformItem"><label for="pwd">Password:</label><input type="password" name="pwd" id="pwd" value="" class="inputtext" maxlength="{$INPUT_MAX}"><span id="delPasswordInfo">(for deletion)</span></div>
				<div class="postformItem">{$FORM_EXTRA_COLUMN}</div>
			</div>
		</form>
	</div>