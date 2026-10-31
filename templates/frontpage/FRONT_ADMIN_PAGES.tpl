			<h3 id="frontAdminPages">{$HEADING} <small>[<a href="{$NEW_URL}">{$NEW_LABEL}</a>]</small></h3>
			<div class="tableViewportWrapper">
			<table class="postlists frontAdminList">
				<thead><tr><th>{$L_TITLE}</th><th>{$L_SLUG}</th><th>{$L_NAV}</th><th>{$L_STATUS}</th><th></th></tr></thead>
				<tbody>
					<!--&FOREACH($ROWS,'FRONT_ADMIN_PAGE_ROW')-->
					<!--&IF($EMPTY,'<tr><td colspan="5">{$EMPTY_TEXT}</td></tr>','')-->
				</tbody>
			</table>
			</div>
