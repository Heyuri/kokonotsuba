	<div class="anonIpContainer">
		<h2>{$TITLE}</h2>

		<!--&IF($SUCCESS_MESSAGE,'<p class="anonIpSuccess">{$SUCCESS_MESSAGE}</p>','')-->

		<p class="anonIpWarning">
			<strong>{$WARNING_MESSAGE}</strong>
		</p>

		<form method="POST" action="{$MODULE_URL}">
			{$CSRF_TOKEN}
			<input type="hidden" name="anonIpAction" value="anonymize">

			<table class="formtable">
				<tbody>
					<tr>
						<td class="postblock"><label for="timeframe">{$SELECT_LABEL}</label></td>
						<td>
							<select name="timeframe" id="timeframe" class="inputtext">
								<option value="1year">{$OPT_1_YEAR}</option>
								<option value="1month">{$OPT_1_MONTH}</option>
								<option value="1week">{$OPT_1_WEEK}</option>
								<option value="24hours">{$OPT_24_HOURS}</option>
							</select>
						</td>
					</tr>
				</tbody>
			</table>

			<div class="buttonSection">
				<input type="submit" value="{$SUBMIT_BTN}">
			</div>
		</form>
	</div>
