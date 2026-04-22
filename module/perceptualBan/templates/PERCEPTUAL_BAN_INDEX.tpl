	<h3>{$PERCEPTUAL_BAN_INDEX_TITLE}</h3>
	<!--&PERCEPTUAL_BAN_ADD_FORM/-->
	<hr>
	<form action="{$MODULE_URL}" method="POST">
		<input type="hidden" name="action" value="delete">
		<!--&IF($ROWS,'
		<div class="tableViewportWrapper">
		<table class="postlists">
			<thead>
				<tr>
					<th>{$PERCEPTUAL_BAN_DELETE_LABEL}</th>
					<th>{$PERCEPTUAL_BAN_HASH_LABEL}</th>
					<th>{$PERCEPTUAL_BAN_ADDED_BY_LABEL}</th>
					<th>{$PERCEPTUAL_BAN_DATE_LABEL}</th>
				</tr>
			</thead>
			<tbody>
				<!--&FOREACH($ROWS,'PERCEPTUAL_BAN_ROW')-->
			</tbody>
		</table>
		</div>
		<div class="buttonSection">
			<input type="submit" value="{$FORM_SUBMIT_BTN}">
		</div>
		','{$PERCEPTUAL_BAN_NO_ENTRIES}')-->
	</form>
