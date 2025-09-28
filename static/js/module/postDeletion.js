(function () {
	// Utility: find the containing .post element
	function getPostEl(el) {
		return el.closest('.post');
	}

	// Utility: append a warning span to .postInfoExtra (avoid duplicates)
	function appendWarning(postEl, type) {
		if (!postEl) return;
		const infoExtra = postEl.querySelector('.postInfoExtra');
		if (!infoExtra) return;

		const existsDeleted = infoExtra.querySelector('.warning[title="This post was deleted"]');
		const existsFileDel = infoExtra.querySelector('.warning[title="This post\'s file was deleted"]');

		// Add [DELETED] warning for post deletions
		if (type === 'post' && !existsDeleted) {
			const warn = document.createElement('span');
			warn.className = 'warning';
			warn.title = 'This post was deleted';
			warn.textContent = '[DELETED]';
			infoExtra.appendChild(document.createTextNode(' '));
			infoExtra.appendChild(warn);

		// Add [FILE DELETED] warning for file deletions
		} else if (type === 'file' && !existsFileDel) {
			const warn = document.createElement('span');
			warn.className = 'warning';
			warn.title = "This post's file was deleted";
			warn.textContent = '[FILE DELETED]';
			infoExtra.appendChild(document.createTextNode(' '));
			infoExtra.appendChild(warn);
		}
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
					thread.classList.add('deletedPost');

					// Add [DELETED] warning to every post in the thread
					const postsInThread = thread.querySelectorAll('.post');
					for (let i = 0; i < postsInThread.length; i++) {
						appendWarning(postsInThread[i], 'post');
					}
				}
			} else {
				// Original behavior for non-OP posts: mark only the single post
				postEl.classList.add('deletedPost');
				appendWarning(postEl, 'post');
			}
		}

		// ====== FILE DELETION ======
		if (control.matches('.adminDeleteFileFunction, #adminDeleteFileFunction')) {
			const imgContainer = postEl.querySelector('.imageSourceContainer');
			if (imgContainer) {
				imgContainer.classList.add('deletedFile');
			}
			appendWarning(postEl, 'file');
		}

		// ====== SEND DELETE REQUEST (AJAX) ======
		// Prevents full page reload; fires GET request to the admin control link.
		const href = (control.tagName === 'A' && control.href) ? control.href :
			(control.getAttribute && control.getAttribute('href')) ||
			(function () {
				const a = control.querySelector && control.querySelector('a[href]');
				return a ? a.href : null;
			})();

		if (href) {
			e.preventDefault();
			try {
				fetch(href, {
					method: 'GET',
					credentials: 'same-origin',
					headers: { 'X-Requested-With': 'XMLHttpRequest' },
					cache: 'no-store'
				}).catch(function () {
					// Swallow errors: UI already reflects the action.
				});
			} catch (_) {
				// Ignore: best-effort fire-and-forget.
			}
		}
	});
})();