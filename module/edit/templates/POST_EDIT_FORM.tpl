	<div class="editFormContainer">
		<h3>Edit note for post No.<span class="noteFormPostNumber" id="post_number">{$POST_NUMBER}</span></h3>
		<div class="editForm">
			<form method="POST" action="{$MODULE_URL}">
				{$CSRF_TOKEN}
				<input name="postUid" value="<!--&IF($POST_UID,'{$POST_UID}','')-->" type="hidden">
				<table>
					<tbody>
						<tr>
							<td class="postblock"><label for="editName">{$FORM_NAME}</label></td>
							<td>
								<input name="postUserName" id="editName" value="{$NAME}">
							</td>
						</tr>
						<tr>
							<td class="postblock"><label for="editEmail">{$FORM_EMAIL}</label></td>
							<td>
								<input name="postEmail" id="editEmail" value="{$EMAIL}">
							</td>
						</tr>
						<tr>
							<td class="postblock"><label for="editSubject">{$FORM_TOPIC}</label></td>
							<td>
								<input name="subject" id="editSubject" value="{$SUBJECT}">
							</td>
						</tr>
						<tr>
							<td class="postblock"><label for="editComment">{$FORM_COMMENT}</label></td>
							<td>
								<textarea name="comment" id="editComment" rows="4" cols="40">{$COMMENT}</textarea>
							</td>
						</tr>
					</tbody>
				</table>

				<div class="buttonSection">
					<input type="submit" value="Save">
				</div>
			</form>
		</div>
	</div>	