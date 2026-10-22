(function () {
	if (!window.attachmentWidget) return;

	// Ban file by perceptual hash only
	window.attachmentWidget.registerActionHandler('PBanFile', function (ctx) {
		var menuItem = ctx && ctx.menuItem;
		if (!menuItem || !menuItem.href) return;

		sendModuleAction(menuItem.href, {
			successMessage: 'Perceptual hash banned.',
			errorMessage: 'Failed to ban perceptual hash.'
		});
	});

	// Ban by perceptual hash and delete file
	window.attachmentWidget.registerActionHandler('PBanDeleteFile', function (ctx) {
		var menuItem = ctx && ctx.menuItem;
		if (!menuItem || !menuItem.href) return;

		var postEl = ctx.post;
		var attachmentEl = ctx.container || (ctx.bar && ctx.bar.closest('.attachmentContainer'));
		if (!attachmentEl || !postEl) return;

		var state = prepareAttachmentDeletion(attachmentEl, postEl, []);

		sendModuleAction(menuItem.href, {
			revertUI: state.revertUI,
			successMessage: 'Perceptual hash banned and file deleted.',
			errorMessage: 'Failed to ban and delete file.',
			onSuccess: function (data) {
				if (data && data.deleted_link) {
					state.addViewFileButton(data);
				}
			}
		});
	});
})();
