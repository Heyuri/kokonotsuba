	<div class="pmViewContainer">
		<div class="pmViewNav">
			<a href="{$MODULE_PAGE_URL}">&laquo; {$BACK_LABEL}</a>
		</div>
		<div class="pmViewHeader">
			<!--&IF($PM_SUBJECT,'<h3 class="pmViewSubject title">{$PM_SUBJECT}</h3>','')-->
			<table class="pmViewMeta">
				<tr>
					<td class="postblock">{$FROM_LABEL}</td>
					<td><span class="nameContainer name">{$PM_SENDER_NAME}</span> <small>({$PM_SENDER_TRIP})</small></td>
				</tr>
				<tr>
					<td class="postblock">{$TO_LABEL}</td>
					<td>{$PM_RECIPIENT_TRIP}</td>
				</tr>
				<!--&IF($PM_IP,'<tr><td class="postblock">{$IP_LABEL}</td><td>{$PM_IP}</td></tr>','')-->
				<tr>
					<td class="postblock">{$DATE_LABEL}</td>
					<td>{$PM_DATE}</td>
				</tr>
			</table>
		</div>
		<hr>
		<div class="pmViewBody">
			{$PM_BODY}
		</div>
		<!--&IF($SHOW_REPLY,'<hr><details class="pmReplyForm detailsbox" open><summary>{$REPLY_LABEL}</summary><!--&PM_COMPOSE_FORM/--></details>','')-->
	</div>