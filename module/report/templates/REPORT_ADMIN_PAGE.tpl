	<h2 class="theading2">{$PAGE_TITLE}</h2>
	<div class="reportToolbar">[<a href="{$REPORTED_POSTS_URL}">{$REPORTED_POSTS_TEXT}</a>]</div>
	{$FILTER_HTML}
	<h3 class="reportSectionHeading">{$HEADING_REPORTS}</h3>
	<form class="reportQueueForm" method="POST" action="{$MODULE_URL}">
		{$CSRF_TOKEN}
		<!--&IF($HAS_REPORTS,'
		<div class="tableViewportWrapper">
			<table class="postlists reportTable" id="reportAdminTable">
				<thead>
					<tr>
						<th class="colSelect"></th>
						<th class="colId">{$TH_ID}</th>
						<th class="colPreview">{$TH_PREVIEW}</th>
						<th class="colPost">{$TH_POST}</th>
						<th class="colBoard">{$TH_BOARD}</th>
						<th class="colReason">{$TH_REASON}</th>
						<th class="colIp">{$TH_IP}</th>
						<th class="colDate">{$TH_DATE}</th>
						<th class="colStatus">{$TH_STATUS}</th>
						<th class="colActionedBy">{$TH_ACTIONED_BY}</th>
						<th class="colActions">{$TH_ACTIONS}</th>
					</tr>
				</thead>
				<tbody>
					<!--&FOREACH($REPORTS,'REPORT_ADMIN_ROW')-->
				</tbody>
			</table>
		</div>
		{$DECISION_FORM}
		','<p class="reportEmpty">{$NO_REPORTS_TEXT}</p>')-->
	</form>
