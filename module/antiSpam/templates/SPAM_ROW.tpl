	<tr>
		<td><div class="centerText"><input name="entryIDs[]" type="checkbox" value="{$ID}"></div></td>
		<td>{$PATTERN}</td>
		<td><!--&IF($IS_ACTIVE,'Yes','No')--></td>
		<td><!--&IF($DESCRIPTION,'{$DESCRIPTION}','')--></td>
		<td><!--&IF($ACTION,'{$ACTION}','')--></td>
		<td><!--&IF($MATCH_TYPE,'{$MATCH_TYPE}','')--></td>
		<td><!--&IF($APPLIED_FIELDS,'{$APPLIED_FIELDS}','')--></td>
		<td><a href="{$VIEW_ENTRY_URL}">View</a></td>
	</tr>