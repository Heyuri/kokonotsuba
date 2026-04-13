	<hr class="threadSeparator">
	<div class="deletedPostContainer">
		<div class="deletedPostEntry">
			<form method="POST" action="<!--&IF($IS_VIEW,'{$VIEW_URL}','{$URL}')-->">
				<input type="hidden" name="deletedPostId" value="{$ID}">
				<table class="deletedPostTable">
					<tbody>
						<tr>
							<td class="postblock">Board</td>
							<td> {$BOARD_TITLE} ({$BOARD_UID})</td>
						</tr>
						<tr>
							<td class="postblock">Deleted by</td>
							<td><!--&IF($DELETED_BY,'{$DELETED_BY}','<i>User</i>')--></td>
						</tr>
						<tr>
							<td class="postblock">Deleted at</td>
							<td>{$DELETED_AT}</td>
						</tr>
						<!--&IF($IS_OPEN,'','
							<!--&DELETED_POST_RESTORE_INFO/-->
						')-->
						<tr>
							<td class="postblock"></td>
							<td>
								<!--&DELETE_POST_BUTTONS/-->
							</td>
						</tr>
					</tbody>
				</table>
			</form>
			<div class="deletedPostHtmlContainer alignLeft">
				{$POST_HTML}
			</div>
		</div>
	</div>