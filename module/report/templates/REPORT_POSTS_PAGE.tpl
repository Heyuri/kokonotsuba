	<h2 class="theading2">{$PAGE_TITLE}</h2>
	<div class="reportToolbar">[<a href="{$QUEUE_URL}">{$QUEUE_TEXT}</a>]</div>
	<div class="reportIntro">{$TOTAL_TEXT}</div>
	<h3 class="reportSectionHeading">{$HEADING_REPORTED_POSTS}</h3>
	<!--&IF($HAS_POSTS,'
	<div class="tableViewportWrapper">
		<table class="postlists reportTable" id="reportedPostsTable">
			<thead>
				<tr>
					<th class="colPreview">{$TH_PREVIEW}</th>
					<th class="colPost">{$TH_POST}</th>
					<th class="colBoard">{$TH_BOARD}</th>
					<th class="colReportCount">{$TH_REPORT_COUNT}</th>
					<th class="colPendingCount">{$TH_PENDING_COUNT}</th>
					<th class="colApprovedCount">{$TH_APPROVED_COUNT}</th>
					<th class="colDismissedCount">{$TH_DISMISSED_COUNT}</th>
					<th class="colDate">{$TH_LAST_REPORTED}</th>
					<th class="colActions">{$TH_ACTIONS}</th>
				</tr>
			</thead>
			<tbody>
				<!--&FOREACH($POSTS,'REPORT_POSTS_ROW')-->
			</tbody>
		</table>
	</div>
	','<p class="reportEmpty">{$NO_POSTS_TEXT}</p>')-->
