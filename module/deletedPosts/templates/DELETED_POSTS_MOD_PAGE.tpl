	<h3>{$MODULE_HEADER_TEXT}</h3>
	<div class="deletedPostIndexLinks">[<a href="{$URL}">Deleted</a>] [<a href="{$URL}&pageName=restoredIndex">Restored</a>]</div>
	<!--&IF($CAN_VIEW_ALL_DELETED_POSTS,'<!--&DP_TOGGLE_BUTTON/-->','')--> 
	{$FILTER_FORM}
	<div class="deletedPostsListContainer">
		<div class="deletedPostsList">
			<!--&FOREACH($DELETED_POSTS,'DELETED_POST_ENTRY')-->
			<!--&IF($ARE_NO_POSTS,'<div class="centerText">No posts currently in queue.</div>','')-->
		</div>
	</div>