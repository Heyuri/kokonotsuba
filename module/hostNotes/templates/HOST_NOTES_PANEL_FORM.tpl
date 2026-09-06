				<div class="hostNotesPanelAdd">
					<input name="action" value="addNote" type="hidden">
					<input name="{$TARGET_NAME}" value="{$TARGET_VALUE}" type="hidden">
					<input name="returnUrl" value="{$RETURN_URL}" type="hidden">
					<table>
						<tbody>
							<tr>
								<td class="postblock"><label for="{$NOTE_FIELD_ID}">{$FORM_NOTE}</label></td>
								<td>
									<div class="formItemDescription">{$HOST_NOTE_VISIBILITY_DESCRIPTION}</div>
									<textarea id="{$NOTE_FIELD_ID}" name="note" cols="80" rows="3"></textarea>
								</td>
							</tr>
						</tbody>
					</table>
					<div class="buttonSection">
						<input type="submit" value="{$ADD_LABEL}">
					</div>
				</div>
