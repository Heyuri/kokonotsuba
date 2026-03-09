window.postWidget.registerActionHandler('leaveNote', function(ctx) {
	const postEl = ctx?.post || ctx?.arrow?.closest('.post');
	if (!postEl) return;

	PostActionUtils.openWindow({
		templateId: '#noteCreateFormTemplate',
		title: 'Leave a note',
		postEl,
		successMessage: "Note added to post!",
		failMessage: "There was an error while adding a note.",
		isWarn: true
	});
});
