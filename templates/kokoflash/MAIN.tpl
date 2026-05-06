	{$FORMDAT}
	{$THREADFRONT}
	<form name="delform" id="delform" action="{$LIVE_INDEX_FILE}" method="post">
		{$DELFORM_CSRF}
		<div class="centerText">
			<table class="flashboardList" id="filelist">
				<thead>
					<tr>
						<th class="postblock">No.</th>					<th class="postblock">Tag</th>						<th class="postblock">Name</th>
						<th class="postblock">File</th>
						<th class="postblock"></th>
						<th class="postblock">Subject</th>
						<th class="postblock">Size</th>
						<th class="postblock">Date</th>
						<th class="postblock">Replies</th>
						<th class="postblock"></th>
					</tr>
				</thead>				
				<tbody>
					{$THREADS}
				</tbody>
			</table>
			<hr>
			{$THREADREAR}
		</div>
		<!--&DELFORM/-->
	</form>
	{$BOTTOM_PAGENAV}
	<div id="postarea2"></div>