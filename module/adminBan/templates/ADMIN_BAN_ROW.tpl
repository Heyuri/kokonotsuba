	<tr class="banRow {$ROW_CLASS}">
		<td class="colSelect"><!--&IF($CAN_REVOKE,'<input type="checkbox" name="banIds[]" value="{$BAN_ID}">','')--></td>
		<td class="colPattern">{$IP}</td>
		<td class="colBoard">{$BOARD}</td>
		<td class="colFiledBy">{$FILED_BY}</td>
		<td class="colFiledAt">{$FILED_AT}</td>
		<td class="colDuration">{$DURATION}</td>
		<td class="colReason">
			{$REASON}
			<!--&IF($HAS_PRIVATE_REASON,'<div class="banPrivateReason"><span class="banReasonTag">{$PRIVATE_REASON_LABEL}</span> {$PRIVATE_REASON}</div>','')-->
		</td>
		<td class="colPost"><!--&IF($HAS_POST,'<a href="{$POST_URL}">No.{$POST_NUMBER}</a>','')--></td>
		<td class="colSeen">{$SEEN}</td>
		<td class="colStatus">
			{$STATUS}
			<!--&IF($HAS_APPEAL,'<span class="banAppealFlag">{$APPEAL_TEXT}</span>','')-->
		</td>
		<td class="colActions">
			[<a href="{$VIEW_URL}">{$VIEW_TEXT}</a>]
		</td>
	</tr>
