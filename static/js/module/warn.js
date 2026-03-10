window.postWidget.registerActionHandler('warn', function(ctx) {
    const postEl = ctx?.post || ctx?.arrow?.closest('.post');
    if (!postEl) return;

    PostActionUtils.openWindow({
        templateId: '#warnFormTemplate',
        title: 'Warn user',
        postEl,
        onSuccess: ({ res, form, postEl }) => {
            showMessage("User was warned for this post No. " + res);
        },
        onFail: ({ err, form, postEl }) => {
            showMessage("There was an error while warning user.");
        }
    });
});