(function () {
	document.addEventListener('click', function (e) {
		var control = e.target.closest('.adminBanFileFunction, #adminBanFileFunction');
		if (!control) return;

		var anchor = control.querySelector('a') || e.target.closest('a');
		if (!anchor) return;

		e.preventDefault();

		var postEl = anchor.closest('.post');
		if (!postEl) return;

		var attachmentEl = anchor.closest('.attachmentContainer') || anchor.closest('.file');
		if (!attachmentEl) return;

		var state = prepareAttachmentDeletion(attachmentEl, postEl, [
			'.adminBanFileFunction, #adminBanFileFunction',
			'.adminDeleteFileFunction, #adminDeleteFileFunction',
			'.imgopsLink'
		]);

		sendModuleAction(anchor.href, {
			revertUI: state.revertUI,
			successMessage: 'File banned and deleted.',
			errorMessage: 'Failed to ban file.',
			onSuccess: function (data) {
				if (data && data.deleted_link) {
					state.addViewFileButton(data.deleted_link);
				}
			}
		});
	});
})();
