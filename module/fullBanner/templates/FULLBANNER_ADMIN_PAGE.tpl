<!--&FULLBANNER_SUBMIT_FORM/-->

<form id="fullbanneradminform" action="{$MODULE_PAGE_URL}" method="POST">
	<h3>All Banners</h3>
	<div class="tableViewportWrapper">
	<table class="postlists" id="fullbanneradminlist">
		<thead>
			<tr>
				<th>Select</th>
				<th>Date Submitted</th>
				<th>File Name</th>
				<th>Destination Link</th>
				<th>Approved</th>
				<th>Enabled</th>
				<th>Preview</th>
			</tr>
		</thead>
		<tbody>
			<!--&FOREACH($ROWS,'FULLBANNER_ADMIN_ROW')-->
			<!--&IF($EMPTY,'<tr><td colspan="7">No banners submitted yet.</td></tr>','')-->
		</tbody>
	</table>
	</div>
	<div class="buttonSection">
		<input type="submit" name="action_approve" value="Approve Selected">
		<input type="submit" name="action_enable" value="Enable Selected">
		<input type="submit" name="action_disable" value="Disable Selected">
		<input type="submit" name="action_delete" value="Delete Selected">
	</div>
</form>
