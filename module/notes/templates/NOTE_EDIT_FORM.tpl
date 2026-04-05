	<div class="noteFormContainer">
		<h3>Edit note for post No.<span class="noteFormPostNumber" id="post_number">{$POST_NUMBER}</span></h3>
		<div class="noteForm">
			<form method="POST" action="{$MODULE_URL}">
				<input name="action" value="editNote" type="hidden">
				<input name="noteId" value="<!--&IF($NOTE_ID,'{$NOTE_ID}','')-->" type="hidden">
				<input name="postUid" value="<!--&IF($POST_UID,'{$POST_UID}','')-->" type="hidden">
				<table>
					<tbody>
						<tr>
							<td class="postblock"><label for="noteText">Note</label></td>
							<td>
								<div class="formItemDescription">{$NOTE_VISIBILITY_DESCRIPTION}</div>
								<textarea id="noteText" name="noteText" cols="50" rows="6"><!--&IF($NOTE_TEXT,'{$NOTE_TEXT}','')--></textarea>
							</td>
						</tr>
					</tbody>
				</table>

				<div class="buttonSection">
					<input type="submit" value="Save note">
				</div>
			</form>
	</div>