	<h3 class="reportSectionHeading">{$DECISION_HEADING}</h3>
	<div class="formItemDescription reportDecisionLegend">{$DECISION_LEGEND}</div>
	<table class="formtable reportDecisionTable">
		<tbody>
			<tr>
				<td class="postblock"><label for="reportPublicReason">{$PUBLIC_REASON_LABEL}</label></td>
				<td>
					<div class="formItemDescription">{$PUBLIC_REASON_HINT}</div>
					<textarea id="reportPublicReason" name="publicReason" cols="60" rows="3"></textarea>
				</td>
			</tr>
			<tr>
				<td class="postblock"><label for="reportPrivateReason">{$PRIVATE_REASON_LABEL}</label></td>
				<td>
					<div class="formItemDescription">{$PRIVATE_REASON_HINT}</div>
					<textarea id="reportPrivateReason" name="privateReason" cols="60" rows="3"></textarea>
				</td>
			</tr>
		</tbody>
	</table>
	<div class="buttonSection reportDecisionButtons">
		<!--&IF($CAN_APPROVE,'<button type="submit" class="reportApproveButton" name="action" value="approve" title="{$APPROVE_HINT}">{$APPROVE_TEXT}</button>','')-->
		<!--&IF($CAN_DISMISS,'<button type="submit" class="reportDismissButton" name="action" value="dismiss" title="{$DISMISS_HINT}">{$DISMISS_TEXT}</button>','')-->
		<!--&IF($SHOW_CLEAR,'<button type="submit" class="reportClearButton" name="action" value="{$CLEAR_ACTION}" title="{$CLEAR_HINT}">{$CLEAR_TEXT}</button>','')-->
	</div>
