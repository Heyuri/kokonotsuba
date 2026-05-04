(function () {
	if (!window.attachmentWidget) return;

	// Ban file only
	window.attachmentWidget.registerActionHandler('BanFile', function (ctx) {
		var menuItem = ctx && ctx.menuItem;
		if (!menuItem || !menuItem.href) return;

		var attachmentEl = ctx.container || (ctx.bar && ctx.bar.closest('.attachmentContainer'));

		sendModuleAction(menuItem.href, {
			successMessage: 'File hash banned.',
			errorMessage: 'Failed to ban file.',
			onSuccess: function () {
				if (attachmentEl) {
					var banFileIndicator = attachmentEl.querySelector('.indicator-BanFile');
					if (banFileIndicator) banFileIndicator.classList.add('indicatorHidden');
					var bdIndicator = attachmentEl.querySelector('.indicator-BanDeleteFile');
					if (bdIndicator) bdIndicator.classList.add('indicatorHidden');
				}
			}
		});
	});

	// Ban and delete file
	window.attachmentWidget.registerActionHandler('BanDeleteFile', function (ctx) {
		var menuItem = ctx && ctx.menuItem;
		if (!menuItem || !menuItem.href) return;

		var postEl = ctx.post;
		var attachmentEl = ctx.container || (ctx.bar && ctx.bar.closest('.attachmentContainer'));
		if (!attachmentEl || !postEl) return;

		var state = prepareAttachmentDeletion(attachmentEl, postEl, []);

		sendModuleAction(menuItem.href, {
			revertUI: state.revertUI,
			successMessage: 'File banned and deleted.',
			errorMessage: 'Failed to ban and delete file.',
			onSuccess: function (data) {
				if (data && data.deleted_link) {
					state.addViewFileButton(data.deleted_link);
				}
			}
		});
	});
})();
