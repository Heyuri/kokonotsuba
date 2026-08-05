	<h2 class="theading2">{$PAGE_TITLE}</h2>
	<div class="reportToolbar">[<a href="{$REPORTED_POSTS_URL}">{$REPORTED_POSTS_TEXT}</a>]</div>
	{$FILTER_HTML}
	<h3 class="reportSectionHeading">{$HEADING_REPORTS}</h3>
	<form class="reportQueueForm" method="POST" action="{$MODULE_URL}">
		{$CSRF_TOKEN}

		<!--&IF($HAS_REPORTS,'','<p class="reportEmpty">{$NO_REPORTS_TEXT}</p>')-->

		<!-- The table is not wrapped in the HAS_REPORTS conditional: the compiler will not nest an
		     IF inside an IF branch, and the per-column conditions below have to be top level. It
		     is hidden with a class instead when there is nothing to list. -->
		<div class="tableViewportWrapper<!--&IF($HAS_REPORTS,'',' reportTableHidden')-->">
			<table class="postlists reportTable" id="reportAdminTable">
				<thead>
					<tr>
						<th class="colSelect"></th>
						<th class="colPost">{$TH_POST}</th>
						<th class="colBoard">{$TH_BOARD}</th>
						<th class="colReason">{$TH_REASON}</th>
						<th class="colIp">{$TH_IP}</th>
						<th class="colDate">{$TH_DATE}</th>
						<th class="colStatus">{$TH_DECISION}</th>
						<!--&IF($SHOW_ACTIONED_BY,'<th class="colActionedBy">{$TH_ACTIONED_BY}</th>','')-->
						<th class="colActions"></th>
					</tr>
				</thead>
				<tbody>
					<!--&FOREACH($REPORTS,'REPORT_ADMIN_ROW')-->
				</tbody>
			</table>
		</div>
		<!--&IF($HAS_REPORTS,'{$DECISION_FORM}','')-->
	</form>
