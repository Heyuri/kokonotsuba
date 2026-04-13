document.addEventListener('DOMContentLoaded', () => {
	// === Register Lock Handler ===
	window.postWidget.registerActionHandler('lock', async function (ctx) {
		await handleThreadToggle(ctx, {
			actionName: 'lock',
			indicatorClass: 'indicator-lock',
			messageOn: 'Thread locked!',
			messageOff: 'Thread unlocked!',
			labelOn: 'Unlock thread',
			labelOff: 'Lock thread'
		});
	});
});
