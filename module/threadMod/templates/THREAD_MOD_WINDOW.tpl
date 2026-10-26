		<form method="POST" action="{$MODULE_URL}" class="threadModForm threadModWindowForm">
			<input type="hidden" name="csrf_token" value="">
			<input type="hidden" name="threadUid" value="">
			<table class="formtable threadModTable">
				<tbody>
					{$FORM_ROWS}
				</tbody>
			</table>
			<div class="threadModRosterSection" hidden>
				<h4>{$ROSTER_TITLE}</h4>
				<p class="threadModRosterEmpty">{$ROSTER_EMPTY}</p>
				<table class="postlists threadModRoster" data-revoke-label="{$REVOKE_LABEL}">
					<tr><th>{$TH_ROLE}</th><th>{$TH_FROM}</th><th>{$TH_SINCE}</th><th>{$TH_BROWSER}</th><th></th></tr>
				</table>
			</div>
		</form>
