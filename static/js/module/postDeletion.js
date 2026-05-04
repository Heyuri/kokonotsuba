(function () {
	// Utility: find the containing .post element
	function getPostEl(el) {
		return el.closest('.post');
	}

	// Utility: hide admin delete controls as required
	function hideDeleteControls(postEl, type) {
		if (!postEl) return [];
		const deleteSpans = postEl.querySelectorAll('.adminDeleteFunction, #adminDeleteFunction, .adminDeleteMuteFunction, #adminDeleteMuteFunction');
		const fileDeleteSpans = postEl.querySelectorAll('.adminDeleteFileFunction, #adminDeleteFileFunction');

		const hidden = [];

		if (type === 'post') {
			for (let i = 0; i < deleteSpans.length; i++) {
				deleteSpans[i].classList.add('hidden');
				hidden.push(deleteSpans[i]);
			}
			for (let i = 0; i < fileDeleteSpans.length; i++) {
				fileDeleteSpans[i].classList.add('hidden');
				hidden.push(fileDeleteSpans[i]);
			}
		} else if (type === 'file') {
			for (let i = 0; i < fileDeleteSpans.length; i++) {
				fileDeleteSpans[i].classList.add('hidden');
				hidden.push(fileDeleteSpans[i]);
			}
		}
		return hidden;
	}

	// Set up UI changes for post deletion and provide revert function
	function createDeletionUIState(postEl, source) {
		const addedClasses = [];
		const shownIndicators = [];
		const hiddenControls = [];

		function addClassAndTrack(el, cls) {
			if (!el) return;
			el.classList.add(cls);
			addedClasses.push({ el: el, cls: cls });
		}

		if (postEl.classList.contains('op')) {
			const thread = postEl.closest('.thread');
			if (thread) {
				addClassAndTrack(thread, 'deletedPost');

				const postsInThread = thread.querySelectorAll('.post');
				for (let i = 0; i < postsInThread.length; i++) {
					const res = showDeletionIndicator(postsInThread[i], 'post');
					if (res && res.indicator) shownIndicators.push(res.indicator);
				}
			}
		} else {
			addClassAndTrack(postEl, 'deletedPost');
			const res = showDeletionIndicator(postEl, 'post');
			if (res && res.indicator) shownIndicators.push(res.indicator);
		}

		const hidden = hideDeleteControls(postEl, 'post');
		for (let i = 0; i < hidden.length; i++) hiddenControls.push(hidden[i]);

		function revertUI() {
			for (let i = addedClasses.length - 1; i >= 0; i--) {
				const entry = addedClasses[i];
				if (entry && entry.el) entry.el.classList.remove(entry.cls);
			}
			for (let i = shownIndicators.length - 1; i >= 0; i--) {
				shownIndicators[i].classList.add('indicatorHidden');
			}
			for (let i = hiddenControls.length - 1; i >= 0; i--) {
				const el = hiddenControls[i];
				if (el) el.classList.remove('hidden');
			}
		}

		return { revertUI: revertUI };
	}

	// Shared: fetch + widget removal + messages
	function performPostDeletionRequest(url, postEl, revertUI, successMessage, extraParams) {
		sendModuleAction(url, {
			revertUI: revertUI,
			successMessage: successMessage,
			errorMessage: 'Failed to delete.',
			onSuccess: function (data) {
				if (data && data.success && data.deleted_link) {
					postEl.dataset.deletedLink = data.deleted_link;
				}
				removeWidgetActions(postEl, ['delete', 'mute']);
			}
		}, extraParams);
	}

	// Main delegated click handler (legacy admin controls)
	document.addEventListener('click', function (e) {
		const control = e.target.closest(
			'.adminDeleteFunction, #adminDeleteFunction, ' +
			'.adminDeleteMuteFunction, #adminDeleteMuteFunction, ' +
			'.adminDeleteFileFunction, #adminDeleteFileFunction'
		);
		if (!control) return;

		const postEl = getPostEl(control);
		if (!postEl) return;

		const isFileControl = control.matches('.adminDeleteFileFunction, #adminDeleteFileFunction');

		const href = (function () {
			// Try form action first (POST forms)
			const form = control.querySelector && control.querySelector('form[action]');
			if (form) return form.action;
			// Fall back to anchor href
			if (control.tagName === 'A' && control.href) return control.href;
			if (control.getAttribute && control.getAttribute('href')) return control.getAttribute('href');
			const a = control.querySelector && control.querySelector('a[href]');
			return a ? a.href : null;
		})();

		if (!href) return;
		e.preventDefault();

		if (isFileControl) {
			const attachmentEl = control.closest('.attachmentContainer') || control.closest('.file');
			if (!attachmentEl) return;

			const state = prepareAttachmentDeletion(attachmentEl, postEl, []);

			sendModuleAction(href, {
				revertUI: state.revertUI,
				successMessage: 'Attachment deleted!',
				errorMessage: 'Failed to delete the attachment.',
				onSuccess: function (data) {
					if (data && data.deleted_link) {
						state.addViewFileButton(data.deleted_link);
					}
				}
			});
		} else {
			const uiState = createDeletionUIState(postEl, { control: control });

			performPostDeletionRequest(href, postEl, uiState.revertUI, 'Post deleted!');
		}
	});

	// ====== WIDGET INTEGRATION (delete/mute + dynamic "View deleted post") ======
	function handleWidgetDeletion(action, ctx) {
		const postEl = ctx && (ctx.post || (ctx.arrow && ctx.arrow.closest('.post')));
		if (!postEl || !ctx || !ctx.url) return;

		// Attachments are never deleted via widgets
		if (action === 'deleteAttachment') return;

		let successMessage = '';

		if (action === 'delete') {
			successMessage = "Post deleted!";
		} else if (action === 'mute') {
			successMessage = "Post deleted and user muted!";
		}

		const uiState = createDeletionUIState(postEl, { arrow: ctx.arrow });

		// Forward params from widget data attributes
		performPostDeletionRequest(ctx.url, postEl, uiState.revertUI, successMessage, ctx.params || {});
	}

	// Register widget handlers + optional augmenter (unchanged for post deletion)
	if (window.postWidget) {
		if (typeof window.postWidget.registerActionHandler === 'function') {
			window.postWidget.registerActionHandler('delete', function (ctx) {
				handleWidgetDeletion('delete', ctx);
			});
			
			window.postWidget.registerActionHandler('mute', function (ctx) {
				handleWidgetDeletion('mute', ctx);
			});

			// Removed: deleteAttachment (attachments never use widgets)

			window.postWidget.registerActionHandler('viewdeleted', function (ctx) {
				if (ctx && ctx.url && ctx.url !== '#') {
					window.location.assign(ctx.url);
				}
			});
		}
		
		if (typeof window.postWidget.registerMenuAugmenter === 'function') {
			window.postWidget.registerMenuAugmenter(function (ctx) {
				const post = ctx && (ctx.post || (ctx.arrow && ctx.arrow.closest('.post')));
				if (!post) return [];
				const existing = post.querySelector('.widgetRefs a[data-action="viewdeleted"]');
				if (existing) return [];
				const link = post.dataset.deletedLink;
				if (!link) return [];
				return [{
					href: link,
					action: 'viewdeleted',
					label: 'View deleted post',
					subMenu: ''
				}];
			});
		}
	}

	// Register attachment widget handler for file deletion
	if (window.attachmentWidget) {
		window.attachmentWidget.registerActionHandler('deleteFile', function (ctx) {
			var menuItem = ctx && ctx.menuItem;
			if (!menuItem || !menuItem.href) return;

			var postEl = ctx.post;
			var attachmentEl = ctx.container || (ctx.bar && ctx.bar.closest('.attachmentContainer'));
			if (!attachmentEl || !postEl) return;

			var state = prepareAttachmentDeletion(attachmentEl, postEl, []);

			sendModuleAction(menuItem.href, {
				revertUI: state.revertUI,
				successMessage: 'Attachment deleted!',
				errorMessage: 'Failed to delete the attachment.',
				onSuccess: function (data) {
					if (data && data.deleted_link) {
						state.addViewFileButton(data.deleted_link);
					}
				}
			});
		});
	}
})();
