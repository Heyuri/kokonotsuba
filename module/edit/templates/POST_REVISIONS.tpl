	<div class="postRevisionsContainer">
		<h3>{$PAGE_TITLE}</h3>
		<form method="POST" action="{$MODULE_URL}">
			{$CSRF_TOKEN}
			<input name="postUid" value="{$POST_UID}" type="hidden">
			<input name="action" value="restoreRevision" type="hidden">
			<div class="postRevisionList">
				<!--&IF($NEVER_EDITED,'<div class="postRevisionsEmpty">{$NO_REVISIONS_TEXT}</div>','')-->
				{$REVISION_LIST}
			</div>
		</form>
	</div>
