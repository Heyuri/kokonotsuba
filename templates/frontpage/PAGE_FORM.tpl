		<form class="frontEditor" action="{$ACTION}" method="post">
			{$CSRF_TOKEN}
			<input type="hidden" name="id" value="{$ID}">
			<h3>{$HEADING}</h3>
			<table class="formtable">
				<tbody>
					<tr><td class="postblock"><label for="frontTitle">{$L_TITLE}</label></td><td><input type="text" id="frontTitle" class="inputtext" name="title" value="{$TITLE}" maxlength="255" required></td></tr>
					<tr><td class="postblock"><label for="frontSlug">{$L_SLUG}</label></td><td><input type="text" id="frontSlug" class="inputtext" name="slug" value="{$SLUG}" maxlength="64" pattern="[a-z0-9_-]+" required> <small>{$L_SLUG_HINT}</small></td></tr>
					<tr><td class="postblock"><label for="frontBody">{$L_BODY}</label></td><td><textarea id="frontBody" class="inputtext frontBodyInput" name="body" rows="20" cols="80" data-preview="frontPreview">{$BODY}</textarea></td></tr>
					<tr><td class="postblock">{$L_PUBLISHED}</td><td><label><input type="checkbox" name="published" value="1"{$PUBLISHED_CHECKED}> {$L_PUBLISHED_HINT}</label></td></tr>
					<tr><td class="postblock">{$L_NAV}</td><td><label><input type="checkbox" name="show_in_nav" value="1"{$NAV_CHECKED}> {$L_NAV_HINT}</label> <label>{$L_NAV_ORDER} <input type="number" class="inputtext" name="nav_order" value="{$NAV_ORDER}" min="0" max="9999"></label></td></tr>
				</tbody>
			</table>
			<div class="buttonSection">
				<button type="submit" name="action" value="save">{$L_SAVE}</button>
				<!--&IF($CAN_DELETE,'<button type="submit" name="action" value="delete" formnovalidate data-confirm="{$L_DELETE_CONFIRM}">{$L_DELETE}</button>','')-->
			</div>
		</form>
		<h3>{$L_PREVIEW}</h3>
		<iframe id="frontPreview" class="frontPreview" sandbox="" title="{$L_PREVIEW}" data-wrap="frontPageBody"></iframe>
		<script src="{$STATIC_URL}js/frontEditor.js" defer></script>
