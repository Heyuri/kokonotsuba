window.postWidget.registerActionHandler('warn', function(ctx) {
	const postEl = ctx?.post || ctx?.arrow?.closest('.post');
	if (!postEl) return;

	PostActionUtils.openWindow({
		templateId: '#warnFormTemplate',
		title: '',
		postEl,
		successMessage: "User was warned for this post!",
		failMessage: "There was an error while warning user.",
		isWarn: true
	});
});
