	<div class="noteFormContainer hostNoteFormContainer">
		<h3>{$FORM_TITLE}</h3>
		<div class="noteForm">
			<form method="POST" action="{$MODULE_URL}">
				{$CSRF_TOKEN}
				<input name="action" value="editNote" type="hidden">
				<input name="noteId" value="<!--&IF($NOTE_ID,'{$NOTE_ID}','')-->" type="hidden">
				<input name="returnUrl" value="<!--&IF($RETURN_URL,'{$RETURN_URL}','')-->" type="hidden">
				<table>
					<tbody>
						<tr>
							<td class="postblock"><label for="noteText">{$FORM_NOTE}</label></td>
							<td>
								<div class="formItemDescription">{$HOST_NOTE_VISIBILITY_DESCRIPTION}</div>
								<textarea id="noteText" name="noteText" cols="50" rows="6"><!--&IF($NOTE_TEXT,'{$NOTE_TEXT}','')--></textarea>
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
