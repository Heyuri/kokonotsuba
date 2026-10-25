	<li class="sideNavRow sideNavAutoRow" data-key="{$KEY}" data-source="{$SOURCE}" data-default-label="{$DEFAULT_LABEL}" data-default-url="{$DEFAULT_URL}" data-link-title="{$LINK_TITLE}">
		<span class="sideNavGrip js-only" title="Drag to move">&#9776;</span>
		<input type="hidden" name="link[{$KEY}][source]" value="{$SOURCE}">
		<input type="hidden" class="sideNavRowGroup" name="link[{$KEY}][group]" value="{$GROUP}">
		<input type="text" class="inputtext sideNavRowLabel" name="link[{$KEY}][label]" value="{$LABEL}" placeholder="{$PLACEHOLDER}" title="Leave empty for the default" maxlength="255">
		<label class="sideNavRowHidden"><input type="checkbox" name="link[{$KEY}][hidden]" value="1"{$HIDDEN_CHECKED}> Hide</label>
		<button type="submit" class="sideNavMoveUp" name="action" value="link_up:{$KEY}" title="Move up" formnovalidate>&uarr;</button>
		<button type="submit" class="sideNavMoveDown" name="action" value="link_down:{$KEY}" title="Move down" formnovalidate>&darr;</button>
	</li>
