		<div id="postformBox" class="innerbox">
			<!--&IF($MAX_FILE_SIZE,'<form id="postform" name="postform" action="{$LIVE_INDEX_FILE}" method="POST" enctype="multipart/form-data" {$ALWAYS_NOKO}>','<form id="postform" name="postform" action="{$LIVE_INDEX_FILE}" method="POST" {$ALWAYS_NOKO}>')-->
				<h2 class="formTitle"><!--&IF($IS_THREAD,' New reply [<a href="{$STATIC_INDEX_FILE}">Return</a>]','New thread')--></h2>
				{$FORM_HIDDEN}
				<div id="postformTable">
					<div class="postformItem"><label for="sub">{$FORM_TOPIC_LABEL}:</label>{$FORM_TOPIC_FIELD}{$FORM_SUBMIT}</div>
					<div class="postformCombinedItems">
						<div class="postformItem"><label for="name">{$FORM_NAME_LABEL}:</label>{$FORM_NAME_FIELD}</div>
						<div class="postformItem"><label for="email">{$FORM_EMAIL_LABEL}:</label>{$FORM_EMAIL_FIELD}<!--&NOKO_SAGE_DUMP/--></div>
					</div>
					<!--&IF($FORM_ATTECHMENT_FIELD,'<div class="postformItem"><label for="upfile">{$FORM_FILE_LABEL}:</label>{$FORM_ATTECHMENT_FIELD}','')-->
					<!--&IF($FORM_NOATTECHMENT_FIELD,'<span class="nowrap">[<label>{$FORM_NOATTECHMENT_FIELD}{$FORM_NOFILE_LABEL}</label>]</span>','')-->
					<!--&IF($FORM_CONTPOST_FIELD,'<span class="nowrap">[<label>{$FORM_CONTPOST_FIELD}{$FORM_CONTPOST_LABEL}</label>]</span>','')-->
					{$FORM_FILE_EXTRA_FIELD}
					<!--&IF($FORM_ATTECHMENT_FIELD,'</div>','')-->
					<!--&IF($FORM_CATEGORY_FIELD,'<div class="postformItem"><label for="category">{$FORM_CATEGORY_LABEL}:</label>{$FORM_CATEGORY_FIELD}<small>{$FORM_CATEGORY_NOTICE}</small></div>','')-->
					<div class="postformItem"><label for="com">{$FORM_COMMENT_LABEL}:</label>{$FORM_COMMENT_BLOCK_EXTRA}
						<div class="commentArea">{$FORM_COMMENT_FIELD}{$FORM_COMMENT_EXTRAS}</div>
					</div>
					<div class="postformItem"><label for="pwd">{$FORM_PASSWORD_LABEL}:</label><input type="password" name="pwd" id="pwd" value="" class="inputtext" maxlength="{$INPUT_MAX}"><span id="delPasswordInfo">{$FORM_PASSWORD_NOTICE}</span></div>
					<div class="postformItem">{$FORM_EXTRA_COLUMN}</div>
					<div id="rules">
						<div id="formfuncs" class="rules"><a class="postformOption" href="javascript:kkjs.form_switch();">Switch form position</a> | <a class="postformOption" href="{$STATIC_URL}html/bbcode.html" target="_blank">BBCode reference</a>{$FORM_FUNCS_EXTRA}</div>
						<ul class="rules">
							{$FORM_NOTICE}
							<!--&IF($FORM_NOTICE_STORAGE_LIMIT,'{$FORM_NOTICE_STORAGE_LIMIT}','')-->
							{$MODULE_POST_MENU_LIST_ITEM}
						</ul>
						{$HOOKPOSTINFO}
					</div>
				</div>
			</form>
		</div>