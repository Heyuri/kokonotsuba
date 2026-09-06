	<!--&IF($HAS_IP,'<p class="banIpFilterNotice">{$IP_FILTER_TEXT} [<a href="{$CLEAR_IP_URL}">{$CLEAR_IP_TEXT}</a>]</p>','')-->

	<form class="detailsboxForm formtable" id="banFilterForm" method="GET" action="{$MODULE_URL}">
		<details id="filtercontainer" class="detailsbox" {$OPEN_ATTR}>
			<summary>{$SUMMARY_TEXT}</summary>
			<div class="detailsboxContent">
				<input type="hidden" name="mode" value="module">
				<input type="hidden" name="load" value="adminBan">
				<input type="hidden" name="moduleMode" value="admin">

				<table class="centerBlock">
					<tbody>
						<tr>
							<td class="postblock"><label for="banFilterGeneral">{$LABEL_GENERAL}</label></td>
							<td><input class="inputtext" id="banFilterGeneral" name="general" value="{$GENERAL}" placeholder="{$PLACEHOLDER_GENERAL}"></td>
						</tr>
						{$ADDRESS_ROWS}
						<tr>
							<td class="postblock"><label for="banFilterReason">{$LABEL_REASON}</label></td>
							<td><input class="inputtext" id="banFilterReason" name="reason" value="{$REASON}"></td>
						</tr>
						<tr>
							<td class="postblock"><label for="banFilterStaff">{$LABEL_STAFF}</label></td>
							<td><input class="inputtext" id="banFilterStaff" name="staff" value="{$STAFF}"></td>
						</tr>
						<tr>
							<td class="postblock"><label for="banFilterId">{$LABEL_BAN_ID}</label></td>
							<td><input class="inputtext" type="number" min="1" id="banFilterId" name="banId" value="{$BAN_ID}"></td>
						</tr>
						<tr>
							<td class="postblock"><label for="banFilterPost">{$LABEL_POST}</label></td>
							<td><input class="inputtext" type="number" min="1" id="banFilterPost" name="postNumber" value="{$POST_NUMBER}"></td>
						</tr>
						<tr>
							<td class="postblock"><label for="banFilterFrom">{$LABEL_DATE_AFTER}</label></td>
							<td><input class="inputtext" type="date" id="banFilterFrom" name="dateAfter" value="{$DATE_AFTER}"></td>
						</tr>
						<tr>
							<td class="postblock"><label for="banFilterTo">{$LABEL_DATE_BEFORE}</label></td>
							<td><input class="inputtext" type="date" id="banFilterTo" name="dateBefore" value="{$DATE_BEFORE}"></td>
						</tr>
						<tr>
							<td class="postblock"><label for="banFilterStatus">{$LABEL_STATUS}</label></td>
							<td><select class="inputtext" id="banFilterStatus" name="status">{$STATUS_OPTIONS}</select></td>
						</tr>
						<tr>
							<td class="postblock"><label for="banFilterKind">{$LABEL_KIND}</label></td>
							<td><select class="inputtext" id="banFilterKind" name="kind">{$KIND_OPTIONS}</select></td>
						</tr>
						<tr id="bancheckpointrow">
							<td class="postblock">{$LABEL_CHECKPOINTS}<div class="selectlinktextjs" id="bancheckpointselectall">[<a>Select all</a>]</div></td>
							<td>
								<ul class="littlelist">
									{$CHECKPOINT_BOXES}
								</ul>
							</td>
						</tr>
						<tr id="boardrow">
							<td class="postblock">{$LABEL_BOARD}<div class="selectlinktextjs" id="boardselectall">[<a>Select all</a>]</div></td>
							<td>
								<ul class="boardFilterList">
									{$BOARD_BOXES}
								</ul>
							</td>
						</tr>
					</tbody>
				</table>
				<div class="buttonSection">
					<button type="submit">{$SUBMIT_TEXT}</button>
					<input type="reset" value="{$RESET_TEXT}">
				</div>
			</div>
		</details>
	</form>
