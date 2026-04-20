<div class="banSectionContainer centerItem">
	<form method="POST" action="{$MODULE_URL}">
		{$CSRF_TOKEN}
		<h3>{$TITLE}</h3>

		<input type="hidden" name="adminban-action" value="delete-ban">

		<div id="banTableContainer" class="tableViewportWrapper">
			<table class="postlists banTable" id="{$TABLE_ID}">
				<thead>
					<tr>
						<th>Remove</th>
						<th>IP address</th>
						<th>Start time</th>
						<th>Expiration time</th>
						<th>Reason</th>
					</tr>
				</thead>
				<tbody>
					<!--&FOREACH($ROWS,'ADMIN_BAN_ROW')-->
				</tbody>
			</table>
		</div>

		<div class="buttonSection">
			<button type="submit" id="revokeButton">Remove selected</button>
		</div>
	</form>

	{$PAGER}
</div>