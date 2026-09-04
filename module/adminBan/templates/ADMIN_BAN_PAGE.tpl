	<h2 class="theading2">{$PAGE_TITLE}</h2>
	<div class="banToolbar">[<a href="{$APPEALS_URL}">{$APPEALS_TEXT}</a>]</div>

	{$BAN_FORM}

	<h3 class="banSectionHeading">{$HEADING_BANS}</h3>
	{$FILTER_FORM}

	<form class="banTableForm" method="POST" action="{$MODULE_URL}">
		{$CSRF_TOKEN}
		<input type="hidden" name="adminban-action" value="revoke-bans">

		<!--&IF($HAS_BANS,'','<p class="banEmpty">{$NO_BANS_TEXT}</p>')-->

		<!--&IF($CAN_REVOKE,'<div class="buttonSection"><button type="submit" class="banRevokeButton">{$REVOKE_TEXT}</button></div>','')-->

		<div class="tableViewportWrapper<!--&IF($HAS_BANS,'',' banTableHidden')-->">
			<table class="postlists banTable" id="banAdminTable">
				<thead>
					<tr>
						<th class="colSelect"></th>
						<th class="colPattern">{$TH_IP}</th>
						<th class="colBoard">{$TH_BOARD}</th>
						<th class="colFiledBy">{$TH_FILED_BY}</th>
						<th class="colFiledAt">{$TH_FILED_AT}</th>
						<th class="colDuration">{$TH_DURATION}</th>
						<th class="colReason">{$TH_REASON}</th>
						<th class="colPost">{$TH_POST}</th>
						<th class="colSeen">{$TH_SEEN}</th>
						<th class="colStatus">{$TH_STATUS}</th>
						<th class="colActions"></th>
					</tr>
				</thead>
				<tbody>
					<!--&FOREACH($ROWS,'ADMIN_BAN_ROW')-->
				</tbody>
			</table>
		</div>

		<!--&IF($CAN_REVOKE,'<div class="buttonSection"><button type="submit" class="banRevokeButton">{$REVOKE_TEXT}</button></div>','')-->
	</form>
