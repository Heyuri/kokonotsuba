[<a href="{$STATIC_INDEX_FILE}">Return</a>]
<h2 class="theading2">Full banners</h2>

<!--&FULLBANNER_SUBMIT_FORM/-->

<h3>Approved banners</h3>
<table class="postlists" id="fullbannerlist">
	<thead>
		<tr>
			<th>Date submitted</th>
			<th>Destination link</th>
			<th>Preview</th>
		</tr>
	</thead>
	<tbody>
		<!--&FOREACH($ROWS,'FULLBANNER_INDEX_ROW')-->
		<!--&IF($EMPTY,'<tr><td colspan="3">No approved banners yet.</td></tr>','')-->
	</tbody>
</table>
