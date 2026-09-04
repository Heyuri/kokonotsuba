	<form class="detailsboxForm formtable hostNotesPanelForm" method="POST" action="{$MODULE_URL}">
		{$CSRF_TOKEN}
		<details class="detailsbox hostNotesPanel" open>
			<summary>{$PANEL_TITLE}</summary>
			<div class="detailsboxContent">
				<div class="hostNotesPanelList">
					<!--&IF($NOTE_LIST,'{$NOTE_LIST}','<div class="hostNotesPanelEmpty">{$NO_NOTES_TEXT}</div>')-->
				</div>
				<!--&IF($CAN_LEAVE_NOTE,'{$ADD_FORM}','')-->
			</div>
		</details>
	</form>
