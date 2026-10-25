			<tr>
				<td>{$ROLE}</td>
				<td>{$GRANTED_FROM}</td>
				<td>{$GRANTED_AT}</td>
				<td><code>{$TOKEN_LABEL}</code></td>
				<td>
					<form method="POST" action="{$MODULE_URL}" class="threadModRevokeForm">
						{$CSRF_TOKEN}
						<input type="hidden" name="action" value="revoke">
						<input type="hidden" name="threadUid" value="{$THREAD_UID}">
						<input type="hidden" name="tokenHash" value="{$TOKEN_HASH}">
						<input type="submit" value="{$REVOKE_LABEL}">
					</form>
				</td>
			</tr>
