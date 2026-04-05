	<h3>View rule</h3>
	[<a href="{$MODULE_URL}">Back</a>]
	<div class="rulesetEntryContainer">
		<form method="POST" action="{$MODULE_URL}">
			<input name="entryId" value="{$ID}" type="hidden">
			<input name="action" value="update" type="hidden">
			<table class="ruleEntry">
				<tbody>
					<tr>
						<td class="postblock"><label for="pattern">Pattern</label></td>
						<td><textarea id="pattern" name="pattern"><!--&IF($PATTERN,'{$PATTERN}','')--></textarea></td>
					</tr>
					<tr>
						<td class="postblock"><label for="matchType">Match type</label></td>
						<td>
							<select name="matchType">
								<option value="contains"<!--&IF($CONTAINS_SELECTED,' selected','')-->>Contains</option>
								<option value="exact"<!--&IF($EXACT_SELECTED,' selected','')-->>Exact match</option>
								<option value="fuzzy"<!--&IF($FUZZY_SELECTED,' selected','')-->>Fuzzy</option>
								<option value="regex"<!--&IF($REGEX_SELECTED,' selected','')-->>Regex</option>
							</select>
						</td>
					</tr>
					<tr>
						<td class="postblock"><label for="maxDistance">Distance</label></td>
						<td>
							<input id="maxDistance" name="maxDistance" min="0" max="4" type="number" value="<!--&IF($MAX_DISTANCE,'{$MAX_DISTANCE}','')-->">
						</td>
					</tr>
					<tr>
						<td class="postblock"><label for="fields">Fields</label></td>
						<td>
							<ul class="boardFilterList" id="fields">
								<li><label><input type="checkbox" name="matchField[]" value="subject"<!--&IF($SUBJECT_SELECTED,' checked','')-->>Subject</label></li>
								<li><label><input type="checkbox" name="matchField[]" value="comment"<!--&IF($COMMENT_SELECTED,' checked','')-->>Comment</label></li>
								<li><label><input type="checkbox" name="matchField[]" value="name"<!--&IF($NAME_SELECTED,' checked','')-->>Name</label></li>
								<li><label><input type="checkbox" name="matchField[]" value="email"<!--&IF($EMAIL_SELECTED,' checked','')-->>Email</label></li>
								<li><label><input type="checkbox" name="matchField[]" value="filename"<!--&IF($FILENAME_SELECTED,' checked','')-->>Filename</label></li>
							</ul>
						</td>
					</tr>
					<tr>
						<td class="postblock"><label for="matchCase">Match case</label></td>
						<td>
							<label><input type="checkbox" id="matchCase" name="matchCase" value="1"<!--&IF($CASE_SENSITIVE,' checked','')-->>Case sensitive</label>
						</td>
					</tr>
					<tr>
						<td class="postblock"><label for="applyOpOnly">OP only</label></td>
						<td>
							<label><input type="checkbox" id="applyOpOnly" name="applyOpOnly" value="1"<!--&IF($OP_ONLY_SELECTED,' checked','')-->>Only apply to opening posts</label>
						</td>
					</tr>
					<tr>
						<td class="postblock"><label for="silentReject">Silent reject</label></td>
						<td>
							<label><input type="checkbox" id="silentReject" name="silentReject" value="1"<!--&IF($SILENT_REJECT_SELECTED,' checked','')-->>Silently redirect instead of showing an error</label>
						</td>
					</tr>
					<tr>
						<td class="postblock"><label for="spamAction">Action</label></td>
						<td>
							<select id="spamAction" name="spamAction">
								<option value="reject"<!--&IF($REJECT_SELECTED,' selected','')-->>Reject</option>
								<option value="mute"<!--&IF($MUTE_SELECTED,' selected','')-->>Mute</option>
								<option value="ban"<!--&IF($BAN_SELECTED,' selected','')-->>Ban</option>
							</select>
						</td>
					</tr>
					<tr>
						<td class="postblock"><label for="description">Description</label></td>
						<td>
							<textarea id="description" name="description" placeholder="Stops an advertising bot."><!--&IF($DESCRIPTION,'{$DESCRIPTION}','')--></textarea>
						</td>
					</tr>
					<tr>
						<td class="postblock"><label for="isActive">Status</label></td>
						<td>
							<label><input type="checkbox" id="isActive" name="isActive" value="1"<!--&IF($IS_ACTIVE,' checked','')-->>Active</label>
						</td>
					</tr>
					<tr>
						<td class="postblock"><label for="userMessage">User message</label></td>
						<td>
							<textarea id="userMessage" name="userMessage" placeholder="Your post contained content that tripped spam filters."><!--&IF($USER_MESSAGE,'{$USER_MESSAGE}','')--></textarea>
						</td>
					</tr>
					<tr>
						<td class="postblock">Date added</td>
						<td><span class="spamRuleCreatedAt">{$CREATED_AT}</span></td>
					</tr>
					<tr>
						<td class="postblock">Created by</td>
						<td><span class="spamRuleCreatedBy"><!--&IF($CREATED_BY,'{$CREATED_BY}','<i>N/A</i>')--></span></td>
					</tr>
				</tbody>
			</table>
			<input type="submit" value="Submit">
		</form>
	</div>