	<div class="pmInboxContainer">
		<h3>{$INBOX_TITLE}</h3>
		<span class="loggedInAs">{$LOGGED_IN_AS}</span>
		<form action="{$MODULE_PAGE_URL}" method="POST" class="logoutForm">
			<input name="action" value="tripLogout" type="hidden">
			<input type="submit" value="{$LOGOUT_LABEL}" class="logoutBtn">
		</form>
		<hr>
		<details class="pmSendForm detailsbox">
			<summary class="pmSendFormToggle">{$SEND_LABEL}</summary>
			<!--&PM_COMPOSE_FORM/-->
		</details>
		<div class="pmTableContainer tableViewportWrapper">
			<!--&IF($HAS_MESSAGES,'<table class="postlists">
				<thead>
					<tr class="pmTableHeader">
						<th></th>
						<th>{$PM_TABLE_FROM}</th>
						<th>{$PM_TABLE_SUBJECT}</th>
						<th>{$PM_TABLE_PREVIEW}</th>
						<th>{$PM_TABLE_DATE}</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<!--&FOREACH($MESSAGES,'PM_MESSAGE_ROW')-->
				</tbody>
			</table>','<p class="noMessages">{$NO_MESSAGES_TEXT}</p>')-->
		</div>
	</div>