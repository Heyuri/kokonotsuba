<div class="moveThreadContainer">
	<form id="thread-move-form" method="POST" action="<!--&IF($FORM_ACTION,'{$FORM_ACTION}','')-->">
		{$CSRF_TOKEN}
		<h3 class="centerText">Move thread</h3>

		<input type="hidden" name="move-thread-uid" value="<!--&IF($THREAD_UID,'{$THREAD_UID}','')-->">
		<input type="hidden" name="move-thread-board-uid" value="<!--&IF($CURRENT_BOARD_UID,'{$CURRENT_BOARD_UID}','')-->">

		<table>
			<tbody>
				<tr>
					<td class="postblock"><label for="move-thread-num">Thread No.</label></td>
					<td><span id="move-thread-num">{$THREAD_NUMBER}</span></td>
				</tr>
				<tr>
					<td class="postblock"><label for="move-thread-board">Current board</label></td>
					<td><span id="move-thread-board">{$CURRENT_BOARD_NAME}</span></td>
				</tr>
				<tr id="boardrow">
					<td class="postblock"><label>Boards</label></td>
					<td>{$BOARD_RADIO_HTML}</td>
				</tr>
				<tr>
					<td class="postblock"><label>Options</label></td>
					<td>
						<label id="move-thread-leave-shadow-thread" title="Leave original thread up and lock it">
							<input type="checkbox" name="leave-shadow-thread" checked value="1">Leave shadow thread
						</label>		
					</td>
				</tr>
			</tbody>
		</table>

		<div class="buttonSection centerText">
			<button type="submit" name="move-thread-submit" value="move it!">Move thread</button>
		</div>
	</form>
</div>