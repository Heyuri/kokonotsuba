(function () {
	/**
	 * Mark a post/thread as deleted based on type (post only; attachments now reloaded)
	 */
	function markAsDeleted(postEl, type) {
		if (!postEl) {
			console.error("Error: Post element not found!");
			return;
		}

		if (type === 'post') {
			if (postEl.classList.contains('op')) {
				const thread = postEl.closest('.thread');
				if (thread) {
					thread.classList.add('deletedPost');
				}
			} else {
				postEl.classList.add('deletedPost');
			}
		}
	}

	/**
	 * Reload just the attachment block for a post
	 */
	async function reloadAttachment(postEl) {
		try {
			const url = postEl.dataset.reloadUrl || postEl.dataset.url;
			if (!url) return;

			const res = await fetch(url, {
				method: 'GET',
				credentials: 'same-origin',
				headers: { 'X-Requested-With': 'XMLHttpRequest' },
				cache: 'no-store'
			});
			if (!res.ok) return;

			const html = await res.text();
			const temp = document.createElement('div');
			temp.innerHTML = html;

			const newPost = temp.querySelector('.post');
			if (!newPost) return;

			const oldFiles = postEl.querySelectorAll('.attachmentContainer, .file');
			const newFiles = newPost.querySelectorAll('.attachmentContainer, .file');

			oldFiles.forEach((oldFile, i) => {
				const updated = newFiles[i];
				if (updated) oldFile.replaceWith(updated);
			});
		} catch (err) {
			console.error("Error reloading attachment:", err);
		}
	}

	/**
	 * Handle the deletion of a file attachment (admin delete action)
	 */
	function handleFileDeletion(event, dfAnchor) {
		event.preventDefault();

		const postEl = dfAnchor.closest('.post');
		if (!postEl) {
			console.error("Error: Post element not found for DF link.");
			return;
		}

		const attachmentEl =
			dfAnchor.closest('.attachmentContainer') ||
			dfAnchor.closest('.file');

		if (!attachmentEl) {
			console.error("Error: Attachment container not found for DF link.");
			return;
		}

		const dfContainer =
			dfAnchor.closest('.adminDeleteFileFunction') ||
			dfAnchor.closest('#adminDeleteFileFunction');

		attachmentEl.classList.add('deletedFile');

		try {
			if (typeof appendWarning === 'function') {
				const props =
					attachmentEl.querySelector('.fileProperties') ||
					attachmentEl.querySelector('.filesize');

				const res = appendWarning(postEl, 'file');
				if (props && res) {
					if (res.spacer1) props.insertAdjacentElement('afterend', res.spacer1);
					if (res.warn) props.insertAdjacentElement('afterend', res.warn);
				}
			}
		} catch (err) {
			console.error("Error while appending file warning:", err);
		}

		const imgops = attachmentEl.querySelector('.imgopsLink');
		if (imgops) imgops.remove();

		if (dfContainer) dfContainer.classList.add('hidden');

		const url = dfAnchor.href;

		fetch(url, {
			method: 'GET',
			credentials: 'same-origin',
			headers: { 'X-Requested-With': 'XMLHttpRequest' },
			cache: 'no-store'
		})
		.then(async (response) => {
			if (!response.ok) {
				if (typeof showMessage === 'function') showMessage("Failed to delete the attachment.", false);
				return;
			}

			let data = null;
			try { data = await response.json(); } catch (_) {}

			if (data && data.success && data.deleted_link && typeof createViewFileButton === 'function') {
				const btn =
					attachmentEl.querySelector('.adminDeleteFileFunction, #adminDeleteFileFunction') ||
					dfContainer;

				if (btn) {
					const vf = createViewFileButton(data.deleted_link);
					btn.insertAdjacentElement('afterend', vf);
				}
			}

			if (typeof showMessage === 'function') {
				showMessage("Attachment deleted successfully.", true);
			}
		})
		.catch(() => {
			if (typeof showMessage === 'function') showMessage("Network error during deletion.", false);
		});
	}

	/**
	 * Unified deletion/mute/attachment handler
	 */
	async function handleWidgetDeletion(action, ctx) {
		const postEl = ctx?.post || ctx?.arrow?.closest('.post');
		if (!postEl || !ctx?.url) {
			console.error("Error: Invalid context or post element.");
			return;
		}

		const type = action === 'deleteAttachment' ? 'file' : 'post';

		const successMessages = {
			delete: 'Post deleted!',
			mute: 'Post deleted and user muted!',
			deleteAttachment: 'Attachment deleted!'
		};

		const failMessages = {
			delete: 'Failed to delete post.',
			mute: 'Failed to delete and mute.',
			deleteAttachment: 'Failed to delete attachment.'
		};

		try {
			if (type === 'post') markAsDeleted(postEl, 'post');

			const res = await fetch(ctx.url, {
				method: 'GET',
				credentials: 'same-origin',
				headers: { 'X-Requested-With': 'XMLHttpRequest' },
				cache: 'no-store'
			});
			if (!res.ok) throw new Error();

			let data = {};
			try { data = await res.json(); } catch (_) {}

			if (data?.success && data.deleted_link && type === 'post') {
				postEl.dataset.deletedLink = data.deleted_link;
			}

			if (typeof showMessage === 'function') {
				showMessage(successMessages[action] || 'Action complete.', true);
			}

			if (type === 'file') {
				removeWidgetActions(postEl, ['deleteAttachment']);
				await reloadAttachment(postEl);
			} else {
				removeWidgetActions(postEl, ['delete', 'mute', 'deleteAttachment']);

				// -------------------------------
				// FIX: If OP → hide entire thread
				// -------------------------------
				if (postEl.classList.contains('op')) {
					const thread = postEl.closest('.thread');
					if (thread) {
						thread.style.transition = 'opacity 0.3s ease';
						thread.style.opacity = '0';
						setTimeout(() => thread.remove(), 300);
					}
				} else {
					// Non-OP delete (same as before)
					postEl.style.transition = 'opacity 0.3s ease';
					postEl.style.opacity = '0';
					setTimeout(() => {
						const parent = postEl.closest('.reply-container');
						if (parent) parent.remove();
						else postEl.remove();
					}, 300);
				}
			}
		} catch (err) {
			if (typeof showMessage === 'function') {
				showMessage(failMessages[action] || 'Failed to process deletion.', false);
			}
		}
	}

	/**
	 * Adds the “View deleted post” option dynamically if deleted_link exists
	 */
	function addViewDeletedMenu() {
		window.postWidget.registerMenuAugmenter(ctx => {
			const post = ctx?.post || ctx?.arrow?.closest('.post');
			if (!post || post.querySelector('[data-action="viewdeleted"]')) return [];
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

	// Register widget handlers
	if (window.postWidget) {
		window.postWidget.registerActionHandler('delete', ctx =>
			handleWidgetDeletion('delete', ctx)
		);
		window.postWidget.registerActionHandler('mute', ctx =>
			handleWidgetDeletion('mute', ctx)
		);
		window.postWidget.registerActionHandler('viewdeleted', ctx => {
			if (ctx?.url && ctx.url !== '#') window.location.assign(ctx.url);
		});
		addViewDeletedMenu();
	}

	// Admin delete attachment handler
	document.addEventListener('click', function (e) {
		const dfAnchor = e.target.closest('.adminDeleteFileFunction a[title="Delete attachment"]');
		if (!dfAnchor) return;
		handleFileDeletion(e, dfAnchor);
	});

})();
