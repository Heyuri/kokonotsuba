	<h2 class="theading2">{$PAGE_TITLE}</h2>
	<div class="banToolbar">
		[<a href="{$BACK_URL}">{$BACK_TEXT}</a>]
		[<a href="{$APPEALS_URL}">{$APPEALS_TEXT}</a>]
	</div>

	<h3 class="banSectionHeading">{$HEADING_DETAILS}</h3>

	{$BAN_FORM}

	<!--&IF($CAN_REVOKE,'
	<form class="banRevokeForm" id="banRevokeForm" method="POST" action="{$MODULE_URL}">
		{$CSRF_TOKEN}
		<input type="hidden" name="adminban-action" value="revoke-bans">
		<input type="hidden" name="banIds[]" value="{$BAN_ID}">
	</form>
	','')-->

	<h3 class="banSectionHeading">{$HEADING_APPEALS}</h3>

	<!--&IF($HAS_APPEALS,'','<p class="banEmpty">{$NO_APPEALS_TEXT}</p>')-->

	<div class="tableViewportWrapper<!--&IF($HAS_APPEALS,'',' banTableHidden')-->">
		<table class="postlists banAppealTable banViewAppealTable">
			<thead>
				<tr>
					<th class="colSelect"></th>
					<th class="colBan">{$TH_BAN}</th>
					<th class="colAppellant">{$TH_APPELLANT}</th>
					<th class="colReason">{$TH_REASON}</th>
					<th class="colFiledAt">{$TH_FILED}</th>
					<th class="colStatus">{$TH_STATUS}</th>
					<th class="colActions">{$TH_ACTIONS}</th>
				</tr>
			</thead>
			<tbody>
				<!--&FOREACH($APPEALS,'BAN_APPEAL_ROW')-->
			</tbody>
		</table>
	</div>
