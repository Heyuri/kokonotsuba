	<h2 class="theading2">{$PAGE_TITLE}</h2>
	<div class="banToolbar">[<a href="{$BACK_URL}">{$BACK_TEXT}</a>]</div>

	<h3 class="banSectionHeading">{$HEADING_APPEALS}</h3>
	{$FILTER_FORM}

	<form class="banAppealQueueForm" method="POST" action="{$MODULE_URL}">
		{$CSRF_TOKEN}

		<!--&IF($HAS_APPEALS,'','<p class="banEmpty">{$NO_APPEALS_TEXT}</p>')-->

		<!--&IF($CAN_ACTION,'{$DECISION_BUTTONS}','')-->

		<div class="tableViewportWrapper<!--&IF($HAS_APPEALS,'',' banTableHidden')-->">
			<table class="postlists banAppealTable" id="banAppealQueueTable">
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

		<!--&IF($CAN_ACTION,'{$DECISION_FORM}','')-->
	</form>
