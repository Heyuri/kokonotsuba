	<div class="anonIpContainer">
		<h2>{$TITLE}</h2>

		<!--&IF($SUCCESS_MESSAGE,'<p class="anonIpSuccess">{$SUCCESS_MESSAGE}</p>','')-->

		<div class="anonIpWarning">
			<p><strong>{$WARNING_MESSAGE}</strong></p>
			<ul class="anonIpTargets">
				{$WARNING_TARGETS}
			</ul>
			<p><strong>{$WARNING_UNDO}</strong></p>
		</div>

		<p class="anonIpStatus">
			{$SCHEDULE_STATUS}<br>
			{$LAST_RUN}
		</p>

		<!--&IF($SCHEDULE_NOTE,'<p class="anonIpScheduleNote">{$SCHEDULE_NOTE}</p>','')-->

		<form method="POST" action="{$MODULE_URL}" id="anonIpScheduleForm">
			{$CSRF_TOKEN}
			<input type="hidden" name="anonIpAction" value="schedule">

			<h3>{$SCHEDULE_HEADING}</h3>

			<table class="formtable">
				<tbody>
					<tr>
						<td class="postblock"><label for="scheduleEveryDays">{$SCHEDULE_EVERY_LABEL}</label></td>
						<td>
							<input type="number" class="inputtext" id="scheduleEveryDays" name="scheduleEveryDays" min="0" step="1" value="{$SCHEDULE_EVERY}">
							<div class="formItemDescription">{$SCHEDULE_EVERY_DESC}</div>
						</td>
					</tr>
				</tbody>
			</table>

			<div class="buttonSection">
				<input type="submit" value="{$SCHEDULE_SUBMIT}">
			</div>
		</form>

		<form method="POST" action="{$MODULE_URL}" id="anonIpForm">
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
								<option value="now">{$OPT_NOW}</option>
							</select>
						</td>
					</tr>
				</tbody>
			</table>

			<div class="buttonSection">
				<input type="submit" id="anonIpSubmit" value="{$SUBMIT_BTN}">
			</div>
		</form>
	</div>
