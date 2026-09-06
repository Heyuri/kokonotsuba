	<h2 id="banHeading" class="centerText warning">{$BAN_HEADING}</h2>
	<div id="banScreen">

		<div id="banScreenSummary">
			<div id="banScreenText">
				<!--&IF($IS_BANNED,'','<p>{$CLEAR_TEXT}</p>')-->
				<!--&IF($BLOCKED_TEXT,'<p class="banBlockedText">{$BLOCKED_TEXT}</p>','')-->

				<!--&FOREACH($ENTRIES,'BAN_PAGE_ENTRY')-->
			</div>

			<img id="banimg" src="{$BAN_IMAGE}" alt="{$BAN_IMAGE_ALT}" {$BAN_IMAGE_DIMENSIONS}>
		</div>

		<!--&FOREACH($ENTRIES,'BAN_PAGE_ENTRY_BELOW')-->
	</div>

	<!--&IF($HAS_ENTRIES,'<hr id="hrBan">','')-->
