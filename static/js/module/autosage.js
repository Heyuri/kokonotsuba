document.addEventListener('DOMContentLoaded', () => {
	// === Register Autosage Handler ===
	window.postWidget.registerActionHandler('autosage', async function (ctx) {
		await handleThreadToggle(ctx, {
			actionName: 'autosage',
			indicatorClass: 'indicator-autosage',
			messageOn: 'Thread autosage\'d!',
			messageOff: 'Thread un-autosage\'d!',
			labelOn: 'Un-autosage thread',
			labelOff: 'Autosage thread'
		});
	});
});