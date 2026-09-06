	<h2 class="theading2">{$PAGE_TITLE}</h2>
	<div class="banToolbar">
		[<a href="{$BACK_URL}">{$BACK_TEXT}</a>]
		[<a href="{$BAN_URL}">{$BAN_TEXT}</a>]
	</div>

	<h3 class="banSectionHeading">{$HEADING_APPEAL}</h3>
	<table class="formtable banDetailTable">
		<tbody>
			<tr><td class="postblock">{$LABEL_APPEAL_STATUS}</td><td class="{$STATUS_CLASS}">{$STATUS}</td></tr>
			<tr><td class="postblock">{$LABEL_APPELLANT}</td><td>{$APPELLANT_IP}</td></tr>
			<tr><td class="postblock">{$LABEL_APPEAL_FILED}</td><td>{$FILED_AT}</td></tr>
			<tr><td class="postblock">{$LABEL_APPEAL_REASON}</td><td class="banAppealReasonCell">{$REASON}</td></tr>
			<!--&IF($IS_ACTIONED,'<tr><td class="postblock">{$LABEL_DECIDED_BY}</td><td>{$ACTIONED_BY} — {$ACTIONED_AT}</td></tr>','')-->
			<!--&IF($HAS_NOTE,'<tr><td class="postblock">{$LABEL_STAFF_NOTE}</td><td class="banAppealNote">{$STAFF_NOTE}</td></tr>','')-->
		</tbody>
	</table>

	<h3 class="banSectionHeading">{$HEADING_BAN}</h3>
	<table class="formtable banDetailTable">
		<tbody>
			<!--&IF($POST_PREVIEW,'<tr><td class="postblock">{$LABEL_PREVIEW}</td><td><div class="banPostPreview modPagePostContainer">{$POST_PREVIEW}</div></td></tr>','')-->
			<tr><td class="postblock">{$LABEL_IP}</td><td>{$IP}</td></tr>
			<tr><td class="postblock">{$LABEL_BOARD}</td><td>{$BOARD}</td></tr>
			<tr><td class="postblock">{$LABEL_STATUS}</td><td class="{$BAN_STATUS_CLASS}">{$BAN_STATUS}</td></tr>
			<tr><td class="postblock">{$LABEL_DURATION}</td><td>{$DURATION}</td></tr>
			<tr><td class="postblock">{$LABEL_EXPIRES}</td><td>{$EXPIRES_AT}</td></tr>
			<tr><td class="postblock">{$LABEL_REASON}</td><td class="banDetailReason">{$BAN_REASON}</td></tr>
		</tbody>
	</table>

	<!--&IF($CAN_DECIDE,'
	<hr class="threadSeparator">

	<form class="banAppealDecisionForm" method="POST" action="{$MODULE_URL}">
		{$CSRF_TOKEN}
		<input type="hidden" name="appealIds[]" value="{$APPEAL_ID}">
		{$DECISION_FORM}
	</form>
	','')-->
