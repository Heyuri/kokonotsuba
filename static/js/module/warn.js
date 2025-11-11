window.postWidget.registerActionHandler('warn', function(ctx) {
	const postEl = ctx?.post || ctx?.arrow?.closest('.post');
	if (!postEl) return;

	PostActionUtils.openWindow({
		templateId: '#warnFormTemplate',
		title: 'Warn user',
		postEl,
        successMessage: "User warned!",
        failMessage: "There was an error while warning user.",
	});
});
