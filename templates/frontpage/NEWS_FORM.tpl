		<form class="frontEditor" action="{$ACTION}" method="post">
			{$CSRF_TOKEN}
			<input type="hidden" name="id" value="{$ID}">
			<h3>{$HEADING}</h3>
			<table class="formtable">
				<tbody>
					<tr><td class="postblock"><label for="frontSubject">{$L_SUBJECT}</label></td><td><input type="text" id="frontSubject" class="inputtext" name="subject" value="{$SUBJECT}" maxlength="255" required></td></tr>
					<tr><td class="postblock"><label for="frontByline">{$L_BYLINE}</label></td><td><input type="text" id="frontByline" class="inputtext" name="byline" value="{$BYLINE}" maxlength="255" placeholder="{$BYLINE_PLACEHOLDER}"></td></tr>
					<tr><td class="postblock"><label for="frontEmail">{$L_EMAIL}</label></td><td><input type="email" id="frontEmail" class="inputtext" name="email" value="{$EMAIL}" maxlength="255"></td></tr>
					<tr><td class="postblock"><label for="frontBody">{$L_BODY}</label></td><td><textarea id="frontBody" class="inputtext frontBodyInput" name="body" rows="16" cols="80" data-preview="frontPreview">{$BODY}</textarea></td></tr>
					<tr><td class="postblock">{$L_PUBLISHED}</td><td><label><input type="checkbox" name="published" value="1"{$PUBLISHED_CHECKED}> {$L_PUBLISHED_HINT}</label></td></tr>
				</tbody>
			</table>
			<div class="buttonSection">
				<button type="submit" name="action" value="save">{$L_SAVE}</button>
				<!--&IF($CAN_DELETE,'<button type="submit" name="action" value="delete" formnovalidate data-confirm="{$L_DELETE_CONFIRM}">{$L_DELETE}</button>','')-->
			</div>
		</form>
		<h3>{$L_PREVIEW}</h3>
		<iframe id="frontPreview" class="frontPreview" sandbox="" title="{$L_PREVIEW}" data-wrap="newsbody"></iframe>
		<script src="{$STATIC_URL}js/frontEditor.js" defer></script>
