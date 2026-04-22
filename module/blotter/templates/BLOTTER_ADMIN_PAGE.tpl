	<form action="{$MODULE_URL}" method='post'>
		<h3>Add new blotter entry</h3>

		<table class="formtable">
			<tbody>
				<tr>
					<td class='postblock'><label for='new_blot_txt'>Blotter entry</label></td>
					<td><textarea id='new_blot_txt' class='inputtext' name='new_blot_txt' cols='30' rows='5' ></textarea></td>
				</tr>
			</tbody>
		</table>

		<div class="buttonSection">
			<input type='submit' name='submit' value='Submit'>
		</div>
	</form>

	<form id="blotterdeletionform" action="{$MODULE_URL}" method="POST">
		<h3>Blotter entries</h3>

		<div class="tableViewportWrapper">
		<table class="postlists" id="blotterlist">
			<thead>
				<tr>
					<th>Date</th>
					<th>Added by</th>
					<th>Entry</th>
					<th>Del</th>
				</tr>
			</thead>
			<tbody>
				<!--&FOREACH($ROWS,'BLOTTER_ADMIN_PAGE_TABLE_BLOCK')-->
			</tbody>
		</table>
		</div>

		<div class="buttonSection">
			<input value="Save edits" name="edit_submit" type="submit">
			<input value="Submit" name="delete_submit" type="submit">
		</div>
	</form>