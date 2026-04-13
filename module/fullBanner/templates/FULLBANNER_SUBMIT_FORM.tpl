<h3>{$UPLOAD_HEADING}</h3>
<!--&IF($STATUS_MESSAGE,'{$STATUS_MESSAGE}','')-->
<form action="{$MODULE_PAGE_URL}" method="post" enctype="multipart/form-data">
	<input type="hidden" name="action" value="submitBanner">
	<table class="formtable">
		<tbody>
			<tr>
				<td class="postblock"><label for="banner_file">Banner Image</label></td>
				<td><input type="file" id="banner_file" name="banner_file" accept="image/png,image/jpeg,image/gif"></td>
			</tr>
			<tr>
				<td class="postblock"><label for="banner_link">Destination Link</label></td>
				<td><input type="text" id="banner_link" name="banner_link" class="inputtext" placeholder="https://example.com" size="40"></td>
			</tr>
			<tr>
				<td class="postblock">Rules</td>
				<td>
					<ul class="rules">
						<li>{$REQ_DIMENSIONS}</li>
						<li>{$REQ_FILETYPES}</li>
						<li>{$REQ_FILESIZE}</li>
					</ul>
				</td>
			</tr>
		</tbody>
	</table>
	<div class="buttonSection">
		<input type="submit" value="{$UPLOAD_BUTTON}">
	</div>
</form>
