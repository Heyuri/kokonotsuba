(function () {
	/**
	 * Hide or mark a post/thread as deleted, depending on type
	 */
	function markAsDeleted(postEl, type) {
		if (!postEl) return;
		if (type === 'file') {
			const imgContainer = postEl.querySelector('.imageSourceContainer');
			if (imgContainer) imgContainer.classList.add('deletedFile');
			appendWarning(postEl, 'file');
		} else {
			if (postEl.classList.contains('op')) {
				const thread = postEl.closest('.thread');
				if (thread) {
					thread.classList.add('deletedPost');
					thread.querySelectorAll('.post').forEach(p => appendWarning(p, 'post'));
				}
			} else {
				postEl.classList.add('deletedPost');
				appendWarning(postEl, 'post');
			}
		}
	}

	/**
	 * Create and append a deletion warning message
	 */
	function appendWarning(postEl, type) {
		if (!postEl) return;
		const infoExtra = postEl.querySelector('.postInfoExtra');
		if (!infoExtra) return;

		const existing = infoExtra.querySelector(
			type === 'file'
				? '.warning[title="This post\'s file was deleted"]'
				: '.warning[title="This post was deleted"]'
		);
		if (existing) return;

		const warn = document.createElement('span');
		warn.className = 'warning';
		warn.title =
			type === 'file' ? "This post's file was deleted" : 'This post was deleted';
		warn.textContent = type === 'file' ? '[FILE DELETED]' : '[DELETED]';
		infoExtra.append(' ', warn);
	}

	/**
	 * Removes widget menu actions that no longer make sense
	 */
	function removeWidgetActions(postEl, actions) {
		if (!postEl || !actions?.length) return;
		const refs = postEl.querySelector('.widgetRefs');
		if (!refs) return;
		for (const act of actions) {
			refs.querySelectorAll(`[data-action="${act}"]`).forEach(a => a.remove());
		}
	}

	/**
	 * Reload just the attachment HTML (after a file deletion)
	 */
	async function reloadAttachment(postEl) {
		const imgContainer = postEl.querySelector('.imageSourceContainer');
		if (!imgContainer) return;
		try {
			const res = await fetch(window.location.href, { cache: 'no-store' });
			if (!res.ok) throw new Error('failed to reload');
			const text = await res.text();
			const tmp = document.createElement('div');
			tmp.innerHTML = text;
			const newContainer = tmp.querySelector(
				`#${postEl.id} .imageSourceContainer`
			);
			if (newContainer) {
				imgContainer.replaceWith(newContainer);
			} else {
				// fallback: clear content if attachment is gone
				imgContainer.remove();
			}
		} catch (err) {
			console.error('Attachment reload failed:', err);
		}
	}

	/**
	 * Unified deletion/mute/attachment handler
	 */
	async function handleWidgetDeletion(action, ctx) {
		const postEl = ctx?.post || ctx?.arrow?.closest('.post');
		if (!postEl || !ctx?.url) return;

		const type =
			action === 'deleteAttachment' ? 'file' : 'post';

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
			markAsDeleted(postEl, type);

			const res = await fetch(ctx.url, {
				method: 'GET',
				credentials: 'same-origin',
				headers: { 'X-Requested-With': 'XMLHttpRequest' },
				cache: 'no-store'
			});

			if (!res.ok) throw new Error('Server returned ' + res.status);

			let data = {};
			try {
				data = await res.json();
			} catch (_) {
				// non-JSON ok
			}

			if (data?.success && data.deleted_link) {
				postEl.dataset.deletedLink = data.deleted_link;
			}

			showMessage(successMessages[action] || 'Action complete.', true);

			if (type === 'file') {
				removeWidgetActions(postEl, ['deleteAttachment']);
				await reloadAttachment(postEl);
			} else {
				removeWidgetActions(postEl, ['delete', 'mute', 'deleteAttachment']);
				// hide entire post after deletion
				postEl.style.transition = 'opacity 0.3s ease';
				postEl.style.opacity = '0';
				setTimeout(() => postEl.remove(), 300);
			}
		} catch (err) {
			console.error('handleWidgetDeletion error:', err);
			showMessage(failMessages[action] || 'Failed to process deletion.', false);
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
			return [
				{
					href: link,
					action: 'viewdeleted',
					label: 'View deleted post',
					subMenu: ''
				}
			];
		});
	}

	// --- Register the widget handlers ---
	if (window.postWidget) {
		window.postWidget.registerActionHandler('delete', ctx =>
			handleWidgetDeletion('delete', ctx)
		);
		window.postWidget.registerActionHandler('mute', ctx =>
			handleWidgetDeletion('mute', ctx)
		);
		window.postWidget.registerActionHandler('deleteAttachment', ctx =>
			handleWidgetDeletion('deleteAttachment', ctx)
		);
		window.postWidget.registerActionHandler('viewdeleted', ctx => {
			if (ctx?.url && ctx.url !== '#') window.location.assign(ctx.url);
		});
		addViewDeletedMenu();
	}
})();
