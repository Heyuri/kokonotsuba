		<form method="POST" action="{$MODULE_URL}" class="threadModForm threadModDeleteForm">
			{$CSRF_TOKEN}
			<input type="hidden" name="action" value="delete">
			<label>{$DELETE_LABEL} No.<input type="number" name="postNumber" min="1" class="inputtext threadModNumberField"></label>
			<input type="submit" value="{$DELETE_BUTTON}">
			<small class="threadModCount">{$COUNT_TEXT}</small>
		</form>
		<form method="POST" action="{$MODULE_URL}" class="threadModForm threadModUndoForm">
			{$CSRF_TOKEN}
			<input type="hidden" name="action" value="undo">
			<label>{$UNDO_LABEL} No.<input type="number" name="postNumber" min="1" class="inputtext threadModNumberField"></label>
			<input type="submit" value="{$UNDO_BUTTON}">
		</form>
