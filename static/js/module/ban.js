window.postWidget.registerActionHandler('ban', function(ctx) {
	const postEl = ctx?.post || ctx?.arrow?.closest('.post');
	if (!postEl) return;

	PostActionUtils.openWindow({
		templateId: '#banFormTemplate',
		title: '',
		postEl,
		successMessage: "User was banned for this post!",
		failMessage: "There was an error while banning user.",
		isWarn: false
	});
});
