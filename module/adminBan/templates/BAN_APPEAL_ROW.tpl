	<tr class="banAppealRow {$ROW_CLASS}">
		<td class="colSelect"><!--&IF($IS_SELECTABLE,'<input type="checkbox" name="appealIds[]" value="{$APPEAL_ID}">','')--></td>
		<td class="colBan">
			<div class="banAppealBanId"><a href="{$BAN_URL}">#{$BAN_ID}</a> {$BAN_IP}</div>
			<!--&IF($BOARD,'<div class="banAppealBanBoard">{$BOARD}</div>','')-->
			<!--&IF($BAN_REASON,'<div class="banAppealAside"><span class="banAppealTag">{$BAN_REASON_LABEL}</span> {$BAN_REASON}</div>','')-->
		</td>
		<td class="colAppellant">{$APPELLANT_IP}</td>
		<td class="colReason"><div class="banAppealReasonText">{$REASON}</div></td>
		<td class="colFiledAt">{$FILED_AT}</td>
		<td class="colStatus">
			<span class="banAppealStatusTag">{$STATUS_LABEL}</span>
			<!--&IF($IS_ACTIONED,'<div class="banAppealActionedBy">{$ACTIONED_BY} — {$ACTIONED_AT}</div>','')-->
			<!--&IF($HAS_NOTE,'<div class="banAppealAside"><span class="banAppealTag">{$STAFF_NOTE_LABEL}</span> {$STAFF_NOTE}</div>','')-->
		</td>
		<td class="colActions">[<a href="{$VIEW_URL}">{$VIEW_TEXT}</a>]</td>
	</tr>
