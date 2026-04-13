document.addEventListener('DOMContentLoaded', () => {
	// === Register Sticky Handler ===
	window.postWidget.registerActionHandler('sticky', async function (ctx) {
		await handleThreadToggle(ctx, {
			actionName: 'sticky',
			indicatorClass: 'indicator-sticky',
			messageOn: 'Thread stickied!',
			messageOff: 'Thread un-stickied!',
			labelOn: 'Unsticky thread',
			labelOff: 'Sticky thread'
		});
	});
});