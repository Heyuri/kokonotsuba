(function () {
	// Helper: extract action URL from either a form or anchor within a control element
	function getActionUrl(control) {
		var form = control.querySelector('form[action]');
		if (form) return form.action;
		var anchor = control.querySelector('a');
		if (anchor) return anchor.href;
		return null;
	}

	document.addEventListener('click', function (e) {
		// B&D button — ban + delete (same as old BF behavior)
		var bdControl = e.target.closest('.adminBanDeleteFileFunction, #adminBanDeleteFileFunction');
		if (bdControl) {
			var url = getActionUrl(bdControl);
			if (!url) return;

			e.preventDefault();

			var postEl = bdControl.closest('.post');
			if (!postEl) return;

			var attachmentEl = bdControl.closest('.attachmentContainer') || bdControl.closest('.file');
			if (!attachmentEl) return;

			var state = prepareAttachmentDeletion(attachmentEl, postEl, [
				'.indicator-banFile',
				'.indicator-banDeleteFile',
				'.indicator-deleteFile',
				'.indicator-imgops'
			]);

			sendModuleAction(url, {
				revertUI: state.revertUI,
				successMessage: 'File banned and deleted.',
				errorMessage: 'Failed to ban and delete file.',
				onSuccess: function (data) {
					if (data && data.deleted_link) {
						state.addViewFileButton(data.deleted_link);
					}
				}
			});
			return;
		}

		// BF button — ban only (no file deletion)
		var bfControl = e.target.closest('.adminBanFileFunction, #adminBanFileFunction');
		if (bfControl) {
			var url = getActionUrl(bfControl);
			if (!url) return;

			e.preventDefault();

			sendModuleAction(url, {
				successMessage: 'File hash banned.',
				errorMessage: 'Failed to ban file.',
				onSuccess: function () {
					var banFileIndicator = bfControl.closest('.indicator-banFile');
					if (banFileIndicator) banFileIndicator.classList.add('indicatorHidden');
					var attachmentEl = bfControl.closest('.attachmentContainer') || bfControl.closest('.file');
					if (attachmentEl) {
						var bdIndicator = attachmentEl.querySelector('.indicator-banDeleteFile');
						if (bdIndicator) bdIndicator.classList.add('indicatorHidden');
					}
				}
			});
		}
	});
})();
