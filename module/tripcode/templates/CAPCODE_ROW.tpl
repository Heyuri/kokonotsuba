	<tr>
		<td><span class="postertrip">{$TRIP_KEY}{$TRIPCODE}</span></td>
		<td>{$CAPCODE_COLOR}</td>
		<td>{$CAPCODE_TEXT}</td>
		<td>{$PREVIEW}</td>
		<td><!--&IF($IS_ENABLED,'Enabled','Disabled')--></td>
		<td>
			[<a href="{$VIEW_ENTRY_URL}">View</a>]
			<form class="capcodeToggleForm" style="display:inline;" method="POST" action="{$MODULE_URL}">
				<input name="capcodeId" value="{$ID}" type="hidden">
				<button type="submit"
					name="action"
					value="toggleCapcode"
					class="adminFunctions adminToggleCapcodeFunction"
					title="Switch the capcode on or off without deleting it"><!--&IF($IS_ENABLED,'Disable','Enable')--></button>
			</form>
		</td>
	</tr>
