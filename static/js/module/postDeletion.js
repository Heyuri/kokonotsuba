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

	// Shared: set up UI changes for post/file deletion and provide revert function
	function createDeletionUIState(type, postEl, source) {
		const addedClasses = [];
		const appendedNodes = [];
		const hiddenControls = [];

		function addClassAndTrack(el, cls) {
			if (!el) return;
			el.classList.add(cls);
			addedClasses.push({ el: el, cls: cls });
		}

		// POST DELETION UI
		if (type === 'post') {
            // OP: remove entire thread
			if (postEl.classList.contains('op')) {
				const thread = postEl.closest('.thread');
				if (thread) {
					addClassAndTrack(thread, 'deletedPost');

					const postsInThread = thread.querySelectorAll('.post');
					for (let i = 0; i < postsInThread.length; i++) {
						const p = postsInThread[i];
						const res = appendWarning(p, 'post');
						if (res && res.spacer1) appendedNodes.push(res.spacer1);
						if (res && res.warn) appendedNodes.push(res.warn);
					}
				}
			} else {
				addClassAndTrack(postEl, 'deletedPost');
				const res = appendWarning(postEl, 'post');
				if (res && res.spacer1) appendedNodes.push(res.spacer1);
				if (res && res.warn) appendedNodes.push(res.warn);
			}

			const hidden = hideDeleteControls(postEl, 'post');
			for (let i = 0; i < hidden.length; i++) hiddenControls.push(hidden[i]);
		}

		// FILE DELETION UI (per-attachment) — LEGACY ONLY
		if (type === 'file') {
			let attachment = null;
			if (source && source.control) {
				attachment = source.control.closest('.attachmentContainer');
			}

			if (attachment) {
				addClassAndTrack(attachment, 'deletedFile');

				// Insert [FILE DELETED] after .fileProperties
				const props = attachment.querySelector('.fileProperties');
				const res = appendWarning(postEl, 'file');

				if (props && res) {
					if (res.spacer1) {
						props.insertAdjacentElement('afterend', res.spacer1);
						appendedNodes.push(res.spacer1);
					}
					if (res.warn) {
						props.insertAdjacentElement('afterend', res.warn);
						appendedNodes.push(res.warn);
					}
				}

				// Hide only THIS DF button
				let btn = null;
				if (source && source.control) {
					btn = source.control.closest('.adminDeleteFileFunction, #adminDeleteFileFunction');
				}
				if (btn) {
					btn.classList.add('hidden');
					hiddenControls.push(btn);
				}
			}
		}

		function revertUI() {
			for (let i = addedClasses.length - 1; i >= 0; i--) {
				const entry = addedClasses[i];
				if (entry && entry.el) entry.el.classList.remove(entry.cls);
			}
			for (let i = appendedNodes.length - 1; i >= 0; i--) {
				const node = appendedNodes[i];
				if (node && node.parentNode) node.parentNode.removeChild(node);
			}
			for (let i = hiddenControls.length - 1; i >= 0; i--) {
				const el = hiddenControls[i];
				if (el) el.classList.remove('hidden');
			}
		}

		return {
			addedClasses: addedClasses,
			appendedNodes: appendedNodes,
			hiddenControls: hiddenControls,
			revertUI: revertUI
		};
	}

	// Shared: fetch + widget removal + messages
	function performDeletionRequest(url, options) {
		const type = options.type;
		const postEl = options.postEl;
		const revertUI = options.revertUI;
		const successMessage = options.successMessage;
		const failMessage = options.failMessage;
		const hasMessages = typeof showMessage === 'function' && (successMessage || failMessage);

		fetch(url, {
			method: 'GET',
			credentials: 'same-origin',
			headers: { 'X-Requested-With': 'XMLHttpRequest' },
			cache: 'no-store'
		}).then(function (res) {
			if (!res.ok) {
				if (hasMessages && failMessage) showMessage(failMessage, false);
				if (revertUI) revertUI();
			} else {
				res.json().then(function (data) {
					if (data && data.success && data.deleted_link) {
						
						// Post-level only
						postEl.dataset.deletedLink = data.deleted_link;

						// Attachment deletion → add [VF] button
						if (type === 'file' && options.attachment) {
							const btn = options.attachment.querySelector('.adminDeleteFileFunction, #adminDeleteFileFunction');
							if (btn) {
								const vf = createViewFileButton(data.deleted_link);
								btn.insertAdjacentElement('afterend', vf);
								options.vfButton = vf;
							}
						}
					}

					if (hasMessages && successMessage) showMessage(successMessage, true);

					// Widget cleanup: posts only (attachments never use widgets)
					if (type !== 'file') {
						removeWidgetActions(postEl, ['delete', 'mute']);
					}

				}).catch(function () {
					if (hasMessages && successMessage) showMessage(successMessage, true);

					if (type !== 'file') {
						removeWidgetActions(postEl, ['delete', 'mute']);
					}
				});
			}
		}).catch(function () {
			if (hasMessages && failMessage) showMessage(failMessage, false);
			if (revertUI) revertUI();
		});
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
		const type = isFileControl ? 'file' : 'post';

		const uiState = createDeletionUIState(type, postEl, { control: control });

		const href = (control.tagName === 'A' && control.href) ? control.href :
			(control.getAttribute && control.getAttribute('href')) ||
			(function () {
				const a = control.querySelector && control.querySelector('a[href]');
				return a ? a.href : null;
			})();

		if (href) {
			e.preventDefault();
			try {
				let successMessage = null;
				let failMessage = null;

				if (type === 'file') {
					successMessage = "Attachment deleted!";
					failMessage = "Failed to delete attachment.";
				} else {
					successMessage = "Post deleted!";
					failMessage = "Failed to delete post.";
				}

				let attachment = null;
				if (type === 'file') {
					attachment = control.closest('.attachmentContainer');
				}

				performDeletionRequest(href, {
					type: type,
					postEl: postEl,
					revertUI: uiState.revertUI,
					successMessage: successMessage,
					failMessage: failMessage,
					attachment: attachment
				});
			} catch (_) {
				if (uiState && uiState.revertUI) uiState.revertUI();
			}
		}
	});

	// ====== WIDGET INTEGRATION (delete/mute + dynamic "View deleted post") ======
	function handleWidgetDeletion(action, ctx) {
		const postEl = ctx && (ctx.post || (ctx.arrow && ctx.arrow.closest('.post')));
		if (!postEl || !ctx || !ctx.url) return;

		// Attachments are never deleted via widgets
		if (action === 'deleteAttachment') return;

		const type = (action === 'delete' || action === 'mute') ? 'post' : 'post';

		let successMessage = '';
		let failMessage = '';

		if (action === 'delete') {
			successMessage = "Post deleted!";
			failMessage = "Failed to delete post.";
		} else if (action === 'mute') {
			successMessage = "Post deleted and user muted!";
			failMessage = "Failed to delete and mute.";
		}

		const uiState = createDeletionUIState('post', postEl, { arrow: ctx.arrow });

		try {
			performDeletionRequest(ctx.url, {
				type: 'post',
				postEl: postEl,
				revertUI: uiState.revertUI,
				successMessage: successMessage,
				failMessage: failMessage
			});
		} catch (_) {
			if (uiState && uiState.revertUI) uiState.revertUI();
		}
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
})();
