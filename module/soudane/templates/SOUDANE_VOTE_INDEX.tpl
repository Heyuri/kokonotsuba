	<h3>Votes for Post No.{$POST_NUMBER}</h3>
	<form action="{$MODULE_URL}" method="POST">
		<input type="hidden" name="action" value="delete">
		<input type="hidden" name="postUid" value="{$POST_UID}">
		{$CSRF_TOKEN}
		<!--&IF($ROWS,'
		<div class="tableViewportWrapper">
		<table class="postlists">
			<thead>
				<tr>
					<th>Delete</th>
					<th>IP Address</th>
					<th>Type</th>
					<th>Date</th>
				</tr>
			</thead>
			<tbody>
				<!--&FOREACH($ROWS,'SOUDANE_VOTE_ROW')-->
			</tbody>
		</table>
		</div>
		<div class="buttonSection">
			<input type="submit" value="Remove selected">
		</div>
		','No votes found for this post.')-->
	</form>