	<!--&IF($SUCCESS_MESSAGE,'<p class="rebuildSuccess">{$SUCCESS_MESSAGE}</p>','')-->

	<form action="{$MODULE_URL}" method="POST" class="formtable" id="rebuildForm">
		{$CSRF_TOKEN}
		<h3>Rebuild boards</h3>
		
		{$REBUILD_CHECK_LIST}

		<div class="buttonSection">
			<button name="formSubmit" value="save" id="rebuildSubmit">Submit</button>
		</div>
	</form>