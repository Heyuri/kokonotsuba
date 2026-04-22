	[<a href="{$STATIC_INDEX_FILE}">Return</a>]

	<h2 class="theading2">Blotter</h2>

	<div class="tableViewportWrapper">
	<table class="postlists" id="blotterlist">
		<thead>
			<tr>
				<th>Date</th>
				<th>Entry</th>
			</tr>
		</thead>
		<tbody>
			<!--&FOREACH($ROWS,'BLOTTER_TABLE_ROW')-->
			<!--&IF($EMPTY,'<tr><td colspan="2">No entries</td></tr>','')-->
		</tbody>
	</table>
	</div>