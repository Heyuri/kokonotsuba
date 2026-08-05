	<h2 class="theading2">{$PAGE_TITLE}</h2>
	<div class="reportToolbar">[<a href="{$QUEUE_URL}">{$QUEUE_TEXT}</a>]</div>

	<h3 class="reportSectionHeading">{$HEADING_POST}</h3>
	<div class="reportPostPreview modPagePostContainer reportPostPreviewStandalone">{$POST_PREVIEW}</div>

	<h3 class="reportSectionHeading">{$HEADING_TOTALS}</h3>
	<div class="reportStats">{$STATS_TABLE}</div>

	<!--&IF($CAN_CLEAR,'
	<h3 class="reportSectionHeading">{$CLEAR_TEXT}</h3>
	<form class="reportClearForm" method="POST" action="{$MODULE_URL}">
		{$CSRF_TOKEN}
		<input type="hidden" name="postUid" value="{$POST_UID}">
		<input type="hidden" name="action" value="clearPost">
		<div class="formItemDescription reportClearHint">{$CLEAR_HINT}</div>
		<table class="formtable reportClearTable">
			<tbody>
				<tr>
					<td class="postblock"><label for="reportClearPublicReason">{$PUBLIC_REASON_LABEL}</label></td>
					<td>
						<div class="formItemDescription">{$PUBLIC_REASON_HINT}</div>
						<textarea id="reportClearPublicReason" name="publicReason" cols="60" rows="2"></textarea>
					</td>
				</tr>
				<tr>
					<td class="postblock"><label for="reportClearPrivateReason">{$PRIVATE_REASON_LABEL}</label></td>
					<td>
						<div class="formItemDescription">{$PRIVATE_REASON_HINT}</div>
						<textarea id="reportClearPrivateReason" name="privateReason" cols="60" rows="2"></textarea>
					</td>
				</tr>
			</tbody>
		</table>
		<div class="buttonSection">
			<button type="submit" class="reportClearButton">{$CLEAR_BUTTON_TEXT}</button>
		</div>
	</form>
	','')-->

	<h3 class="reportSectionHeading">{$HEADING_REPORTS}</h3>
	<form class="reportQueueForm" method="POST" action="{$MODULE_URL}">
		{$CSRF_TOKEN}
		<input type="hidden" name="postUid" value="{$POST_UID}">

		<!--&IF($HAS_REPORTS,'','<p class="reportEmpty reportWindowEmpty">{$NO_REPORTS_TEXT}</p>')-->

		<!-- The table always renders: the reports window clones this same block and fills the
		     tbody client-side, and an IF around it could not also hold the per-column IFs the
		     compiler refuses to nest. It is hidden instead when there is nothing in it. -->
		<div class="tableViewportWrapper reportWindowTableWrapper<!--&IF($HAS_REPORTS,'',' reportTableHidden')-->">
			<table class="postlists reportTable">
				<thead>
					<tr>
						<th class="colSelect"></th>
						<th class="colReason">{$TH_REASON}</th>
						<th class="colIp">{$TH_IP}</th>
						<th class="colDate">{$TH_DATE}</th>
						<th class="colStatus">{$TH_DECISION}</th>
						<th class="colActionedBy">{$TH_ACTIONED_BY}</th>
						<th class="colActions"></th>
					</tr>
				</thead>
				<tbody class="reportWindowRows">
					<!--&FOREACH($REPORTS,'REPORT_POST_REPORT_ROW')-->
				</tbody>
			</table>
		</div>
		{$DECISION_FORM}
	</form>
