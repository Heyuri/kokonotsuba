<h3 class="centerText">{$PAGE_TITLE}</h3>

<form method="POST" action="{$MODULE_URL}">
	<div id="pmAdminTableContainer">
		<table class="postlists" id="pmAdminTable">
			<thead>
				<tr>
					<th>{$TH_SELECT}</th>
					<th>{$TH_SENDER}</th>
					<th>{$TH_RECIPIENT}</th>
					<th>{$TH_SUBJECT}</th>
					<th>{$TH_BODY}</th>
					<th>{$TH_IP}</th>
					<th>{$TH_DATE}</th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<!--&IF($HAS_MESSAGES,'<!--&FOREACH($MESSAGES,'PM_ADMIN_ROW')-->','<tr><td colspan="8">{$NO_MESSAGES_TEXT}</td></tr>')-->
			</tbody>
		</table>
	</div>

	<div class="buttonSection">
		<button type="submit" name="action" value="delete">{$DELETE_BTN}</button>
		<button type="submit" name="action" value="ban">{$BAN_BTN}</button>
	</div>
</form>

{$PAGER_HTML}
