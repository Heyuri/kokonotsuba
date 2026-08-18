(function() {
	'use strict';

	// Function to handle request
	function handleRequest(url, formData, onSuccess, onFailure) {
		const urlEncoded = new URLSearchParams();
		for (const [k, v] of formData.entries()) {
			urlEncoded.append(k, v);
		}

		fetch(url, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'X-Requested-With': 'XMLHttpRequest',
				'Content-Type': 'application/x-www-form-urlencoded'
			},
			body: urlEncoded
		})
			.then(response => {
				if (response.ok || (response.status >= 200 && response.status < 300)) {
					return response.text();
				} else {
					throw new Error('Non-2xx response received');
				}
			})
			.then(() => {
				onSuccess();
			})
			.catch(() => {
				onFailure();
			});
	}

	// Function to collect form data
	function createFormData(button) {
		const form = button.closest('form');
		const formData = new FormData(form);

		const deletedPostIdInput = form.querySelector('input[name="deletedPostId"]');
		if (deletedPostIdInput) {
			formData.delete('deletedPostId');
			formData.append('deletedPostId', deletedPostIdInput.value);
		}

		if (button.name) {
			formData.append(button.name, button.value || '');
		}

		return formData;
	}

	// The .attachmentContainer an attachment menu was opened from
	function attachmentElOf(ctx) {
		return ctx.container || (ctx.bar && ctx.bar.closest('.attachmentContainer'));
	}

	// Put the file's delete entry back from adminDel's template — a restored file can be deleted
	// again, but the server left that entry out while it was deleted
	function addAttachmentDeleteEntry(attachmentEl, params) {
		if (!attachmentEl || !params) return;

		var tmpl = document.getElementById('del-file-restore-tmpl');
		var data = attachmentEl.querySelector('.attachmentWidgetData');
		if (!tmpl || !data) return;

		// already there — nothing to add
		if (data.querySelector('a[data-action="deleteFile"]')) return;

		var clone = tmpl.content.cloneNode(true);

		clone.querySelectorAll('a').forEach(function (a) {
			a.setAttribute('href', a.getAttribute('href')
				.replace('__FILEID__', params.fileid)
				.replace('__POSTUID__', params.postuid));
		});

		data.appendChild(clone);
	}

	// Drop this file's deletion entries, both the server-rendered ones and the dataset the
	// augmenter rebuilds them from after a live deletion
	function clearAttachmentDeletionEntries(attachmentEl) {
		if (!attachmentEl) return;

		removeAttachmentWidgetActions(attachmentEl, ['viewDeletedAttachment', 'restoreDeletedFile', 'purgeDeletedFile']);

		delete attachmentEl.dataset.deletedPostId;
		delete attachmentEl.dataset.deletedLink;
	}

	/**
	 * Build menu items from a server-rendered <template>, so labels, URLs and submenu names all
	 * come from the server.
	 *
	 * @param {string} templateId  Element id of the <template>
	 * @param {Object} values      Placeholder ('__DPID__', ...) => the real value
	 * @param {Function} [hrefFor] Given (action, templateHref), the href to use — return null to
	 *                             drop the entry, for a link that isn't known yet
	 */
	function itemsFromTemplate(templateId, values, hrefFor) {
		var tmpl = document.getElementById(templateId);
		if (!tmpl) return [];

		var items = [];

		tmpl.content.querySelectorAll('a[data-action]').forEach(function (a) {
			var action = a.getAttribute('data-action') || '';
			var href = a.getAttribute('href') || '#';

			if (hrefFor) href = hrefFor(action, href);
			if (href === null) return;

			// fill the placeholders the server left in the parameters
			var params = {};
			for (var i = 0; i < a.attributes.length; i++) {
				var attr = a.attributes[i];
				if (attr.name.indexOf('data-param-') !== 0) continue;

				params[attr.name.slice(11)] = values.hasOwnProperty(attr.value) ? values[attr.value] : attr.value;
			}

			items.push({
				href: href,
				action: action,
				label: a.getAttribute('data-label') || '',
				subMenu: a.getAttribute('data-submenu') || '',
				params: params
			});
		});

		return items;
	}

	function hideDeletedPost(postContainer) {
		if (postContainer) {
			// also remove a trailing threadSeparator <hr> if it follows this container
			const nextEl = postContainer.nextElementSibling;
			if (nextEl && nextEl.matches('hr.threadSeparator')) {
				// clear pending state on success
				nextEl.classList.remove('pendingDeletion');
				nextEl.style.display = 'none';
			}

			// clear pending state on success
			postContainer.classList.remove('pendingDeletion');
			postContainer.style.display = 'none';
		}
	}

	// Ensure the script applies to any 'deletedPostContainer'
	if (document.querySelector('.deletedPostContainer')) {
		const purgeBtnList = document.querySelectorAll('.adminPurgeFunction');
		const restoreBtnList = document.querySelectorAll('.adminRestoreFunction');
		const deleteRecordBtnList = document.querySelectorAll('.adminDeleteRecordFunction');
	
		function handleAdminAction(btn, successMsg, failureMsg) {
			btn.addEventListener('click', function (e) {
				e.preventDefault();
				const formData = createFormData(btn);
			
				// Use the form's action ATTRIBUTE to avoid RadioNodeList shadowing
				const form = btn.closest('form');
				const url = form ? form.getAttribute('action') : btn.formAction;
			
				// mark as pending (container + possible trailing separator)
				const postContainer = btn.closest('.deletedPostContainer');
				const sep = postContainer && postContainer.nextElementSibling && postContainer.nextElementSibling.matches('hr.threadSeparator')
					? postContainer.nextElementSibling
					: null;
				if (postContainer) postContainer.classList.add('pendingDeletion');
				if (sep) sep.classList.add('pendingDeletion');
			
				handleRequest(url, formData, function () {
					// hide it with css
					hideDeletedPost(postContainer);
					showMessage(successMsg, true);
				}, function () {
					// revert pending state on failure
					if (postContainer) postContainer.classList.remove('pendingDeletion');
					if (sep) sep.classList.remove('pendingDeletion');
					showMessage(failureMsg, false);
				});
			});
		}
	
		// Handle Purge Button Click
		purgeBtnList.forEach(function (purgeBtn) {
			handleAdminAction(purgeBtn, 'Post purged successfully.', 'Failed to purge post.');
		});
	
		// Handle Restore Button Click
		restoreBtnList.forEach(function (restoreBtn) {
			handleAdminAction(restoreBtn, 'Post restored successfully.', 'Failed to restore post.');
		});

		// Handle Restore Button Click
		deleteRecordBtnList.forEach(function (deleteRecordBtn) {
			handleAdminAction(deleteRecordBtn, 'Restored post record removed from database.', 'Failed to remove record.');
		});
	}

	// ---- actions, shared by the menus and the entry window ----

	// Where the module's own actions are posted to, and where the window reads an entry from
	function moduleUrl() {
		var meta = document.querySelector('meta[name="deletedPostsModuleUrl"]');
		return meta ? meta.content : '';
	}

	// Restore a deleted post, undeleting its styling first and putting it back if the request fails
	function restorePost(postEl, url, params, onDone) {
		if (!postEl || !url) return;

		var restyled = [];
		var hiddenIndicators = [];

		function applyRestore(el) {
			if (!el) return;
			el.classList.remove('deletedPost');
			restyled.push(el);
			el.querySelectorAll('.indicator-deleted, .indicator-fileDeleted').forEach(function (ind) {
				ind.classList.add('indicatorHidden');
				hiddenIndicators.push(ind);
			});
		}

		if (postEl.classList.contains('op')) {
			var thread = postEl.closest('.thread');
			if (thread) {
				applyRestore(thread);
				thread.querySelectorAll('.post').forEach(applyRestore);
			}
		} else {
			applyRestore(postEl);
		}

		sendModuleAction(url, {
			revertUI: function () {
				restyled.forEach(function (el) { el.classList.add('deletedPost'); });
				hiddenIndicators.forEach(function (ind) { ind.classList.remove('indicatorHidden'); });
			},
			successMessage: 'Post restored.',
			errorMessage: 'Failed to restore post.',
			onSuccess: function () {
				removeWidgetActions(postEl, ['viewDeletedPost', 'restoreDeletedPost', 'purgeDeletedPost']);

				// clear the id stored after a live deletion, so the menu stops rebuilding the entries
				delete postEl.dataset.deletedPostId;

				addPostDeleteEntries(postEl);
				if (onDone) onDone();
			}
		}, params);
	}

	// Put the post's delete/mute entries back from adminDel's template
	function addPostDeleteEntries(postEl) {
		var tmpl = document.getElementById('del-restore-tmpl');
		var refs = postEl.querySelector('.widgetRefs');
		if (!tmpl || !refs) return;

		var clone = tmpl.content.cloneNode(true);
		var postUid = postEl.dataset.postUid;

		clone.querySelectorAll('a').forEach(function (a) {
			if (a.getAttribute('data-param-post_uid') === '__POSTUID__') {
				a.setAttribute('data-param-post_uid', postUid);
			}
		});

		// prepend so delete/mute appear first, matching server-side order
		refs.insertBefore(clone, refs.firstChild);
	}

	function purgePost(postEl, url, params, onDone) {
		if (!postEl || !url) return;

		sendModuleAction(url, {
			successMessage: 'Post purged.',
			errorMessage: 'Failed to purge post.',
			onSuccess: function () {
				fadeAndRemovePost(postEl);
				if (onDone) onDone();
			}
		}, params);
	}

	function restoreFile(attachmentEl, postEl, url, params, onDone) {
		if (!url) return;

		var indicator = attachmentEl ? attachmentEl.querySelector('.indicator-fileDeleted') : null;

		// Optimistic UI: undelete the file, put it back if the request fails
		if (attachmentEl) attachmentEl.classList.remove('deletedFile');
		if (indicator) indicator.classList.add('indicatorHidden');

		sendModuleAction(url, {
			revertUI: function () {
				if (attachmentEl) attachmentEl.classList.add('deletedFile');
				if (indicator) indicator.classList.remove('indicatorHidden');
			},
			successMessage: 'File restored.',
			errorMessage: 'Failed to restore the file.',
			onSuccess: function () {
				clearAttachmentDeletionEntries(attachmentEl);
				addAttachmentDeleteEntry(attachmentEl, params);
				if (onDone) onDone();
			}
		}, params);
	}

	function purgeFile(attachmentEl, postEl, url, params, onDone) {
		if (!url) return;

		sendModuleAction(url, {
			successMessage: 'File purged.',
			errorMessage: 'Failed to purge the file.',
			onSuccess: function () {
				// the file is gone, so drop the entries that act on it
				clearAttachmentDeletionEntries(attachmentEl);

				if (attachmentEl) attachmentEl.classList.add('deletedFile');
				showDeletionIndicator(postEl || attachmentEl, 'file', attachmentEl);
				if (onDone) onDone();
			}
		}, params);
	}

	// Remove a restored post's record. The post itself is untouched, so nothing on the page changes.
	function deleteEntryRecord(url, params, onDone) {
		sendModuleAction(url, {
			successMessage: 'Record deleted.',
			errorMessage: 'Failed to delete the record.',
			onSuccess: function () { if (onDone) onDone(); }
		}, params);
	}

	if (window.postWidget) {
		if (typeof window.postWidget.registerActionHandler === 'function') {
			window.postWidget.registerActionHandler('viewDeletedPost', function (ctx) {
				openEntryWindow(ctx, ctx.post, null);
			});

			window.postWidget.registerActionHandler('restoreDeletedPost', function (ctx) {
				restorePost(ctx.post, ctx.url, ctx.params);
			});

			window.postWidget.registerActionHandler('purgeDeletedPost', function (ctx) {
				purgePost(ctx.post, ctx.url, ctx.params);
			});
		}
	}

	// The attachment menu's Deletion submenu
	if (window.attachmentWidget && typeof window.attachmentWidget.registerActionHandler === 'function') {
		window.attachmentWidget.registerActionHandler('viewDeletedAttachment', function (ctx) {
			openEntryWindow(ctx, ctx.post, attachmentElOf(ctx));
		});

		window.attachmentWidget.registerActionHandler('restoreDeletedFile', function (ctx) {
			restoreFile(attachmentElOf(ctx), ctx.post, ctx.url, ctx.params);
		});

		window.attachmentWidget.registerActionHandler('purgeDeletedFile', function (ctx) {
			purgeFile(attachmentElOf(ctx), ctx.post, ctx.url, ctx.params);
		});
	}

	// Augment the post menu: when a post has been deleted live (postDeletion.js sets
	// dataset.deletedPostId), add the Deletion submenu from the server-rendered template.
	if (window.postWidget && typeof window.postWidget.registerMenuAugmenter === 'function') {
		window.postWidget.registerMenuAugmenter(function (ctx) {
			var post = ctx.post;
			if (!post) return [];

			var deletedPostId = post.dataset.deletedPostId;
			if (!deletedPostId) return [];

			// don't add if already present (rendered server-side or previously injected)
			if (post.querySelector('.widgetRefs a[data-action="restoreDeletedPost"]')) return [];

			return itemsFromTemplate('dp-widget-tmpl', { '__DPID__': deletedPostId }, function (action, href) {
				// the entry's link only exists once the deletion has happened
				return action === 'viewDeletedPost' ? (post.dataset.deletedLink || null) : href;
			});
		});
	}

	// The same for the attachment menu, after a file has been deleted live. The purge entry comes
	// from its own template, which the server only emits for staff who may purge.
	if (window.attachmentWidget && typeof window.attachmentWidget.registerMenuAugmenter === 'function') {
		window.attachmentWidget.registerMenuAugmenter(function (ctx) {
			var attachmentEl = attachmentElOf(ctx);
			if (!attachmentEl) return [];

			// set by postDeletionLib.js from the deletion response
			var deletedPostId = attachmentEl.dataset.deletedPostId;
			if (!deletedPostId) return [];

			var data = attachmentEl.querySelector('.attachmentWidgetData');
			if (data && data.querySelector('a[data-action="restoreDeletedFile"]')) return [];

			var values = {
				'__DPID__': deletedPostId,
				'__FILEID__': attachmentEl.dataset.fileId || '',
				'__POSTUID__': (ctx.post && ctx.post.dataset.postUid) || ''
			};

			function hrefFor(action, href) {
				return action === 'viewDeletedAttachment' ? (attachmentEl.dataset.deletedLink || null) : href;
			}

			return itemsFromTemplate('dp-attachment-widget-tmpl', values, hrefFor)
				.concat(itemsFromTemplate('dp-attachment-purge-tmpl', values, hrefFor));
		});
	}

})();
