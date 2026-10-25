	<li class="sideNavRow" data-key="{$KEY}">
		<span class="sideNavGrip js-only" title="Drag to move">&#9776;</span>
		<input type="text" class="inputtext sideNavRowLabel" name="link[{$KEY}][label]" value="{$LABEL}" placeholder="Label" maxlength="255" required>
		<input type="text" class="inputtext sideNavRowUrl" name="link[{$KEY}][url]" value="{$URL}" placeholder="URL" maxlength="2048" required>
		<select class="sideNavRowGroup" name="link[{$KEY}][group]" title="Group">{$GROUP_OPTIONS}</select>
		<button type="submit" class="sideNavMoveUp" name="action" value="link_up:{$KEY}" title="Move up" formnovalidate>&uarr;</button>
		<button type="submit" class="sideNavMoveDown" name="action" value="link_down:{$KEY}" title="Move down" formnovalidate>&darr;</button>
		<button type="submit" class="sideNavRemove" name="action" value="delete_link:{$KEY}" title="Delete link" formnovalidate>x</button>
	</li>
