			<h3 id="frontAdminNews">{$HEADING} <small>[<a href="{$NEW_URL}">{$NEW_LABEL}</a>]</small></h3>
			<div class="tableViewportWrapper">
			<table class="postlists frontAdminList">
				<thead><tr><th>{$L_SUBJECT}</th><th>{$L_AUTHOR}</th><th>{$L_DATE}</th><th>{$L_STATUS}</th><th></th></tr></thead>
				<tbody>
					<!--&FOREACH($ROWS,'FRONT_ADMIN_NEWS_ROW')-->
					<!--&IF($EMPTY,'<tr><td colspan="5">{$EMPTY_TEXT}</td></tr>','')-->
				</tbody>
			</table>
			</div>
			{$PAGER}
