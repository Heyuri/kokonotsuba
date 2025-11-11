document.addEventListener('DOMContentLoaded', () => {
	// === Register Sticky Handler ===
	window.postWidget.registerActionHandler('sticky', async function (ctx) {
		await handleThreadToggle(ctx, {
			templateId: 'stickyIconTemplate',
			actionName: 'sticky',
			iconClass: 'stickyIcon',
			messageOn: 'Thread stickied!',
			messageOff: 'Thread un-stickied!',
			labelOn: 'Unsticky thread',
			labelOff: 'Sticky thread'
		});
	});
});