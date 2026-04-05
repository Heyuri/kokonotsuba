	<h3>{$FILE_BAN_ADD_TITLE}</h3>
	<form action="{$MODULE_URL}" method="POST">
		<input type="hidden" name="action" value="addBan">
		<table>
			<tbody>
				<tr>
					<td class="postblock"><label for="fileBanMd5">{$FILE_BAN_HASH_LABEL}</label></td>
					<td><input type="text" class="inputtext" name="md5" id="fileBanMd5" value="{$MD5_VALUE}" size="34" maxlength="32" required></td>
				</tr>
			</tbody>
		</table>
		<div class="buttonSection">
			<input type="submit" value="{$FORM_SUBMIT_BTN}">
		</div>
	</form>