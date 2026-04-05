	<div class="noteFormContainer">
		<h3>Leave a note for post No.<span class="noteFormPostNumber" id="post_number">{$POST_NUMBER}</span></h3>
		<div class="noteForm">
			<form method="POST" action="{$MODULE_URL}">
				<input name="action" value="addNote" type="hidden">
				<input name="postUid" value="<!--&IF($POST_UID,'{$POST_UID}','')-->" type="hidden">
				<table>
					<tbody>
						<tr>
							<td class="postblock"><label for="note">Note</label></td>
							<td>
								<div class="formItemDescription">{$NOTE_VISIBILITY_DESCRIPTION}</div>
								<textarea id="note" name="note" cols="80" rows="6"></textarea>
							</td>
						</tr>
					</tbody>
				</table>

				<div class="buttonSection">
					<input type="submit" value="Save note">
				</div>
			</form>
	</div>