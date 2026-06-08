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

	if (window.postWidget) {
		if (typeof window.postWidget.registerActionHandler === 'function') {
			window.postWidget.registerActionHandler('viewDeletedPost', function (ctx) {
				if (ctx && ctx.url && ctx.url !== '#') window.location.assign(ctx.url);
			});

			window.postWidget.registerActionHandler('restoreDeletedPost', function (ctx) {
				var post = ctx.post;
				if (!post || !ctx.url) return;

				// Optimistic UI: strip deleted styling immediately
				var addedClasses = [];
				var hiddenIndicators = [];

				function applyRestore(el) {
					if (!el) return;
					el.classList.remove('deletedPost');
					addedClasses.push(el);
					el.querySelectorAll('.indicator-deleted, .indicator-fileDeleted').forEach(function (ind) {
						ind.classList.add('indicatorHidden');
						hiddenIndicators.push(ind);
					});
				}

				if (post.classList.contains('op')) {
					var thread = post.closest('.thread');
					if (thread) {
						applyRestore(thread);
						thread.querySelectorAll('.post').forEach(applyRestore);
					}
				} else {
					applyRestore(post);
				}

				sendModuleAction(ctx.url, {
					revertUI: function () {
						addedClasses.forEach(function (el) { el.classList.add('deletedPost'); });
						hiddenIndicators.forEach(function (ind) { ind.classList.remove('indicatorHidden'); });
					},
					successMessage: 'Post restored.',
					errorMessage: 'Failed to restore post.',
					onSuccess: function () {
						removeWidgetActions(post, ['viewDeletedPost', 'restoreDeletedPost', 'purgeDeletedPost']);

						// Clear the deleted-post-id stored after live deletion
						delete post.dataset.deletedPostId;

						// Re-inject delete/mute entries from the adminDel template
						var delTmpl = document.getElementById('del-restore-tmpl');
						if (delTmpl) {
							var refs = post.querySelector('.widgetRefs');
							if (refs) {
								var clone = delTmpl.content.cloneNode(true);
								var postUid = post.dataset.postUid;
								clone.querySelectorAll('a').forEach(function (a) {
									if (a.getAttribute('data-param-post_uid') === '__POSTUID__') {
										a.setAttribute('data-param-post_uid', postUid);
									}
								});
								// Prepend so delete/mute appear first, matching server-side order
								refs.insertBefore(clone, refs.firstChild);
							}
						}
					}
				}, ctx.params);
			});

			window.postWidget.registerActionHandler('purgeDeletedPost', function (ctx) {
				var post = ctx.post;
				if (!post || !ctx.url) return;

				sendModuleAction(ctx.url, {
					successMessage: 'Post purged.',
					errorMessage: 'Failed to purge post.',
					onSuccess: function () {
						fadeAndRemovePost(post);
					}
				}, ctx.params);
			});
		}
	}

	// Augment the post widget menu: when a post has been deleted live (postDeletion.js sets
	// dataset.deletedPostId), clone the server-rendered <template id="dp-widget-tmpl"> and
	// return its entries — labels, URLs, and subMenu name all come from the server.
	if (window.postWidget && typeof window.postWidget.registerMenuAugmenter === 'function') {
		window.postWidget.registerMenuAugmenter(function (ctx) {
			var post = ctx.post;
			if (!post) return [];

			// data-deleted-post-id is set by postDeletion.js after a live deletion
			var deletedPostId = post.dataset.deletedPostId;
			if (!deletedPostId) return [];

			// Don't add if already present (rendered server-side or previously injected)
			if (post.querySelector('.widgetRefs a[data-action="restoreDeletedPost"]')) return [];

			var tmpl = document.getElementById('dp-widget-tmpl');
			if (!tmpl) return [];

			var items = [];
			tmpl.content.querySelectorAll('a[data-action]').forEach(function (a) {
				var action  = a.getAttribute('data-action')  || '';
				var label   = a.getAttribute('data-label')   || '';
				var subMenu = a.getAttribute('data-submenu') || '';
				var href    = a.getAttribute('href')         || '#';

				if (action === 'viewDeletedPost') {
					var viewLink = post.dataset.deletedLink;
					if (!viewLink) return; // no link yet — skip
					href = viewLink;
				}

				// Collect params and replace the placeholder with the real deleted-post ID
				var params = {};
				for (var i = 0; i < a.attributes.length; i++) {
					var attr = a.attributes[i];
					if (attr.name.indexOf('data-param-') === 0) {
						var key = attr.name.slice(11);
						params[key] = attr.value === '__DPID__' ? deletedPostId : attr.value;
					}
				}

				items.push({ href: href, action: action, label: label, subMenu: subMenu, params: params });
			});
			return items;
		});
	}

})();
