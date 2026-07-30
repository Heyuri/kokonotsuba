	<div class="reportFormContainer">
		<h2 class="theading2 reportFormTitle">{$FORM_TITLE}</h2>
		<form class="reportForm" method="POST" action="{$MODULE_URL}">
			{$CSRF_TOKEN}
			<input type="hidden" name="postUid" class="reportPostUidField" value="{$POST_UID}">
			<table class="formtable reportFormTable">
				<tbody>
					<tr class="reportPreviewRow">
						<td class="postblock">{$TH_PREVIEW}</td>
						<td><div class="reportPostPreview modPagePostContainer">{$POST_PREVIEW}</div></td>
					</tr>
					<tr>
						<td class="postblock">{$TH_POST_NUMBER}</td>
						<td><span class="reportPostNumber">No.<span class="reportPostNumberValue">{$POST_NUMBER}</span></span></td>
					</tr>
					<tr>
						<td class="postblock">{$TH_BOARD}</td>
						<td><span class="reportBoardTitle">{$BOARD_TITLE}</span></td>
					</tr>
					<tr>
						<td class="postblock"><label for="reportReason">{$TH_REASON}</label></td>
						<td>
							<div class="formItemDescription">{$REASON_HINT}</div>
							<textarea id="reportReason" name="reason" cols="60" rows="6" maxlength="{$REASON_MAX_LENGTH}"></textarea>
						</td>
					</tr>
				</tbody>
			</table>
			<div class="buttonSection">
				<input type="submit" value="{$SUBMIT_TEXT}">
			</div>
		</form>
	</div>
