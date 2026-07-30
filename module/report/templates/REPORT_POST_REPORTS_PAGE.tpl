	<h2 class="theading2">{$PAGE_TITLE}</h2>
	<div class="reportToolbar">[<a href="{$QUEUE_URL}">{$QUEUE_TEXT}</a>]</div>

	<h3 class="reportSectionHeading">{$HEADING_POST}</h3>
	<div class="reportPostPreview modPagePostContainer reportPostPreviewStandalone">{$POST_PREVIEW}</div>

	<h3 class="reportSectionHeading">{$HEADING_TOTALS}</h3>
	<div class="reportStats">{$STATS_TABLE}</div>

	<h3 class="reportSectionHeading">{$HEADING_REPORTS}</h3>
	<form class="reportQueueForm" method="POST" action="{$MODULE_URL}">
		{$CSRF_TOKEN}
		<input type="hidden" name="postUid" value="{$POST_UID}">
		<!--&IF($HAS_REPORTS,'
		<div class="tableViewportWrapper">
			<table class="postlists reportTable">
				<thead>
					<tr>
						<th class="colSelect"></th>
						<th class="colId">{$TH_ID}</th>
						<th class="colReason">{$TH_REASON}</th>
						<th class="colIp">{$TH_IP}</th>
						<th class="colDate">{$TH_DATE}</th>
						<th class="colStatus">{$TH_STATUS}</th>
						<th class="colActionedBy">{$TH_ACTIONED_BY}</th>
						<th class="colActions">{$TH_ACTIONS}</th>
					</tr>
				</thead>
				<tbody>
					<!--&FOREACH($REPORTS,'REPORT_POST_REPORT_ROW')-->
				</tbody>
			</table>
		</div>
		{$DECISION_FORM}
		','<p class="reportEmpty">{$NO_REPORTS_TEXT}</p>')-->
	</form>
