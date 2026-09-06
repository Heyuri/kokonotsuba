<tr class="{$APPROVED_CLASS}">
	<td><input type="checkbox" name="selected_ids[]" value="{$ID}"></td>
	<td>{$DATE}</td>
	<td>{$PRESET_LABEL}</td>
	<td>{$FILE_NAME}</td>
	<!--&IF($USES_LINK,'<td><a href="{$LINK}" target="_blank" rel="noopener noreferrer">{$LINK}</a></td>','<td>&mdash;</td>')-->
	<td>{$IS_APPROVED}</td>
	<td>{$IS_ACTIVE}</td>
	<td><img src="{$IMAGE_URL}" width="{$BANNER_WIDTH}" height="{$BANNER_HEIGHT}" loading="lazy"></td>
</tr>
