	<h2 class="theading2">{$PAGE_TITLE}</h2>
	<div class="reportToolbar">
		[<a href="{$BACK_URL}">{$BACK_TEXT}</a>]
		[<a href="{$POST_REPORTS_URL}">{$POST_REPORTS_TEXT}</a>]
		<!--&IF($CAN_VIEW_IP,'[<a class="reportPostsLink" href="{$REPORTER_POSTS_URL}" title="{$REPORTER_POSTS_HINT}">{$REPORTER_POSTS_TEXT}</a>]','')-->
	</div>

	<h3 class="reportSectionHeading">{$HEADING_DETAILS}</h3>

	<!--&IF($IS_PENDING,'{$DECISION_BUTTONS}','')-->

	<table class="formtable reportDetailTable">
		<tbody>
			<tr>
				<td class="postblock">{$TH_PREVIEW}</td>
				<td><div class="reportPostPreview modPagePostContainer">{$POST_PREVIEW}</div></td>
			</tr>
			<tr>
				<td class="postblock">{$TH_BOARD}</td>
				<td>{$BOARD_TITLE}</td>
			</tr>
			<tr>
				<td class="postblock">{$TH_REASON}</td>
				<td class="reportReasonCell">{$REPORTER_REASON}</td>
			</tr>
			<tr>
				<td class="postblock">{$TH_IP}</td>
				<td><a class="reportIpLink" href="{$IP_REPORTS_URL}" title="{$IP_REPORTS_TEXT}">{$REPORTER_IP}</a></td>
			</tr>
			<tr>
				<td class="postblock">{$TH_DATE}</td>
				<td>{$DATE_REPORTED}</td>
			</tr>
			<tr>
				<td class="postblock">{$TH_STATUS}</td>
				<td class="{$STATUS_CLASS}">{$STATUS_LABEL}</td>
			</tr>
			<tr>
				<td class="postblock">{$TH_ACTIONED_BY}</td>
				<td><span class="reportActionedBy">{$ACTIONED_BY}</span> <span class="reportActionedAt">{$ACTIONED_AT}</span></td>
			</tr>
			<tr>
				<td class="postblock">{$PUBLIC_REASON_LABEL}</td>
				<td class="reportPublicReason">{$PUBLIC_REASON}</td>
			</tr>
			<tr>
				<td class="postblock">{$PRIVATE_REASON_LABEL}</td>
				<td class="reportPrivateReason">{$PRIVATE_REASON}</td>
			</tr>
			<tr>
				<td class="postblock">{$TH_STATS}</td>
				<td class="reportStats">{$STATS_TABLE}</td>
			</tr>
		</tbody>
	</table>
	<hr>
	<!--&IF($IS_PENDING,'
	<form class="reportDecisionForm" id="reportDecisionForm" method="POST" action="{$MODULE_URL}">
		{$CSRF_TOKEN}
		<input type="hidden" name="reportId" value="{$REPORT_ID}">
		{$DECISION_FORM}
	</form>
	','')-->
