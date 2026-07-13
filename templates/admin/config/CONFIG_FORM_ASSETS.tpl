<style>
	.configModuleHeader h3 { margin: 0; }
	.configArrayList { list-style: none; margin: 0; padding: 0; }
	.configArrayRow, .configArrayAddRow { display: flex; gap: 4px; margin-bottom: 3px; align-items: center; }
	.configArrayRow input, .configArrayAddRow input { flex: 1 1 auto; min-width: 0; }
	.configArrayKey, .configArrayNewKey { flex: 0 0 32%; }
	.configArrayEditor button { flex: 0 0 auto; cursor: pointer; padding: 0 8px; line-height: 1.6; }
	.configArrayAddRow { margin-top: 4px; }

	/* Once anything in the form is edited, the save button follows the viewport so it stays
	   reachable without scrolling to the end of a long form. */
	#boardConfigForm.configFormDirty .buttonSection { position: sticky; bottom: 8px; z-index: 20; }
	#boardConfigForm.configFormDirty #boardConfigSaveButton { box-shadow: 0 1px 6px rgba(0, 0, 0, 0.45); }
</style>
<script src="{$STATIC_URL}js/boardConfigForm.js?v=4" defer></script>
