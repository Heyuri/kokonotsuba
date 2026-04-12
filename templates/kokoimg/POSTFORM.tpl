		<!--&IF($IS_THREAD,' <h2 class="theading">Posting mode: Reply</h2>','')-->
		<form id="postform" name="postform" action="{$LIVE_INDEX_FILE}" method="POST" <!--&IF($MAX_FILE_SIZE,' enctype="multipart/form-data"','')--> {$ALWAYS_NOKO}>
			{$FORM_HIDDEN}
			<table id="postformTable">
				<tbody>
					<tr>
						<td class="postblock"><label for="name">Name</label></td>
						<td class="postformInputCell">{$FORM_NAME_FIELD}</td>
					</tr>
					<tr>
						<td class="postblock"><label for="email">Email</label></td>
						<td class="postformInputCell">{$FORM_EMAIL_FIELD}<!--&NOKO_SAGE_DUMP/--></td>
					</tr>
					<tr>
						<td class="postblock">
							<label for="sub">Subject</label></td>
						<td class="postformInputCell">{$FORM_TOPIC_FIELD}{$FORM_SUBMIT}</td>
					</tr>
					<tr>
						<td class="postblock">
							<label for="com">Comment</label>{$FORM_COMMENT_BLOCK_EXTRA}</td>
						<td class="postformInputCell">{$FORM_COMMENT_FIELD}{$FORM_COMMENT_EXTRAS}</td>
					</tr>
					<!--&IF($FORM_ATTECHMENT_FIELD,'<tr>
						<td class="postblock"><label for="upfile">File</label></td>
						<td class="postformInputCell">{$FORM_ATTECHMENT_FIELD}
							<div id="postformFileOptionsContainer">','')-->
								<!--&IF($FORM_CONTPOST_FIELD,'<div id="continuousContainer"><label id="continuousLabel">{$FORM_CONTPOST_FIELD}Continuous</label></div>','')-->
								<!--&IF($FORM_ATTECHMENT_FIELD,'
							{$FORM_FILE_EXTRA_FIELD}
							</div>
						</td>
					</tr>','')-->
					<!--&IF($FORM_CATEGORY_FIELD,'<tr>
						<td class="postblock"><label for="category">Category</label></td>
						<td class="postformInputCell">{$FORM_CATEGORY_FIELD}<small></small></td>
					</tr>','')-->
					<tr>
						<td class="postblock"><label for="pwd">Password</label></td>
						<td class="postformInputCell"><input type="password" name="pwd" id="pwd" value="" class="inputtext" maxlength="{$INPUT_MAX}"><span id="delPasswordInfo">(for deletion)</span>{$FORM_EXTRA_COLUMN}</td>
					</tr>
					<!--&IF($IS_STAFF,'<tr>
						<td class="postblock"><label for="postFormAdmin">Magic</label></td>
						<td class="postformInputCell">
							<div class="postFormAdminContainer">
								<span class="postFormAdminCheckboxes"> 
									{$FORM_STAFF_CHECKBOXES} 
								</span>
							</div>
						</td>
					</tr>','')-->
					<tr>
						<td id="rules" colspan="2">
							<ul class="rules">
								{$FORM_NOTICE}
								<!--&IF($FORM_NOTICE_STORAGE_LIMIT,'{$FORM_NOTICE_STORAGE_LIMIT}','')-->
								{$MODULE_POST_MENU_LIST_ITEM}
							</ul>
							<hr>
                            <div id="formfuncs" class="rules"><a class="postformOption" href="javascript:kkjs.form_switch();">Switch form position</a> | <a class="postformOption" href="{$STATIC_URL}html/bbcode.html" target="_blank">BBCode reference</a>{$FORM_FUNCS_EXTRA}</div>
							{$HOOKPOSTINFO}
						</td>
					</tr>
				</tbody>
			</table>
			<hr>
		</form>