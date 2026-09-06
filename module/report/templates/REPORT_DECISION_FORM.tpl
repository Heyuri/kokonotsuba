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
			<!--&IF($CAN_APPROVE,'<tr>
				<td class="postblock"><label for="reportDeletePost">{$DELETE_POST_LABEL}</label></td>
				<td>
					<div class="formItemDescription">{$DELETE_POST_HINT}</div>
					<input type="checkbox" id="reportDeletePost" name="deletePost" value="1" checked>
				</td>
			</tr>','')-->
		</tbody>
	</table>
	{$DECISION_BUTTONS}
