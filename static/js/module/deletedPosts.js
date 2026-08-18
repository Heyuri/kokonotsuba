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
	function addAttachmentDeleteEntry(attachmentEl, postEl) {
		if (!attachmentEl) return;

		var tmpl = document.getElementById('del-file-restore-tmpl');
		var data = attachmentEl.querySelector('.attachmentWidgetData');
		if (!tmpl || !data) return;

		// already there — nothing to add
		if (data.querySelector('a[data-action="deleteFile"]')) return;

		var host = postEl || attachmentEl.closest('.post');
		var fileId = attachmentEl.dataset.fileId || '';
		var postUid = host ? (host.dataset.postUid || '') : '';

		var clone = tmpl.content.cloneNode(true);

		clone.querySelectorAll('a').forEach(function (a) {
			a.setAttribute('href', a.getAttribute('href')
				.replace('__FILEID__', fileId)
				.replace('__POSTUID__', postUid));
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

	// ---- the entry window ----

	/**
	 * Open the deletion entry in a window instead of navigating to the mod page.
	 *
	 * The post itself is the copy already on the page; only the entry's own metadata and the
	 * actions it offers come from the server. Falls back to the mod page when the window can't be
	 * built (no window library, no template, no id to look up).
	 */
	function openEntryWindow(ctx, postEl, attachmentEl) {
		var fullViewUrl = (ctx.url && ctx.url !== '#') ? ctx.url : '';
		var deletedPostId = (ctx.params && ctx.params.deletedpostid)
			|| (attachmentEl && attachmentEl.dataset.deletedPostId)
			|| (postEl && postEl.dataset.deletedPostId);

		if (!deletedPostId || !window.PostActionUtils || !document.getElementById('dpEntryWindowTemplate')) {
			if (fullViewUrl) window.location.assign(fullViewUrl);
			return;
		}

		PostActionUtils.openWindow({
			templateId: '#dpEntryWindowTemplate',
			title: 'Deletion entry',
			postEl: postEl,
			onOpen: function (opened) {
				fillEntryWindow(opened.win, deletedPostId, postEl, attachmentEl, fullViewUrl);
			}
		});
	}

	function fillEntryWindow(win, deletedPostId, postEl, attachmentEl, fullViewUrl) {
		var body = win.div.querySelector('.dpEntryWindow');
		if (!body) return;

		showEntryPost(body, postEl);

		var fullView = body.querySelector('.dpEntryFullView');
		if (fullView) {
			if (fullViewUrl) fullView.href = fullViewUrl;
			else fullView.parentNode.hidden = true;
		}

		fetchEntry(deletedPostId)
			.then(function (data) {
				fillEntryDetails(body, data);
				fillEntryActions(body, data, win, deletedPostId, postEl, attachmentEl);
			})
			.catch(function (err) {
				showMessage(PostActionUtils.errorMessage(err, 'Could not read the deletion entry.'), false);
			});
	}

	// ---- reading an entry ----

	// Entries already read, by id: opening a menu warms the one behind it, so the click that
	// follows usually has it in hand. Short-lived, because another moderator may act meanwhile.
	var entryCache = {};
	var ENTRY_TTL = 30000;

	function fetchEntry(deletedPostId) {
		var cached = entryCache[deletedPostId];
		if (cached && (Date.now() - cached.time) < ENTRY_TTL) {
			return cached.request;
		}

		var url = moduleUrl() + '&pageName=entryData&deletedPostId=' + encodeURIComponent(deletedPostId);

		var request = fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
			.then(function (res) {
				// the endpoint explains a refusal ("not authenticated to view this"), so keep it
				if (!res.ok) {
					return PostActionUtils.responseError(res).then(function (err) { throw err; });
				}
				return res.json();
			})
			.catch(function (err) {
				// a refusal or a dropped request is not worth remembering
				forgetEntry(deletedPostId);
				throw err;
			});

		entryCache[deletedPostId] = { request: request, time: Date.now() };

		return request;
	}

	/** Warm an entry without caring about the result — the window is what reports a failure. */
	function prefetchEntry(deletedPostId) {
		fetchEntry(deletedPostId).catch(function () {});
	}

	/** Anything that acts on an entry changes what it says, so the copy in hand is spent. */
	function forgetEntry(deletedPostId) {
		delete entryCache[deletedPostId];
	}

	/** The entry id a menu entry carries, whichever way the attribute was cased. */
	function entryIdOf(params) {
		return (params && (params.deletedpostid || params.deletedPostId)) || '';
	}

	// A menu carrying a [View entry] is a menu whose entry is about to be wanted.
	document.addEventListener('widgetMenu:open', function (ev) {
		var items = (ev.detail && ev.detail.items) || [];

		items.forEach(function (item) {
			if (item.action !== 'viewDeletedPost' && item.action !== 'viewDeletedAttachment') return;

			var deletedPostId = entryIdOf(item.params);

			// the placeholder stands in the template the augmenters fill from
			if (deletedPostId && deletedPostId !== '__DPID__') prefetchEntry(deletedPostId);
		});
	});

	/** Show the post the menu was opened from — a static copy, without its own menus */
	function showEntryPost(body, postEl) {
		var container = body.querySelector('.dpEntryPost');
		if (!container || !postEl) return;

		var clone = postEl.cloneNode(true);

		// ids would be duplicated across the document, and the copy has no menus of its own
		clone.removeAttribute('id');
		clone.querySelectorAll('[id]').forEach(function (el) { el.removeAttribute('id'); });
		clone.querySelectorAll('.postMenu, .widgetRefs, .attachmentWidgetData, .attachmentMenuToggle')
			.forEach(function (el) { el.remove(); });

		container.appendChild(clone);
	}

	function fillEntryDetails(body, data) {
		function set(selector, value) {
			var el = body.querySelector(selector);
			if (el) el.textContent = value;
		}

		set('.dpEntryBoard', data.boardTitle + ' (' + data.boardUid + ')');
		set('.dpEntryDeletedBy', data.deletedBy || 'User');
		set('.dpEntryDeletedAt', data.deletedAt);

		// a restored entry says who put it back, an open one has nothing to say yet
		if (!data.isOpen) {
			set('.dpEntryRestoredBy', data.restoredBy || 'N/A');
			set('.dpEntryRestoredAt', data.restoredAt || 'N/A');
			body.querySelectorAll('.dpEntryRestoredRow').forEach(function (row) { row.hidden = false; });
		}
	}

	/** The same actions the entry page offers, each posting what its form would have posted */
	function fillEntryActions(body, data, win, deletedPostId, postEl, attachmentEl) {
		var container = body.querySelector('.dpEntryActions');
		if (!container || !data.actions) return;

		data.actions.forEach(function (entry) {
			var button = document.createElement('button');
			button.type = 'button';
			button.className = 'adminFunctions buttonLink';
			button.textContent = entry.label;

			button.addEventListener('click', function () {
				runEntryAction(entry.action, {
					win: win,
					params: { deletedPostId: deletedPostId, action: entry.action },
					postEl: postEl,
					attachmentEl: attachmentEl
				});
			});

			container.appendChild(document.createTextNode('['));
			container.appendChild(button);
			container.appendChild(document.createTextNode('] '));
		});
	}

	function runEntryAction(action, ctx) {
		var url = moduleUrl();
		var close = function () { ctx.win.remove(); };

		// restoring, purging or deleting the record rewrites the entry this was read from
		forgetEntry(entryIdOf(ctx.params));

		switch (action) {
			case 'restore':
				restorePost(ctx.postEl, url, ctx.params, close);
				break;
			case 'purge':
				purgePost(ctx.postEl, url, ctx.params, close);
				break;
			case 'restoreAttachment':
				restoreFile(ctx.attachmentEl, ctx.postEl, url, ctx.params, close);
				break;
			case 'purgeAttachment':
				purgeFile(ctx.attachmentEl, ctx.postEl, url, ctx.params, close);
				break;
			case 'deleteRecord':
				deleteEntryRecord(url, ctx.params, close);
				break;
		}
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
				addAttachmentDeleteEntry(attachmentEl, postEl);
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
				forgetEntry(entryIdOf(ctx.params));
				restorePost(ctx.post, ctx.url, ctx.params);
			});

			window.postWidget.registerActionHandler('purgeDeletedPost', function (ctx) {
				forgetEntry(entryIdOf(ctx.params));
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
			forgetEntry(entryIdOf(ctx.params));
			restoreFile(attachmentElOf(ctx), ctx.post, ctx.url, ctx.params);
		});

		window.attachmentWidget.registerActionHandler('purgeDeletedFile', function (ctx) {
			forgetEntry(entryIdOf(ctx.params));
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
