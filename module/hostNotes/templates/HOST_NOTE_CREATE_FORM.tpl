	<div class="noteFormContainer hostNoteFormContainer">
		<h3>{$FORM_TITLE}</h3>
		<div class="noteForm">
			<form method="POST" action="{$MODULE_URL}">
				{$CSRF_TOKEN}
				<input name="action" value="addNote" type="hidden">
				<input name="postUid" value="<!--&IF($POST_UID,'{$POST_UID}','')-->" type="hidden">
				<input name="ipPattern" value="{$IP_PATTERN}" type="hidden">
				<input name="visitorTokenHash" value="{$VISITOR_TOKEN_HASH}" type="hidden">
				<table>
					<tbody>
						<tr>
							<td class="postblock"><label>{$FORM_TARGET}</label></td>
							<td>
								<div class="formItemDescription">{$HOST_NOTE_TARGET_DESCRIPTION}</div>
								<div class="hostNoteTargetChoice">
									<label class="hostNoteHostChoice"><input type="radio" name="noteTarget" value="host" checked> {$FORM_TARGET_HOST} <span class="hostNoteFormPattern">{$IP_PATTERN}</span></label>
									<label class="hostNoteBrowserChoice" <!--&IF($HAS_BROWSER_TARGET,'','hidden')-->><input type="radio" name="noteTarget" value="browser"> {$FORM_TARGET_BROWSER} <span class="hostNoteFormToken">{$VISITOR_TOKEN_LABEL}</span></label>
								</div>
							</td>
						</tr>
						<tr>
							<td class="postblock"><label for="note">{$FORM_NOTE}</label></td>
							<td>
								<div class="formItemDescription hostNoteVisibility" data-host-description="{$HOST_NOTE_VISIBILITY_DESCRIPTION}" data-browser-description="{$BROWSER_NOTE_VISIBILITY_DESCRIPTION}">{$HOST_NOTE_VISIBILITY_DESCRIPTION}</div>
								<textarea id="note" name="note" cols="80" rows="6"></textarea>
							</td>
						</tr>
					</tbody>
				</table>

				<div class="buttonSection">
					<input type="submit" value="{$SUBMIT_TEXT}">
				</div>
			</form>
		</div>
	</div>
