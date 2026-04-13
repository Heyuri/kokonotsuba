	<h3>View capcode</h3>
	[<a href="{$MODULE_URL}">Back</a>]
	<div class="capcodeEntryContainer">
		<form method="POST" action="{$MODULE_URL}">
			<input name="capcodeId" value="{$ID}" type="hidden">
			<table class="capcodeEntry">
				<tr>
					<td class="postblock"><label for="rawTripcode">Tripcode</label></td>
					<td><input id="rawTripcode" name="rawTripcode" value="{$TRIP_KEY}{$TRIPCODE}"></td>
				</tr>
				<tr>
					<td class="postblock"><label for="capcodeColorHex">Capcode color</label></td>
					<td><input type="color" id="capcodeColorHex" name="capcodeColorHex" value="{$CAPCODE_COLOR}"></td>
				</tr>
				<tr>
					<td class="postblock"><label for="capcodeText">Capcode text</label></td>
					<td><input id="capcodeText" name="capcodeText" value="{$CAPCODE_TEXT}"></td>
				</tr>
				<tr>
					<td class="postblock">Preview</td>
					<td><span class="capcodeEntryPreview">{$PREVIEW}</span></td>
				</tr>
				<tr>
					<td class="postblock">ID</td>
					<td><span class="capcodeEntryId">{$ID}</span></td>
				</tr>
				<tr>
					<td class="postblock">Date added</td>
					<td><span class="capcodeEntryDateAdded">{$DATE_ADDED}</span></td>
				</tr>
				<tr>
					<td class="postblock">Added by</td>
					<td><span class="capcodeEntryAddedBy"><!--&IF($ADDED_BY_USERNAME,'{$ADDED_BY_USERNAME}','<i>N/A</i>')--></span></td>
				</tr>
				<tr>
					<td class="postblock"><label for="capcodeSubmitButtons"></label></td>
					<td>
						<div id="capcodeSubmitButtons">
							<button type="submit"
								name="action"
								value="editCapcode"
								class="adminFunctions adminEditCapcodeFunction"
								title="Delete the capcode">
								Submit edit
							</button>
							<button type="submit"
								name="action"
								value="deleteCapcode"
								class="adminFunctions adminDeleteCapcodeFunction"
								title="Delete the capcode">
								Delete
							</button>
						</div>
					</td>
				</tr>
			</table>
		</form>
	</div>