document.addEventListener('DOMContentLoaded', () => {
	// === Register Lock Handler ===
	window.postWidget.registerActionHandler('lock', async function (ctx) {
		await handleThreadToggle(ctx, {
			templateId: 'lockIconTemplate',
			actionName: 'lock',
            iconClass: 'lockIcon',
			messageOn: 'Thread locked!',
			messageOff: 'Thread unlocked!',
			labelOn: 'Unlock thread',
			labelOff: 'Lock thread'
		});
	});
});
