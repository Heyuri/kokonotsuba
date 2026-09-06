<h3>{$UPLOAD_HEADING} ({$PRESET_LABEL})</h3>
<form action="{$MODULE_PAGE_URL}" method="post" enctype="multipart/form-data">
	<input type="hidden" name="action" value="submitBanner">
	<input type="hidden" name="preset" value="{$PRESET_KEY}">
	<table class="formtable">
		<tbody>
			<tr>
				<td class="postblock"><label for="banner_file">Banner Image</label></td>
				<td><input type="file" id="banner_file" name="banner_file" accept="image/png,image/jpeg,image/gif"></td>
			</tr>
			<!--&IF($USES_LINK,'<tr><td class="postblock"><label for="banner_link">Destination Link</label></td><td><input type="text" id="banner_link" name="banner_link" class="inputtext" placeholder="https://example.com" size="40"></td></tr>','')-->
			<tr>
				<td class="postblock">Rules</td>
				<td>
					<ul class="rules">
						<!--&FOREACH($REQUIREMENTS,'BANNER_REQUIREMENT')-->
					</ul>
				</td>
			</tr>
		</tbody>
	</table>
	<div class="buttonSection">
		<input type="submit" value="{$UPLOAD_BUTTON}">
	</div>
</form>
