	<tr class="reportRow {$STATUS_CLASS}">
		<td class="colSelect"><!--&IF($IS_PENDING,'<input type="checkbox" name="reportIds[]" value="{$REPORT_ID}">','<input type="checkbox" disabled title="{$SELECT_DISABLED_TITLE}">')--></td>
		<td class="colId">{$REPORT_ID}</td>
		<td class="colReason">{$REPORTER_REASON}</td>
		<td class="colIp"><a class="reportIpLink" href="{$IP_REPORTS_URL}" title="{$IP_REPORTS_TEXT}">{$REPORTER_IP}</a></td>
		<td class="colDate">{$DATE_REPORTED}</td>
		<td class="colStatus">
			<span class="reportStatusLabel">{$STATUS_LABEL}</span>
			<!--&IF($PUBLIC_REASON,'<div class="reportPublicReason"><span class="reportReasonTag">{$PUBLIC_REASON_LABEL}</span> {$PUBLIC_REASON}</div>','')-->
			<!--&IF($PRIVATE_REASON,'<div class="reportPrivateReason"><span class="reportReasonTag">{$PRIVATE_REASON_LABEL}</span> {$PRIVATE_REASON}</div>','')-->
		</td>
		<td class="colActionedBy">
			<span class="reportActionedBy">{$ACTIONED_BY}</span>
			<span class="reportActionedAt">{$ACTIONED_AT}</span>
		</td>
		<td class="colActions">[<a href="{$VIEW_URL}">{$VIEW_TEXT}</a>]</td>
	</tr>
