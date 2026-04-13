	<form id="import-board-form" action="{$LIVE_INDEX_FILE}?mode=handleBoardRequests" method="POST" enctype="multipart/form-data">
		<h3>Import a board</h3>
		<p>This imports the entirety of a vichan's boards and posts - please ensure there are no conflicting board URIs</p>
		<input type="hidden" name="import-board" value="1">
		<table id="import-board-table">
			<tbody>
				<tr>
					<td class="postblock"><label for="import-board-path">Absolute directory</label></td>
					<td>
						<input class="inputtext" id="import-board-path" name="import-board-path" required class="url-input" placeholder="/var/www/html/boards/" value="{$DEFAULT_PATH}">
					</td>
				</tr>
				<tr>
					<td class="postblock"><label for="import-dump-path">Dump path</label></td>
					<td>
						<input class="inputtext" id="import-dump-path" name="import-dump-path" required class="url-input" placeholder="/srv/kokonotsuba/bkp-kereste.sql">
					</td>
				</tr>
			</tbody>
		</table>

		<div class="buttonSection">
			<input id="import-board-submit" type="submit" value="Import">
		</div>
		
	</form>