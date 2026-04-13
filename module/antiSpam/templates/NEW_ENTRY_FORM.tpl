	<div class="newEntryForm">
		<form method="POST" action="{$MODULE_URL}">
			<input name="action" value="addEntry" type="hidden">
			<table>
				<tbody>
					<tr>
						<td class="postblock"><label for="pattern">Pattern</label></td>
						<td>
							<div class="formItemDescription">The string you want to ban. Enter raw regex if using the regex match type.</div>
							<textarea id="pattern" name="pattern" placeholder="Spicy viagra pills for just 19.31! a pop!" required><!--&IF($PATTERN_VALUE,'{$PATTERN_VALUE}','')--></textarea>
						</td>
					</tr>
					<tr>
						<td class="postblock"><label for="matchType">Match type</label></td>
						<td>
							<div class="formItemDescription">The way strings are checked. </div>
							<select name="matchType">
								<option value="contains">Contains</option>
								<option value="exact">Exact match</option>
								<option value="fuzzy">Fuzzy</option>
								<option value="regex">Regex</option>
							</select>
						</td>
					</tr>
					<tr>
						<td class="postblock"><label for="maxDistance">Distance</label></td>
						<td>
							<div class="formItemDescription">For the fuzzy match type. The higher the distance, the less strict it is. Higher values may increase cases of false positives.</div>
							<input id="maxDistance" name="maxDistance" min="0" max="4" type="number" value="3">
						</td>
					</tr>
					<tr>
						<td class="postblock"><label for="fields">Fields</label></td>
						<td>
							<div class="formItemDescription">The fields that will be checked when the post is submitted.</div>
							<ul class="boardFilterList" id="fields">
								<li><label><input type="checkbox" name="matchField[]" value="subject" checked>Subject</label></li>
								<li><label><input type="checkbox" name="matchField[]" value="comment" checked>Comment</label></li>
								<li><label><input type="checkbox" name="matchField[]" value="name" checked>Name</label></li>
								<li><label><input type="checkbox" name="matchField[]" value="email" checked>Email</label></li>
								<li><label><input type="checkbox" name="matchField[]" value="filename" checked>Filename</label></li>
							</ul>
						</td>
					</tr>
					<tr>
						<td class="postblock"><label for="matchCase">Match case</label></td>
						<td>
							<div class="formItemDescription">Whether it should be case sensitive.</div>
							<label><input type="checkbox" id="matchCase" name="matchCase" value="1">Case sensitive</label>
						</td>
					</tr>
					<tr>
						<td class="postblock"><label for="applyOpOnly">OP only</label></td>
						<td>
							<div class="formItemDescription">Only check this rule against new thread submissions (opening posts).</div>
							<label><input type="checkbox" id="applyOpOnly" name="applyOpOnly" value="1">Only apply to opening posts</label>
						</td>
					</tr>
					<tr>
						<td class="postblock"><label for="silentReject">Silent reject</label></td>
						<td>
							<div class="formItemDescription">Silently redirect the user to the board index instead of showing an error message.</div>
							<label><input type="checkbox" id="silentReject" name="silentReject" value="1">Silently redirect instead of showing an error</label>
						</td>
					</tr>
					<tr>
						<td class="postblock"><label for="spamAction">Action</label></td>
						<td>
							<div class="formItemDescription">What happens to the user or their post once its caught by the spam rule.</div>
							<select id="spamAction" name="spamAction">
								<option value="reject">Reject</option>
								<option value="mute">Mute</option>
								<option value="ban">Ban</option>
							</select>
						</td>
					</tr>
					<tr>
						<td class="postblock"><label for="description">Description</label></td>
						<td>
							<div class="formItemDescription">Describes what the filter is for.</div>
							<textarea id="description" name="description" placeholder="Stops an advertising bot."></textarea>
						</td>
					</tr>
					<tr>
						<td class="postblock"><label for="userMessage">User message</label></td>
						<td>
							<div class="formItemDescription">The error message a user will see if they trip the spam rule. (Leave blank for a default message)</div>
							<textarea id="userMessage" name="userMessage" placeholder="Your post contained content that tripped spam filters."></textarea>
						</td>
					</tr>
				</tbody>
			</table>

			<div class="buttonSection">
				<input type="submit" value="Submit">
			</div>
		</form>
	</div>