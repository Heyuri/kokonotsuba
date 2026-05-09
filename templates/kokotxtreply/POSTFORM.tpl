	<div id="postformBox">
		<!--&IF($MAX_FILE_SIZE,'<form id="postform" name="postform" action="{$LIVE_INDEX_FILE}" method="POST" enctype="multipart/form-data" {$ALWAYS_NOKO}>','<form id="postform" name="postform" action="{$LIVE_INDEX_FILE}" method="POST" {$ALWAYS_NOKO}>')-->
			<h2 id="newReplyTitle"><!--&IF($IS_THREAD,'New reply','New thread')--></h2>
			{$FORM_HIDDEN}
			<div id="postformTable">
				<div id="rowPostNameEmail" class="postformCombinedItems">
					<div class="postformItem"><label for="name">{$FORM_NAME_LABEL}:</label>{$FORM_NAME_FIELD}</div>
					<div class="postformItem"><label for="email">{$FORM_EMAIL_LABEL}:</label>{$FORM_EMAIL_FIELD}<!--&NOKO_SAGE_DUMP/--></div>
					<div class="postformItem">{$FORM_SUBMIT}</div>
				</div>
				<!--&IF($FORM_ATTECHMENT_FIELD,'<div class="postformItem"><label for="upfile">{$FORM_FILE_LABEL}:</label>{$FORM_ATTECHMENT_FIELD}','')-->
					<!--&IF($FORM_NOATTECHMENT_FIELD,'<span class="nowrap">[<label>{$FORM_NOATTECHMENT_FIELD}{$FORM_NOFILE_LABEL}</label>]</span>','')-->
					<!--&IF($FORM_CONTPOST_FIELD,'<span class="nowrap">[<label>{$FORM_CONTPOST_FIELD}{$FORM_CONTPOST_LABEL}</label>]</span>','')-->
					{$FORM_FILE_EXTRA_FIELD}
				<!--&IF($FORM_ATTECHMENT_FIELD,'</div>','')-->
				<!--&IF($FORM_CATEGORY_FIELD,'<div class="postformItem"><label for="category">{$FORM_CATEGORY_LABEL}:</label>{$FORM_CATEGORY_FIELD}<small>{$FORM_CATEGORY_NOTICE}</small></div>','')-->
				<div class="postformItem"><label for="com">{$FORM_COMMENT_LABEL}:</label>{$FORM_COMMENT_BLOCK_EXTRA}<div class="commentArea">{$FORM_COMMENT_FIELD}{$FORM_COMMENT_EXTRAS}</div></div>
				<div class="postformItem"><label for="pwd">{$FORM_PASSWORD_LABEL}:</label><input type="password" name="pwd" id="pwd" value="" class="inputtext" maxlength="{$INPUT_MAX}"><span id="delPasswordInfo">{$FORM_PASSWORD_NOTICE}</span></div>
				<div class="postformItem">{$FORM_EXTRA_COLUMN}</div>
			</div>
		</form>
	</div>