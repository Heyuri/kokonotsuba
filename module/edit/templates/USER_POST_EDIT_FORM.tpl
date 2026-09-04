	<div class="editFormContainer userEditFormContainer">
		<h3>{$FORM_TITLE} No.<span class="noteFormPostNumber" id="post_number">{$POST_NUMBER}</span></h3>
		<div class="editForm">
			<form method="POST" action="{$MODULE_URL}" enctype="multipart/form-data">
				{$CSRF_TOKEN}
				<input name="postUid" value="<!--&IF($POST_UID,'{$POST_UID}','')-->" type="hidden">
				<table>
					<tbody>
						<tr>
							<td class="postblock"><label for="userEditName">{$FORM_NAME}</label></td>
							<td>
								<input name="postUserName" id="userEditName" value="{$NAME}">
							</td>
						</tr>
						<tr>
							<td class="postblock"><label for="userEditEmail">{$FORM_EMAIL}</label></td>
							<td>
								<input name="postEmail" id="userEditEmail" value="{$EMAIL}">
							</td>
						</tr>
						<tr>
							<td class="postblock"><label for="userEditSubject">{$FORM_TOPIC}</label></td>
							<td>
								<input name="subject" id="userEditSubject" value="{$SUBJECT}">
							</td>
						</tr>
						<tr>
							<td class="postblock"><label for="userEditComment">{$FORM_COMMENT}</label></td>
							<td>
								<textarea name="comment" id="userEditComment" rows="4" cols="40">{$COMMENT}</textarea>
							</td>
						</tr>
						<tr>
							<td class="postblock"><label for="userEditTag">{$FORM_TAG}</label></td>
							<td>
								<select name="tag" id="userEditTag">
									{$TAG_SELECT}
								</select>
							</td>
						</tr>
						<tr class="editAttachmentsRow" <!--&IF($SHOW_ATTACHMENTS,'','hidden')-->>
							<td class="postblock"><label>{$FORM_ATTACHMENTS}</label></td>
							<td>
								<div class="formItemDescription">{$ATTACHMENTS_DESCRIPTION}</div>
								<div class="editAttachmentList" data-empty-text="{$NO_ATTACHMENTS_TEXT}">{$ATTACHMENT_LIST}</div>
								<input type="file" name="upfile[]" class="editAttachmentUpload" multiple>
							</td>
						</tr>
						<tr>
							<td class="postblock"><label for="userEditPassword">{$FORM_PASSWORD}</label></td>
							<td>
								<div class="formItemDescription">{$PASSWORD_HINT}</div>
								<input type="password" name="pwd" id="userEditPassword" autocomplete="current-password">
							</td>
						</tr>
					</tbody>
				</table>

				<div class="buttonSection">
					<input type="submit" value="{$SUBMIT_TEXT}">
				</div>
			</form>
		</div>
	</div>
