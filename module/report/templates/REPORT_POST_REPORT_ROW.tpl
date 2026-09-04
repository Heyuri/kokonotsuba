	<tr class="reportRow {$STATUS_CLASS}">
		<td class="colSelect"><!--&IF($IS_PENDING,'<input type="checkbox" name="reportIds[]" value="{$REPORT_ID}">','<input type="checkbox" disabled title="{$SELECT_DISABLED_TITLE}">')--></td>
		<td class="colReason reportRowReason">{$REPORTER_REASON}</td>
		<td class="colIp"><a class="reportIpLink reportRowIpLink" href="{$IP_REPORTS_URL}" title="{$IP_REPORTS_TEXT}">{$REPORTER_IP}</a></td>
		<td class="colDate reportRowDate">{$DATE_REPORTED}</td>
		<td class="colStatus">
			<!--&IF($SHOW_STATUS,'<span class="reportStatusLabel">{$STATUS_LABEL}</span>','')-->
			<!--&IF($PUBLIC_REASON,'<div class="reportPublicReason"><span class="reportReasonTag">{$PUBLIC_REASON_LABEL}</span> {$PUBLIC_REASON}</div>','')-->
			<!--&IF($PRIVATE_REASON,'<div class="reportPrivateReason"><span class="reportReasonTag">{$PRIVATE_REASON_LABEL}</span> {$PRIVATE_REASON}</div>','')-->
		</td>
		<td class="colActionedBy">
			<span class="reportActionedBy">{$ACTIONED_BY}</span>
			<span class="reportActionedAt">{$ACTIONED_AT}</span>
		</td>
		<td class="colActions">[<a class="reportActionLink reportRowActionLink" href="{$ACTION_URL}" data-report-url="{$ACTION_DATA_URL}" data-report-id="{$REPORT_ID}">{$ACTION_TEXT}</a>] [<a class="reportRowViewLink" href="{$VIEW_URL}">{$VIEW_TEXT}</a>] <!--&IF($CAN_VIEW_IP,'[<a class="reportPostsLink reportRowPostsLink" href="{$REPORTER_POSTS_URL}" title="{$REPORTER_POSTS_HINT}">{$REPORTER_POSTS_TEXT}</a>]','')--> <!--&IF($CAN_CLEAR,'[<button type="submit" class="buttonLink reportClearIpButton" name="clearIpReportId" value="{$REPORT_ID}" title="{$CLEAR_IP_HINT}">{$CLEAR_IP_TEXT}</button>]','')--></td>
	</tr>
