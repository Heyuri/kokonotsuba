	<div class="pmInboxContainer">
		<h3>{$INBOX_TITLE}</h3>
		<span class="loggedInAs">{$LOGGED_IN_AS}</span>
		<form action="{$MODULE_PAGE_URL}" method="POST" class="logoutForm">
			<input name="action" value="tripLogout" type="hidden">
			<input type="submit" value="{$LOGOUT_LABEL}" class="logoutBtn">
		</form>
		<hr>
		<!--&PM_COMPOSE_FORM/-->
		<form action="{$MODULE_PAGE_URL}" method="POST" class="pmDeleteForm">
			<input name="action" value="deletePm" type="hidden">
			<div class="pmTableContainer tableViewportWrapper">
				<!--&IF($HAS_MESSAGES,'<table class="postlists">
					<thead>
						<tr class="pmTableHeader">
							<th></th>
							<th>{$PM_TABLE_SELECT}</th>
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
			<!--&IF($HAS_MESSAGES,'<div class="buttonSection"><button type="submit" class="pmDeleteBtn">{$PM_DELETE_BTN}</button></div>','')-->
		</form>
	</div>
