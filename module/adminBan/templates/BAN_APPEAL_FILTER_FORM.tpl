	<form class="banFilterForm" method="GET" action="{$MODULE_URL}">
		<input type="hidden" name="mode" value="module">
		<input type="hidden" name="load" value="adminBan">
		<input type="hidden" name="moduleMode" value="admin">
		<input type="hidden" name="pageName" value="appeals">

		<label class="banFilterField">{$LABEL_STATUS}
			<select name="status">{$STATUS_OPTIONS}</select>
		</label>

		<button type="submit">{$SUBMIT_TEXT}</button>
	</form>
