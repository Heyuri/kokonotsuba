	<div class="noteOnPost"  title="{$NOTE_TITLE_TEXT}" data-note-Id="{$NOTE_ID}">
		<div class="noteSeparator">---</div>
		<span class="noteText">{$NOTE_TEXT}</span>
		<i class="noteAddedBy" style="color: {$MOD_COLOR};"> - {$ACCOUNT_NAME}</i> <i class="noteTimestamp">({$NOTE_TIMESTAMP})</i> 
		<span class="noteFunctions">
			<!--&IF($CAN_MODIFY_NOTE,'<span class="adminFunctions noteDeleteFunction">[<button type="submit" class="buttonLink noteDeletionAnchor" formaction="{$NOTE_DELETION_URL}" formmethod="POST" title="{$DELETE_NOTE_TITLE}">X</button>]</span>','')-->
			<!--&IF($CAN_MODIFY_NOTE,'<span class="adminFunctions noteEditFunction">[<a class="noteEditAnchor" href="{$NOTE_EDIT_URL}" title="{$EDIT_NOTE_TITLE}">E</a>]</span>','')-->
		</span>
	</div>