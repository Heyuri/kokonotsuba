	<h3>{$FILE_BAN_INDEX_TITLE}</h3>
	<!--&FILE_BAN_ADD_FORM/-->
	<hr>
	<form action="{$MODULE_URL}" method="POST">
		<input type="hidden" name="action" value="delete">
		<!--&IF($ROWS,'
		<table class="postlists">
			<thead>
				<tr>
					<th>{$FILE_BAN_DELETE_LABEL}</th>
					<th>{$FILE_BAN_HASH_LABEL}</th>
					<th>{$FILE_BAN_ADDED_BY_LABEL}</th>
					<th>{$FILE_BAN_DATE_LABEL}</th>
				</tr>
			</thead>
			<tbody>
				<!--&FOREACH($ROWS,'FILE_BAN_ROW')-->
			</tbody>
		</table>
		<div class="buttonSection">
			<input type="submit" value="{$FORM_SUBMIT_BTN}">
		</div>
		','{$FILE_BAN_NO_ENTRIES}')-->
	</form>