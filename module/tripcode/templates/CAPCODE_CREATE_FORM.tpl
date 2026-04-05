	<div class="capcodeCreateFormContainer">
		<h4>Add capcode</h4>
		<p>Create a capcode that will be added to the capcode list.</p>
		<form method="POST" action="{$MODULE_URL}">
			<table class="capcodeCreateForm">
				<tr>
					<td class="postblock">Tripcode</td>
					<td><input name="rawTripcode" required="" maxlength=11></td>
					<td><small class="formNote">The tripcode along with the trip key. Like "{$REGULAR_TRIP_KEY}.CzKQna1OU" or "{$SECURE_TRIP_KEY}dHdC5plkz6"</small><td>
				</tr>
				<tr>
					<td class="postblock"><label for="capcodeColorHex">Capcode color</label></td>
					<td><input type="color" id="capcodeColorHex" name="capcodeColorHex"></td>
					<td><small class="formNote">Color that the post name will have.</small></td>
				</tr>
				<tr>
					<td class="postblock"><label for="capcodeText">Capcode text</label></td>
					<td><input id="capcodeText" name="capcodeText"></td>
					<td><small class="formNote">Text that gets appended to the name after '##'. E.g 'User{$SECURE_TRIP_KEY}dHdC5plkz6 ## Pezident'</small></td>
				</tr>
				<tr>
					<td class="postblock"><label for="capcodeSubmitButtons"></label></td>
					<td>
						<div id="capcodeSubmitButtons" class="capcodeSubmitButtons">
							<button type="submit"
								name="action"
								value="createCapcode"
								class="adminFunctions adminCreateCapcodeFunction"
								title="Create capcode">
								Submit
							</button>
						</div>
					</td>
				</tr>
			</table>
		</form>
	</div>