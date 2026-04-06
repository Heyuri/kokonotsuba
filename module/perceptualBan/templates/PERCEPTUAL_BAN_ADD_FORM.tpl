	<h3>{$PERCEPTUAL_BAN_ADD_TITLE}</h3>
	<form action="{$MODULE_URL}" method="POST">
		<input type="hidden" name="action" value="addBan">
		<table>
			<tbody>
				<tr>
					<td class="postblock"><label for="perceptualBanHash">{$PERCEPTUAL_BAN_HASH_LABEL}</label></td>
					<td><input type="text" class="inputtext" name="phash" id="perceptualBanHash" value="{$PHASH_VALUE}" size="18" maxlength="16" required></td>
				</tr>
			</tbody>
		</table>
		<p>{$PERCEPTUAL_BAN_THRESHOLD_LABEL}: {$THRESHOLD}</p>
		<div class="buttonSection">
			<input type="submit" value="{$FORM_SUBMIT_BTN}">
		</div>
	</form>
