(function () {
	document.addEventListener('click', function (e) {
		// B&D button — ban + delete (same as old BF behavior)
		var bdControl = e.target.closest('.adminBanDeleteFileFunction, #adminBanDeleteFileFunction');
		if (bdControl) {
			var anchor = bdControl.querySelector('a') || e.target.closest('a');
			if (!anchor) return;

			e.preventDefault();

			var postEl = anchor.closest('.post');
			if (!postEl) return;

			var attachmentEl = anchor.closest('.attachmentContainer') || anchor.closest('.file');
			if (!attachmentEl) return;

			var state = prepareAttachmentDeletion(attachmentEl, postEl, [
				'.indicator-banFile',
				'.indicator-banDeleteFile',
				'.indicator-deleteFile',
				'.indicator-imgops'
			]);

			sendModuleAction(anchor.href, {
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
			var anchor = bfControl.querySelector('a') || e.target.closest('a');
			if (!anchor) return;

			e.preventDefault();

			sendModuleAction(anchor.href, {
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
