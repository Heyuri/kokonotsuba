	<div class="sideNavBox" data-key="{$KEY}" data-kind="{$KIND}">
		<div class="sideNavBoxHead">
			<span class="sideNavGrip js-only" title="Drag to move">&#9776;</span>
			<input type="text" class="inputtext sideNavBoxTitle" name="group[{$KEY}][title]" value="{$TITLE}" placeholder="Group title" maxlength="255" required>
			{$KIND_LABEL}
			<button type="submit" class="sideNavMoveUp" name="action" value="group_up:{$KEY}" title="Move up" formnovalidate>&uarr;</button>
			<button type="submit" class="sideNavMoveDown" name="action" value="group_down:{$KEY}" title="Move down" formnovalidate>&darr;</button>
			<button type="submit" class="sideNavRemove" name="action" value="delete_group:{$KEY}" title="Delete group and its links" formnovalidate>x</button>
		</div>
		<ul class="sideNavBoxLinks">{$ROWS}</ul>
		{$ADD_ROW}
	</div>
