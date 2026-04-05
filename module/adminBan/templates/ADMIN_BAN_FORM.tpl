<div class="banFormContainer">
	<form method="POST" action="{$MODULE_URL}">
		<h3 class="centerText">Add a ban</h3>
		<input type="hidden" name="adminban-action" value="add-ban">

		<table id="banForm">
			<tbody>
				<tr>
					<td class="postblock"><label for="post_number">Post number</label></td>
					<td><span id="post_number"><!--&IF($POST_NUMBER,'{$POST_NUMBER}','')--></span></td>
					<td><input type="hidden" name="postUid" value="<!--&IF($POST_UID,'{$POST_UID}','')-->"></td>
				</tr>
				<tr>
					<td class="postblock"><label for="ipAddress">IP address</label></td>
					<td>
						<div class="formItemDescription">The IP to be banned. You can use '*' for range bans. E.g, '127.0.*' will ban any IP that begins with '127.0.'</div>
						<input type="text" class="inputtext" id="ipAddress" name="ipAddress" placeholder="Enter IP address" value="<!--&IF($IP,'{$IP}','')-->" required>
					</td>
				</tr>
				<tr>
					<td class="postblock"><label for="duration">Ban duration</label></td>
					<td>
						<input type="text" class="inputtext" id="duration" name="duration" value="1d" placeholder="e.g., 1d, 2h" required>
						<div class="formItemDescription">Legend: 
							<ul>
								<li>'1y' = 1 year</li>
								<li>'1m' = 1 month</li>
								<li>'1w' = 1 week</li>
								<li>'1d' = 1 day</li>
								<li>'1h' = 1 hour</li>
							</ul>
							<p>Decimal values can also be used - e.g: 1.5y = 18 months.</p>
							<p>Units can also be combined - '1y2m' will last for 1 year and 2 months</p>
					</td>
				</tr>
				<tr>
					<td class="postblock"><label for="banprivmsg">Reason for ban</label></td>
					<td>
						<textarea class="inputtext" id="banprivmsg" name="privmsg" rows="4" cols="50" placeholder="Enter reason for the ban"></textarea>
					</td>
				</tr>
				<tr>
					<td class="postblock"><label for="banmsg">Public ban message</label></td>
					<td>
						<textarea class="inputtext" id="banmsg" name="banmsg" rows="4" cols="50"><!--&IF($DEFAULT_BAN_MESSAGE,'{$DEFAULT_BAN_MESSAGE}','')--></textarea>
					</td>
				</tr>
				<tr>
					<td class="postblock"><label for="public">Public ban</label></td>
					<td><input type="checkbox" id="public" name="public"></td>
				</tr>
				<tr>
					<td class="postblock"><label for="global">Global ban</label></td>
					<td><input type="checkbox" id="global" name="global"></td>
				</tr>
			</tbody>
		</table>

		<div class="buttonSection centerText">
			<input id="bigredbutton" type="submit" value="BAN!">
		</div>
	</form>

</div>