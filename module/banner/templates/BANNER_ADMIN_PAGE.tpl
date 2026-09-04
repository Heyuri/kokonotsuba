{$PRESET_NAV}

<!--&IF($SHOW_UPLOAD,'<!--&BANNER_SUBMIT_FORM/-->','')-->

<form id="banneradminform" action="{$MODULE_PAGE_URL}" method="POST">
	<h3>All Banners</h3>
	<!--&IF($EMPTY,'','<!--&BANNER_ADMIN_BUTTONS/-->')-->
	<div class="tableViewportWrapper">
	<table class="postlists" id="banneradminlist">
		<thead>
			<tr>
				<th>Select</th>
				<th>Date Submitted</th>
				<th>Preset</th>
				<th>File Name</th>
				<th>Destination Link</th>
				<th>Approved</th>
				<th>Enabled</th>
				<th>Preview</th>
			</tr>
		</thead>
		<tbody>
			<!--&FOREACH($ROWS,'BANNER_ADMIN_ROW')-->
			<!--&IF($EMPTY,'<tr><td colspan="8">No banners submitted yet.</td></tr>','')-->
		</tbody>
	</table>
	</div>
	<!--&IF($EMPTY,'','<!--&BANNER_ADMIN_BUTTONS/-->')-->
</form>
