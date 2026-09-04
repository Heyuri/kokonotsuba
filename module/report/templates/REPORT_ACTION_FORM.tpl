	<div class="reportFormContainer reportActionContainer">
		<h2 class="theading2 reportFormTitle">{$FORM_TITLE}</h2>
		<form class="reportForm reportActionForm" method="POST" action="{$MODULE_URL}">
			{$CSRF_TOKEN}
			<input type="hidden" name="reportId" class="reportActionIdField" value="{$REPORT_ID}">

			{$DECISION_BUTTONS}

			<table class="formtable reportFormTable">
				<tbody>
					<tr class="reportPreviewRow">
						<td class="postblock">{$TH_PREVIEW}</td>
						<td><div class="reportPostPreview modPagePostContainer">{$POST_PREVIEW}</div></td>
					</tr>
					<tr>
						<td class="postblock">{$TH_POST_NUMBER}</td>
						<td><a class="reportActionPostLink" href="{$POST_URL}">No.<span class="reportPostNumberValue">{$POST_NUMBER}</span></a></td>
					</tr>
					<tr>
						<td class="postblock">{$TH_BOARD}</td>
						<td><span class="reportBoardTitle">{$BOARD_TITLE}</span></td>
					</tr>
					<tr>
						<td class="postblock">{$TH_REPORTER_REASON}</td>
						<td class="reportReasonCell reportActionReason">{$REPORTER_REASON}</td>
					</tr>
					<tr>
						<td class="postblock">{$TH_DATE}</td>
						<td class="reportActionDate">{$DATE_REPORTED}</td>
					</tr>
					<tr>
						<td class="postblock"><label for="reportActionPublicReason">{$PUBLIC_REASON_LABEL}</label></td>
						<td>
							<div class="formItemDescription">{$PUBLIC_REASON_HINT}</div>
							<textarea id="reportActionPublicReason" name="publicReason" cols="60" rows="3"></textarea>
						</td>
					</tr>
					<tr>
						<td class="postblock"><label for="reportActionPrivateReason">{$PRIVATE_REASON_LABEL}</label></td>
						<td>
							<div class="formItemDescription">{$PRIVATE_REASON_HINT}</div>
							<textarea id="reportActionPrivateReason" name="privateReason" cols="60" rows="3"></textarea>
						</td>
					</tr>
					<!--&IF($CAN_APPROVE,'<tr>
						<td class="postblock"><label for="reportActionDeletePost">{$DELETE_POST_LABEL}</label></td>
						<td>
							<div class="formItemDescription">{$DELETE_POST_HINT}</div>
							<input type="checkbox" id="reportActionDeletePost" name="deletePost" value="1" checked>
						</td>
					</tr>','')-->
				</tbody>
			</table>
			{$DECISION_BUTTONS}
		</form>
	</div>
