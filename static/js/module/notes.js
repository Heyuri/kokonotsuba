window.postWidget.registerActionHandler('leaveNote', function(ctx) {
    const postEl = ctx?.post || ctx?.arrow?.closest('.post');
    if (!postEl) return;

    PostActionUtils.openWindow({
        templateId: '#noteCreateFormTemplate',
        title: 'Leave a note',
        postEl,
        onSuccess: ({ res, form, postEl }) => {
            showMessage("Note added to post No. " + res);
        },
        onFail: ({ err, form, postEl }) => {
            showMessage("There was an error while adding a note.");
        }
    });
});