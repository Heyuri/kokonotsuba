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

		const addedClasses = [];
		const appendedNodes = [];
		const hiddenControls = [];

		function addClassAndTrack(el, cls) {
			if (!el) return;
			el.classList.add(cls);
			addedClasses.push({ el: el, cls: cls });
		}

		// POST DELETION (D / DM)
		if (
			control.matches('.adminDeleteFunction, #adminDeleteFunction') ||
			control.matches('.adminDeleteMuteFunction, #adminDeleteMuteFunction')
		) {
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

		// FILE DELETION
		if (control.matches('.adminDeleteFileFunction, #adminDeleteFileFunction')) {
			const imgContainer = postEl.querySelector('.imageSourceContainer');
			if (imgContainer) {
				addClassAndTrack(imgContainer, 'deletedFile');
			}
			const res = appendWarning(postEl, 'file');
			if (res && res.spacer1) appendedNodes.push(res.spacer1);
			if (res && res.warn) appendedNodes.push(res.warn);
			const hidden = hideDeleteControls(postEl, 'file');
			for (let i = 0; i < hidden.length; i++) hiddenControls.push(hidden[i]);
		}

		const href = (control.tagName === 'A' && control.href) ? control.href :
			(control.getAttribute && control.getAttribute('href')) ||
			(function () {
				const a = control.querySelector && control.querySelector('a[href]');
				return a ? a.href : null;
			})();

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

		if (href) {
			e.preventDefault();
			try {
				fetch(href, {
					method: 'GET',
					credentials: 'same-origin',
					headers: { 'X-Requested-With': 'XMLHttpRequest' },
					cache: 'no-store'
				}).then(function (res) {
					if (!res.ok) {
						// failure path
						revertUI();
					} else {
						res.json().then(function (data) {
							if (data && data.success && data.deleted_link) {
								// keep storing the link for widget use
								postEl.dataset.deletedLink = data.deleted_link;
							}
							// remove widgets from this post based on which control was used
							if (control.matches('.adminDeleteFileFunction, #adminDeleteFileFunction')) {
								removeWidgetActions(postEl, ['deleteAttachment']);
							} else {
								removeWidgetActions(postEl, ['delete', 'mute', 'deleteAttachment']);
							}
						}).catch(function () {
							// even without JSON, still remove the widgets after success
							if (control.matches('.adminDeleteFileFunction, #adminDeleteFileFunction')) {
								removeWidgetActions(postEl, ['deleteAttachment']);
							} else {
								removeWidgetActions(postEl, ['delete', 'mute', 'deleteAttachment']);
							}
						});
					}
				}).catch(function () {
					revertUI();
				});
			} catch (_) {
				revertUI();
			}
		}
	});

	// ====== WIDGET INTEGRATION (delete/mute + dynamic "View deleted post") ======
	function handleWidgetDeletion(action, ctx) {
		const postEl = ctx && (ctx.post || (ctx.arrow && ctx.arrow.closest('.post')));
		if (!postEl || !ctx || !ctx.url) return;

		const addedClasses = [];
		const appendedNodes = [];
		const hiddenControls = [];

		let successMessage = '';
		let failMessage = '';

		function addClassAndTrack(el, cls) {
			if (!el) return;
			el.classList.add(cls);
			addedClasses.push({ el: el, cls: cls });
		}

		const type = (action === 'delete' || action === 'mute') ? 'post' :
			(action === 'deleteAttachment' ? 'file' : 'post');

		if (type === 'post') {
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

			if (action === 'delete') {
				successMessage = "Post deleted!";
				failMessage = "Failed to delete post.";
			} else if (action === 'mute') {
				successMessage = "Post deleted and user muted!";
				failMessage = "Failed to delete and mute.";
			}
		}

		if (type === 'file') {
			const imgContainer = postEl.querySelector('.imageSourceContainer');
			if (imgContainer) addClassAndTrack(imgContainer, 'deletedFile');
			const res = appendWarning(postEl, 'file');
			if (res && res.spacer1) appendedNodes.push(res.spacer1);
			if (res && res.warn) appendedNodes.push(res.warn);
			const hidden = hideDeleteControls(postEl, 'file');
			for (let i = 0; i < hidden.length; i++) hiddenControls.push(hidden[i]);

			successMessage = "Attachment deleted!";
			failMessage = "Failed to delete attachment.";
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

		try {
			fetch(ctx.url, {
				method: 'GET',
				credentials: 'same-origin',
				headers: { 'X-Requested-With': 'XMLHttpRequest' },
				cache: 'no-store'
			}).then(function (res) {
				if (!res.ok) {
					showMessage(failMessage, false);
					revertUI();
				} else {
					res.json().then(function (data) {
						if (data && data.success && data.deleted_link) {
							postEl.dataset.deletedLink = data.deleted_link;
						}
						showMessage(successMessage, true);

						// remove widgets after successful action
						if (type === 'file') {
							removeWidgetActions(postEl, ['deleteAttachment']);
						} else {
							removeWidgetActions(postEl, ['delete', 'mute', 'deleteAttachment']);
						}
					}).catch(function () {
						// treat as success w/o JSON body
						showMessage(successMessage, true);

						if (type === 'file') {
							removeWidgetActions(postEl, ['deleteAttachment']);
						} else {
							removeWidgetActions(postEl, ['delete', 'mute', 'deleteAttachment']);
						}
					});
				}
			}).catch(function () {
				showMessage(failMessage, false);
				revertUI();
			});
		} catch (_) {
			revertUI();
		}
	}

	// Register widget handlers + optional augmenter (unchanged)
	if (window.postWidget) {
		if (typeof window.postWidget.registerActionHandler === 'function') {
			window.postWidget.registerActionHandler('delete', function (ctx) {
				handleWidgetDeletion('delete', ctx);
			});
			
			window.postWidget.registerActionHandler('mute', function (ctx) {
				handleWidgetDeletion('mute', ctx);
			});

			window.postWidget.registerActionHandler('deleteAttachment', function (ctx) {
				handleWidgetDeletion('deleteAttachment', ctx);
			});

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
