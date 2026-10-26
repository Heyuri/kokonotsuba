					<tr>
						<td class="postblock"><label for="threadModNumber">{$POST_LABEL}</label></td>
						<td>
							<select id="threadModNumber" name="postNumber" class="threadModNumberField">
								<option value="">{$PICK_LABEL}</option>
								{$POST_OPTIONS}
							</select>
						</td>
					</tr>
					<tr class="threadModPreviewRow" hidden>
						<td class="postblock">{$PREVIEW_LABEL}</td>
						<td class="threadModPreview"></td>
					</tr>
					<tr>
						<td class="postblock">{$ACTION_LABEL}</td>
						<td>
							<button type="submit" disabled hidden></button>
							<button type="submit" name="action" value="delete">{$DELETE_BUTTON}</button>
							<button type="submit" name="action" value="undo">{$UNDO_BUTTON}</button>
							{$OWNER_BUTTONS}
							<div class="formItemDescription threadModCount">{$COUNT_TEXT}</div>
						</td>
					</tr>
