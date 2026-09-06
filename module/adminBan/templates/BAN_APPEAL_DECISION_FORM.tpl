	<h3 class="banSectionHeading">{$DECISION_HEADING}</h3>
	<div class="banAppealDecisionLegend">{$DECISION_LEGEND}</div>

	<table class="formtable banAppealDecisionTable">
		<tbody>
			<tr>
				<td class="postblock"><label for="{$ID_PREFIX}Note">{$LABEL_NOTE}</label></td>
				<td>
					<div class="formItemDescription">{$DESC_NOTE}</div>
					<textarea class="inputtext" id="{$ID_PREFIX}Note" name="staffNote" rows="3" cols="60" placeholder="{$PLACEHOLDER_NOTE}"></textarea>
				</td>
			</tr>
			<tr>
				<td class="postblock"><label for="{$ID_PREFIX}Reduce">{$LABEL_REDUCE}</label></td>
				<td>
					<div class="formItemDescription">{$DESC_REDUCE}</div>
					<input type="text" class="inputtext" id="{$ID_PREFIX}Reduce" name="reduceTo" placeholder="{$PLACEHOLDER_REDUCE}">
				</td>
			</tr>
		</tbody>
	</table>

	{$DECISION_BUTTONS}
