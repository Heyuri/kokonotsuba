	<div class="hostNoteOnPost" title="{$NOTE_TITLE_TEXT}" data-host-note-Id="{$NOTE_ID}">
		<span class="hostNoteText">{$NOTE_TEXT}</span>
		<i class="hostNoteAddedBy" style="color: {$MOD_COLOR};"> - {$ACCOUNT_NAME}</i> <i class="hostNoteTimestamp">({$NOTE_TIMESTAMP})</i>
		<span class="noteFunctions">
			<!--&IF($CAN_MODIFY_NOTE,'<span class="adminFunctions hostNoteDeleteFunction">[<button type="submit" class="buttonLink hostNoteDeletionAnchor" formaction="{$NOTE_DELETION_URL}" formmethod="POST" title="{$DELETE_NOTE_TITLE}">X</button>]</span>','')-->
			<!--&IF($CAN_MODIFY_NOTE,'<span class="adminFunctions hostNoteEditFunction">[<a class="hostNoteEditAnchor" href="{$NOTE_EDIT_URL}" title="{$EDIT_NOTE_TITLE}">E</a>]</span>','')-->
		</span>
	</div>
