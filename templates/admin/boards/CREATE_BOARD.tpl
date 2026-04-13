	<form action="{$LIVE_INDEX_FILE}?mode=handleBoardRequests" method="POST">
		<h3>Create a new board</h3>

		<input type="hidden" name="new-board" value="1">

		<table id="board-create-table">
			<tbody>
				<tr>
					<td class="postblock"><label for="new-board-title">Title</label></td>
					<td>
						<input required class="inputtext" id="new-board-title" name="new-board-title">
						<div class="formItemDescription">Title of the board.</div>
					</td>
				</tr>
				<tr>
					<td class="postblock"><label for="new-board-sub-title">Sub-title</label></td>
					<td>
						<input class="inputtext" id="new-board-sub-title" name="new-board-sub-title">
						<div class="formItemDescription">Smaller text beneath the board title on the page, typically providing a description of the board.</div>
					</td>
				</tr>
				<tr>
					<td class="postblock"><label for="new-board-identifier">Identifier</label></td>
					<td>
						<input class="inputtext" id="new-board-identifier" name="new-board-identifier" placeholder="b">
						<div class="formItemDescription">The string that represents the board in the URL and file storage. E.g. the 'b' in "/b/" or "boards.example.net/b/"</div>
					</td>
				</tr>
				<tr>
					<td class="postblock"><label for="new-board-path">Absolute directory</label></td>
					<td>
						<input class="inputtext" id="new-board-path" name="new-board-path" required class="url-input" placeholder="/var/www/html/boards/" value="{$DEFAULT_PATH}">
						<div class="formItemDescription">The directory where the board will be created at. Excluding its identifier. E.g. '/var/www/boards/' not '/var/www/boards/b/'</div>
					</td>
				</tr>
				<tr>
					<td class="postblock"><label for="new-board-listed">Listed</label></td> 
					<td><input type="checkbox" id="new-board-listed" name="new-board-listed" checked></td>
				</tr>
			</tbody>
		</table>

		<div class="buttonSection">
			<input id="board-form-submit" type="submit" value="Create board">
		</div>
	</form>

	<p>After creating a new board, be sure to configure it at its configuration file</p>