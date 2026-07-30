	<form id="board-action-form" action="{$LIVE_INDEX_FILE}?mode=handleBoardRequests" method="POST">
		<h3>Edit board</h3>
	
		<input type="hidden" name="edit-board-uid" value="{$BOARD_UID}">
		<input type="hidden" name="edit-board-uid-for-redirect" value="{$BOARD_UID}">
		<input type="hidden" name="edit-board" value="{$BOARD_UID}">
		{$CSRF_INPUT}

		<table id="board-action-table">
			<tbody>
				<tr>
					<td class="postblock"><label for="edit-board-identifier">Identifier</label></td>
					<td> <input id="edit-board-identifier" name="edit-board-identifier" value="{$BOARD_IDENTIFIER}"></td>
				</tr>
				<tr>
					<td class="postblock"><label for="edit-board-title">Title</label></td>
					<td> <input required id="edit-board-title" name="edit-board-title" value="{$BOARD_TITLE}"></td>
				</tr>
				<tr>
					<td class="postblock"><label for="edit-board-sub-title">Subtitle</label></td>
					<td> <input id="edit-board-sub-title" name="edit-board-sub-title" value="{$BOARD_SUB_TITLE}"></td>
				</tr>
				<tr>
					<td class="postblock"><label for="edit-board-storage-dir">Board storage directory</label></td>
					<td> <input id="edit-board-storage-dir" name="edit-board-storage-dir" value="{$BOARD_STORAGE_DIR}" required> </td>
				</tr>
				<tr>
					<td class="postblock"><label for="edit-board-subdomain">Subdomain</label></td>
					<td>
						<input id="edit-board-subdomain" name="edit-board-subdomain" value="{$BOARD_SUBDOMAIN}" placeholder="cgi">
						<div class="formItemDescription">Serves this board from a subdomain of the website URL, e.g. 'cgi' turns "https://example.net/b/" into "https://cgi.example.net/b/". Leave empty to serve it from the website URL as-is. The subdomain must already point at this server, and the website URL must be absolute (a subdomain cannot be applied to "/").</div>
					</td>
				</tr>
				<tr>
					<td class="postblock"><label for="edit-board-listed">Listed</label></td>
					<td><input type="checkbox"  id="edit-board-listed" name="edit-board-listed" {$CHECKED}></td>
				</tr>
			</tbody>
		</table>

		<div class="buttonSection">
			<button type="submit" id="board-save-button" name="boardactionsubmit" value="save">Save changes</button>
			<button type="submit" id="edit-board-delete-button" name="board-action-submit" value="delete-board">Delete board</button>
		</div>
	</form>