					<tr>
						<td class="postblock"><label for="threadModNumber">{$POST_LABEL}</label></td>
						<td>No.<input type="number" id="threadModNumber" name="postNumber" min="1" class="inputtext threadModNumberField"></td>
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
