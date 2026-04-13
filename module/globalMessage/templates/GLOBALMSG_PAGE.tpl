	<form action="{$MODULE_URL}&action=setmessage" method="post">
		<h3>Edit global message</h3>

		<table id="postform_tbl">
			<tbody>
				<tr>
					<td class="postblock" style="min-width:9em"><label for="inputGlobalMessage">Global message<div>(raw HTML)</div></label></td>
					<td><textarea class="inputtext" id="inputGlobalMessage" name="content">{$CURRENT_GLOBAL_MESSAGE}</textarea></td>
				</tr>
			</tbody>
		</table>

		<div class="buttonSection">
			<input type="submit" name="submit" value="Submit">
		</div>
	</form>
		
	<h3>Current global message</h3>

	<hr>

	<div id="globalMessagePreviewCurrent">
		<div id="globalmsg">
			{$CURRENT_GLOBAL_MESSAGE}
		</div>
	</div>