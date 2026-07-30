	<h2 class="theading2">{$PAGE_TITLE}</h2>
	<div class="reportIntro">{$INTRO_TEXT}</div>
	<h3 class="reportSectionHeading">{$HEADING_REPORTS}</h3>
	<!--&IF($HAS_REPORTS,'
	<div class="tableViewportWrapper">
		<table class="postlists reportTable reportMyReportsTable">
			<thead>
				<tr>
					<th class="colPost">{$TH_POST}</th>
					<th class="colBoard">{$TH_BOARD}</th>
					<th class="colReason">{$TH_REASON}</th>
					<th class="colDate">{$TH_DATE}</th>
					<th class="colStatus">{$TH_STATUS}</th>
					<th class="colActionedAt">{$TH_ACTIONED_AT}</th>
					<th class="colStaffReason">{$TH_STAFF_REASON}</th>
				</tr>
			</thead>
			<tbody>
				<!--&FOREACH($REPORTS,'REPORT_MY_REPORTS_ROW')-->
			</tbody>
		</table>
	</div>
	','<p class="reportEmpty">{$NO_REPORTS_TEXT}</p>')-->
