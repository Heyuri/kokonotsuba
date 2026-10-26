		<form method="POST" action="{$MODULE_URL}" class="threadModForm">
			{$CSRF_TOKEN}
			<input type="hidden" name="threadUid" value="{$THREAD_UID}">
			<table class="formtable threadModTable">
				<tbody>
					{$FORM_ROWS}
				</tbody>
			</table>
			{$ROSTER_SECTION}
		</form>
