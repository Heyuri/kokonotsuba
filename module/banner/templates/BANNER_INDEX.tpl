<h2 class="theading2">Banners</h2>

{$PRESET_NAV}

<!--&IF($STATUS_MESSAGE,'{$STATUS_MESSAGE}','')-->
<!--&IF($ALLOW_SUBMISSIONS,'<!--&BANNER_SUBMIT_FORM/-->','')-->

<h3>Approved banners ({$PRESET_LABEL})</h3>
<div class="tableViewportWrapper">
<table class="postlists" id="bannerlist">
	<thead>
		<tr>
			<th>Date submitted</th>
			<!--&IF($USES_LINK,'<th>Destination link</th>','')-->
			<th>Preview</th>
		</tr>
	</thead>
	<tbody>
		<!--&FOREACH($ROWS,'BANNER_INDEX_ROW')-->
		<!--&IF($EMPTY,'<tr><td colspan="3">No approved banners yet.</td></tr>','')-->
	</tbody>
</table>
</div>
