(function () {
	// Utility: find the containing .post element
	function getPostEl(el) {
		return el.closest('.post');
	}

	// Utility: append a warning span + optional [VD] placeholder
	function appendWarning(postEl, type, addVD = true) {
		if (!postEl) return null;
		const infoExtra = postEl.querySelector('.postInfoExtra');
		if (!infoExtra) return null;

		const existsDeleted = infoExtra.querySelector('.warning[title="This post was deleted"]');
		const existsFileDel = infoExtra.querySelector('.warning[title="This post\'s file was deleted"]');

		function createVDPlaceholder() {
			const span = document.createElement('span');
			span.className = 'adminFunctions adminViewDeletedPostFunction';
			span.innerHTML = '[<a href="#" title="View deleted post">VD</a>]';
			return span;
		}

		if (type === 'post' && !existsDeleted) {
			const warn = document.createElement('span');
			warn.className = 'warning';
			warn.title = 'This post was deleted';
			warn.textContent = '[DELETED]';

			const spacer1 = document.createTextNode(' ');
			infoExtra.appendChild(spacer1);

			let vd = null, spacer2 = null;
			if (addVD) {
                vd = createVDPlaceholder();
                spacer2 = document.createTextNode(' ');
                infoExtra.appendChild(vd);
                infoExtra.appendChild(spacer2);
			}

			infoExtra.appendChild(warn);
			return { warn, spacer1, vd, spacer2 };

		} else if (type === 'file' && !existsFileDel) {
			const warn = document.createElement('span');
			warn.className = 'warning';
			warn.title = "This post's file was deleted";
			warn.textContent = '[FILE DELETED]';

			const spacer1 = document.createTextNode(' ');
			infoExtra.appendChild(spacer1);

			let vd = null, spacer2 = null;
			if (addVD) {
				vd = createVDPlaceholder();
				spacer2 = document.createTextNode(' ');
				infoExtra.appendChild(vd);
				infoExtra.appendChild(spacer2);
			}

			infoExtra.appendChild(warn);
			return { warn, spacer1, vd, spacer2 };
		}
		return null;
	}

	// Utility: hide admin delete controls as required
	function hideDeleteControls(postEl, type) {
		if (!postEl) return [];
		// Hide all delete and delete-mute controls if needed
		const deleteSpans = postEl.querySelectorAll('.adminDeleteFunction, #adminDeleteFunction, .adminDeleteMuteFunction, #adminDeleteMuteFunction');
		// Hide all file-delete controls if needed
		const fileDeleteSpans = postEl.querySelectorAll('.adminDeleteFileFunction, #adminDeleteFileFunction');

		const hidden = [];

		if (type === 'post') {
			// Hide both delete and file delete controls
			for (let i = 0; i < deleteSpans.length; i++) {
				deleteSpans[i].classList.add('hidden');
				hidden.push(deleteSpans[i]);
			}
			for (let i = 0; i < fileDeleteSpans.length; i++) {
				fileDeleteSpans[i].classList.add('hidden');
				hidden.push(fileDeleteSpans[i]);
			}
		} else if (type === 'file') {
			// Hide only the file delete control(s)
			for (let i = 0; i < fileDeleteSpans.length; i++) {
				fileDeleteSpans[i].classList.add('hidden');
				hidden.push(fileDeleteSpans[i]);
			}
		}
		// Return hidden elements so they can be unhidden if needed
		return hidden;
	}

	// Main delegated click handler (handles all admin controls dynamically)
	document.addEventListener('click', function (e) {
		const control = e.target.closest(
			'.adminDeleteFunction, #adminDeleteFunction, ' +
			'.adminDeleteMuteFunction, #adminDeleteMuteFunction, ' +
			'.adminDeleteFileFunction, #adminDeleteFileFunction'
		);
		if (!control) return;

		const postEl = getPostEl(control);
		if (!postEl) return;

		// Track optimistic UI changes so we can undo them on request failure
		const addedClasses = [];   // items: { el, cls }
		const appendedNodes = [];  // items: Node
		const hiddenControls = []; // items: Element

		// Helper to register class additions for potential revert
		function addClassAndTrack(el, cls) {
			if (!el) return;
			el.classList.add(cls);
			addedClasses.push({ el: el, cls: cls });
		}

		let vdNode = null; // keep track of placeholder to set href when JSON arrives

		// ====== POST DELETION (D / DM) ======
		if (
			control.matches('.adminDeleteFunction, #adminDeleteFunction') ||
			control.matches('.adminDeleteMuteFunction, #adminDeleteMuteFunction')
		) {
			// If this post is the OP, mark the entire thread instead of just the OP
			if (postEl.classList.contains('op')) {
				const thread = postEl.closest('.thread');
				if (thread) {
					// Add deletedPost class to the entire thread
					addClassAndTrack(thread, 'deletedPost');

					// Add [DELETED] warning to every post in the thread
					const postsInThread = thread.querySelectorAll('.post');
					for (let i = 0; i < postsInThread.length; i++) {
						const p = postsInThread[i];
						// OP gets [VD]; replies do NOT
						const res = appendWarning(p, 'post', p.classList.contains('op'));
						if (res && res.spacer1) appendedNodes.push(res.spacer1);
						if (res && res.vd) { vdNode = res.vd; appendedNodes.push(res.vd); }
						if (res && res.spacer2) appendedNodes.push(res.spacer2);
						if (res && res.warn) appendedNodes.push(res.warn);
					}
				}
			} else {
				// Original behavior for non-OP posts: mark only the single post (with [VD])
				addClassAndTrack(postEl, 'deletedPost');
				const res = appendWarning(postEl, 'post', true);
				if (res && res.spacer1) appendedNodes.push(res.spacer1);
				if (res && res.vd) { vdNode = res.vd; appendedNodes.push(res.vd); }
				if (res && res.spacer2) appendedNodes.push(res.spacer2);
				if (res && res.warn) appendedNodes.push(res.warn);
			}
			// Hide both delete + file delete controls
			const hidden = hideDeleteControls(postEl, 'post');
			for (let i = 0; i < hidden.length; i++) hiddenControls.push(hidden[i]);
		}

		// ====== FILE DELETION ======
		if (control.matches('.adminDeleteFileFunction, #adminDeleteFileFunction')) {
			const imgContainer = postEl.querySelector('.imageSourceContainer');
			if (imgContainer) {
				addClassAndTrack(imgContainer, 'deletedFile');
			}
			const res = appendWarning(postEl, 'file', true);
			if (res && res.spacer1) appendedNodes.push(res.spacer1);
			if (res && res.vd) { vdNode = res.vd; appendedNodes.push(res.vd); }
			if (res && res.spacer2) appendedNodes.push(res.spacer2);
			if (res && res.warn) appendedNodes.push(res.warn);
			// Hide only the file delete control
			const hidden = hideDeleteControls(postEl, 'file');
			for (let i = 0; i < hidden.length; i++) hiddenControls.push(hidden[i]);
		}

		// ====== SEND DELETE REQUEST (AJAX) ======
		// Prevents full page reload; fires GET request to the admin control link.
		const href = (control.tagName === 'A' && control.href) ? control.href :
			(control.getAttribute && control.getAttribute('href')) ||
			(function () {
				const a = control.querySelector && control.querySelector('a[href]');
				return a ? a.href : null;
			})();

		
		function onFail() {
			// show the fail message
			showMessage("Post deletion failed!", false);
		}

		function onSuccess() {
			// show the success message
			showMessage("Post deleted!", true);
		}

		// Revert optimistic UI if the request fails or returns non-OK
		function revertUI() {
			// Remove classes we added
			for (let i = addedClasses.length - 1; i >= 0; i--) {
				const entry = addedClasses[i];
				if (entry && entry.el) entry.el.classList.remove(entry.cls);
			}
			// Remove nodes we appended (warnings + spacers + vd)
			for (let i = appendedNodes.length - 1; i >= 0; i--) {
				const node = appendedNodes[i];
				if (node && node.parentNode) node.parentNode.removeChild(node);
			}
			// Unhide any controls we hid
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
					// If server responded but not OK, undo optimistic UI
					if (!res.ok) {
						// FAIL message
						onFail();
						
						revertUI();
					} else {
						// SUCCESS: set the VD href if provided
						res.json().then(function (data) {
							if (data && data.success && data.deleted_link && vdNode) {
								const a = vdNode.querySelector('a');
								if (a) a.href = data.deleted_link;
							}
							onSuccess();
						}).catch(function () {
							onSuccess();
						});
					}
				}).catch(function () {
					// FAIL message
					onFail();

					// On network or other fetch errors, undo optimistic UI
					revertUI();
				});
			} catch (_) {
				// On synchronous errors, also undo optimistic UI
				revertUI();
			}
		}
	});
})();
