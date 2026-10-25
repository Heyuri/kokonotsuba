	<form id="sideNavEditor" class="sideNavEditor" action="{$MODULE_URL}" method="post">
		{$CSRF_TOKEN}
		<button type="submit" name="action" value="save" class="sideNavDefaultAction" tabindex="-1" aria-hidden="true"></button>
		<script type="application/json" class="sideNavEditorData">{$DATA}</script>

		<div class="sideNavEditorMain">
			<h3>Side navigation</h3>
			<p class="sideNavEditorHint"><noscript>Each button saves straight away.</noscript><span class="js-only">Drag groups and links by their handle. Changes are kept until you save.</span></p>
			<div class="sideNavEditorBoxes">{$GROUPS}</div>

			<div class="sideNavEditorAddGroup">
				<input type="text" class="inputtext sideNavEditorNewTitle" name="new_group_title" placeholder="New group title" maxlength="255">
				<button type="submit" class="sideNavEditorAddGroupBtn" name="action" value="add_group" title="Add group" formnovalidate>+</button>
			</div>

			<div class="buttonSection">
				<button type="submit" class="sideNavEditorSave" name="action" value="save">Save</button>
			</div>
		</div>

		<div class="sideNavEditorPreview">
			<h3>Preview</h3>
			<div class="sideNavEditorPreviewBody">{$PREVIEW}</div>
		</div>
	</form>
