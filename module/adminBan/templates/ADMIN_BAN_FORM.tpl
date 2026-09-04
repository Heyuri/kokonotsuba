	<div class="banFormContainer">
		<form method="POST" action="{$MODULE_URL}" id="banForm">
			{$CSRF_TOKEN}
			<!--&IF($FORM_HEADING,'<h3 class="centerText">{$FORM_HEADING}</h3>','')-->
			<input type="hidden" name="adminban-action" value="{$FORM_ACTION_VALUE}">
			<input type="hidden" name="banId" value="{$BAN_ID}">

			<table id="banFormTable" class="formtable">
				<tbody>
					<!--&FOREACH($RECORD_ROWS,'BAN_RECORD_ROW')-->
					<input type="hidden" name="postUid" value="{$POST_UID}">
					<tr>
						<td class="postblock"><label for="ipAddress">{$LABEL_IP}</label></td>
						<td>
							<div class="formItemDescription">{$DESC_IP}</div>
							<input type="text" class="inputtext" id="ipAddress" name="ipAddress" value="{$IP}" placeholder="{$PLACEHOLDER_IP}" required>
						</td>
					</tr>
					<tr>
						<td class="postblock"><label for="duration">{$LABEL_DURATION}</label></td>
						<td>
							<input type="text" class="inputtext" id="duration" name="duration" value="{$DURATION}" placeholder="{$PLACEHOLDER_DURATION}">
							<label class="banPermanentLabel"><input type="checkbox" id="banPermanent" name="permanent"{$PERMANENT_CHECKED}> {$LABEL_PERMANENT}</label>
							<div class="formItemDescription">{$DESC_PERMANENT}</div>
							<div class="formItemDescription">{$DESC_DURATION}</div>
						</td>
					</tr>
					<tr>
						<td class="postblock">{$LABEL_CHECKPOINTS}</td>
						<td>
							<div class="formItemDescription">{$DESC_CHECKPOINTS}</div>
							<label class="banSelectAllLabel"><input type="checkbox" id="banSelectAllCheckpoints"> {$SELECT_ALL}</label>
							<ul class="banCheckpointList">
								<!--&FOREACH($CHECKPOINTS,'BAN_CHECKPOINT_ITEM')-->
							</ul>
						</td>
					</tr>
					<tr>
						<td class="postblock"><label for="banprivmsg">{$LABEL_REASON}</label></td>
						<td><textarea class="inputtext" id="banprivmsg" name="privmsg" rows="4" cols="50" placeholder="{$PLACEHOLDER_REASON}">{$REASON}</textarea></td>
					</tr>
					<tr>
						<td class="postblock"><label for="banstaffmsg">{$LABEL_PRIVATE_REASON}</label></td>
						<td>
							<textarea class="inputtext" id="banstaffmsg" name="staffmsg" rows="3" cols="50" placeholder="{$PLACEHOLDER_PRIVATE_REASON}">{$PRIVATE_REASON}</textarea>
							<div class="formItemDescription">{$DESC_PRIVATE_REASON}</div>
						</td>
					</tr>
					<tr>
						<td class="postblock"><label for="banmsg">{$LABEL_PUBLIC_MESSAGE}</label></td>
						<td><textarea class="inputtext" id="banmsg" name="banmsg" rows="4" cols="50" placeholder="{$PLACEHOLDER_PUBLIC_MESSAGE}">{$DEFAULT_BAN_MESSAGE}</textarea></td>
					</tr>
					<tr>
						<td class="postblock"><label for="public">{$LABEL_PUBLIC}</label></td>
						<td><input type="checkbox" id="public" name="public"{$PUBLIC_CHECKED}></td>
					</tr>
					<!--&IF($IS_EDIT,'','<tr><td class="postblock"><label for="global">{$LABEL_GLOBAL}</label></td><td><input type="checkbox" id="global" name="global"><div class="formItemDescription">{$DESC_GLOBAL}</div></td></tr>')-->
					<tr>
						<td class="postblock"><label for="tieToken">{$LABEL_TOKEN}</label></td>
						<td>
							<input type="checkbox" id="tieToken" name="tieToken"{$TOKEN_CHECKED}{$TOKEN_DISABLED}>
							<div class="formItemDescription">{$DESC_TOKEN}</div>
						</td>
					</tr>
					<tr>
						<td class="postblock"><label for="rejectAppeals">{$LABEL_REJECT_APPEALS}</label></td>
						<td>
							<input type="checkbox" id="rejectAppeals" name="rejectAppeals"{$REJECT_APPEALS_CHECKED}>
							<div class="formItemDescription">{$DESC_REJECT_APPEALS}</div>
						</td>
					</tr>
				</tbody>
			</table>

			<div class="buttonSection centerText">
				<input id="bigredbutton" type="submit" value="{$SUBMIT_TEXT}">{$EXTRA_BUTTONS}
			</div>
		</form>
	</div>
