	<tr>
		<td>
			<select name="rows[{$ID}][slot]">
				{$SLOT_SELECT}
			</select>
		</td>
		<td>
			<select name="rows[{$ID}][type]">
				<option value="image" {$TYPE_IMAGE_SEL}>image</option>
				<option value="script" {$TYPE_SCRIPT_SEL}>script</option>
			</select>
		</td>
		<td><input type="text" name="rows[{$ID}][src]" value="{$SRC}" class="inputtext" size="30"></td>
		<td><input type="text" name="rows[{$ID}][href]" value="{$HREF}" class="inputtext" size="30"></td>
		<td><input type="text" name="rows[{$ID}][alt]" value="{$ALT}" class="inputtext" size="20"></td>
		<td><textarea name="rows[{$ID}][html]" class="inputtext" cols="25" rows="2">{$HTML}</textarea></td>
		<td><input type="checkbox" name="enabled[{$ID}]" value="1" <!--&IF($ENABLED,'checked','')-->></td>
		<td>{$DATE}</td>
		<td><input type="checkbox" name="delete[]" value="{$ID}"></td>
	</tr>
