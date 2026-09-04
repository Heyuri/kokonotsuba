(function () {
	const PREVIEW_DELAY = 250;
	const OFFSET_X = 12;
	const RIGHT_MARGIN = 30;

	/** post_uid -> promise of the post's rendered HTML, so a row is only ever fetched once. */
	const previewCache = new Map();

	function metaContent(name, fallback) {
		return document.querySelector(`meta[name="${name}"]`)?.content || fallback;
	}

	/** A post's rendered HTML from the post API, or null when it can't be had. */
	function fetchPostHtml(postUid) {
		if (previewCache.has(postUid)) return previewCache.get(postUid);

		const apiUrl = metaContent('postApiUrl', null);
		const promise = !apiUrl
			? Promise.resolve(null)
			: fetch(`${apiUrl}${apiUrl.includes('?') ? '&' : '?'}post_uid=${encodeURIComponent(postUid)}`)
				.then(res => (res.ok ? res.json() : null))
				.then(data => data?.html || null)
				.catch(() => null);

		previewCache.set(postUid, promise);
		return promise;
	}

	function createPreviewBox() {
		const box = document.createElement('div');
		box.className = 'previewBox mergeThreadPreview';
		box.style.position = 'absolute';
		// .previewBox is display:none until a preview is actually shown, and sits at the same
		// z-index as .window, so both have to be set for the box to appear over the merge window
		box.style.display = 'block';
		box.style.zIndex = '9998';
		document.body.appendChild(box);
		return box;
	}

	/**
	 * Anchor the box under the hovered row at the cursor's x, shrinking it to the space left
	 * rather than moving it, and flip it above the row when it would run off the bottom.
	 */
	function positionPreviewBox(box, event, row) {
		const left = Math.max(0, event.clientX - OFFSET_X);
		const available = window.innerWidth - RIGHT_MARGIN - left;

		box.style.setProperty('max-width', `${Math.max(0, available)}px`, 'important');
		box.style.left = `${left}px`;

		const rect = row.getBoundingClientRect();
		const height = box.offsetHeight;

		box.style.top = `${rect.bottom + height > window.innerHeight
			? Math.max(rect.top + window.scrollY - height, window.scrollY)
			: rect.bottom + window.scrollY}px`;
	}

	/** Strip the parts of a cloned post that only make sense in place. */
	function cloneForPreview(post) {
		const clone = post.cloneNode(true);
		clone.removeAttribute('id');
		clone.style.margin = '0';
		clone.querySelectorAll('.deletionCheckbox').forEach(el => el.remove());
		return clone;
	}

	/**
	 * Preview a thread's opening post while the cursor rests on its row in the merge list.
	 *
	 * The OP is cloned from the page when the board index already rendered it, and fetched from
	 * the post API otherwise, which is the case on a thread page.
	 *
	 * @returns {function} teardown, run when the merge window closes
	 */
	function attachThreadPreviews(form) {
		const rows = form.querySelectorAll('.mergeThreadItem[data-op-post-uid]');
		if (!rows.length) return () => {};

		let box = null;
		let timer = null;
		let activeRow = null;
		let lastEvent = null;

		const hide = () => {
			if (timer) clearTimeout(timer);
			timer = null;
			if (box) box.remove();
			box = null;
			activeRow = null;
		};

		const show = row => {
			const targetId = row.dataset.opTargetId;
			const onPage = targetId ? document.getElementById(targetId) : null;

			box = createPreviewBox();

			if (onPage) {
				box.appendChild(cloneForPreview(onPage));
				positionPreviewBox(box, lastEvent, row);
				return;
			}

			box.innerHTML = `<div class="post reply">${metaContent('postApiFetchingText', 'Fetching post...')}</div>`;
			positionPreviewBox(box, lastEvent, row);

			// the row may have been left, or another one hovered, while the fetch was in flight
			const pending = box;
			fetchPostHtml(row.dataset.opPostUid).then(html => {
				if (pending !== box) return;

				box.innerHTML = html || '<div class="post reply">Post not found</div>';
				positionPreviewBox(box, lastEvent, row);
			});
		};

		const onEnter = event => {
			const row = event.currentTarget;
			hide();
			activeRow = row;
			lastEvent = event;

			timer = setTimeout(() => {
				timer = null;
				if (activeRow === row && row.isConnected) show(row);
			}, PREVIEW_DELAY);
		};

		const onMove = event => {
			lastEvent = event;
			if (box) positionPreviewBox(box, event, event.currentTarget);
		};

		rows.forEach(row => {
			row.addEventListener('mouseenter', onEnter);
			row.addEventListener('mousemove', onMove);
			row.addEventListener('mouseleave', hide);
		});

		return () => {
			hide();
			rows.forEach(row => {
				row.removeEventListener('mouseenter', onEnter);
				row.removeEventListener('mousemove', onMove);
				row.removeEventListener('mouseleave', hide);
			});
		};
	}

	window.postWidget.registerActionHandler('mergeThread', function (ctx) {
		const postEl = ctx?.post || ctx?.arrow?.closest('.post');
		if (!postEl) return;

		const params = ctx.params || {};

		// Fall back to the widget URL when the thread uid didn't come through as a param
		let threadUid = params.thread_uid || '';
		if (!threadUid) {
			try {
				threadUid = new URL(ctx.url, location.href).searchParams.get('thread_uid') || '';
			} catch (_) {}
		}

		PostActionUtils.openWindow({
			templateId: '#mergeThreadFormTemplate',
			title: 'Merge threads',
			postEl,
			fields: [],
			onOpen: ({ form, win }) => {
				// Destination thread (hidden)
				const uidInput = form.querySelector('[name="merge-thread-uid"]');
				if (uidInput && threadUid) uidInput.value = threadUid;

				// Destination thread number and subject (display)
				const threadNumEl = form.querySelector('#merge-thread-num');
				if (threadNumEl && params.thread_number) threadNumEl.textContent = params.thread_number;

				// The widget hands over the subject as plain text, so it goes straight in
				const subjectEl = form.querySelector('#merge-thread-subject');
				if (subjectEl) {
					subjectEl.textContent = (params.thread_subject || '').trim() || '(no subject)';
				}

				// A thread can't be merged into itself, so drop its own row from the list
				if (threadUid) {
					const ownRow = form.querySelector(`.mergeThreadItem[data-thread-uid="${CSS.escape(threadUid)}"]`);
					if (ownRow) ownRow.remove();
				}

				// after the removal above, so the dropped row is never wired up
				const detachPreviews = attachThreadPreviews(form);
				if (win) win.onclose = detachPreviews;
			},
			onSubmit: ({ form }) => {
				const ticked = form.querySelectorAll('[name="merge-source-uids[]"]:checked').length;
				const typed = form.querySelector('[name="merge-source-numbers"]')?.value.trim();

				if (!ticked && !typed) {
					showMessage('Pick at least one thread to merge.', false);
					return false;
				}

				return confirm('Merge the selected threads into this one?');
			},
			onSuccess: ({ res }) => {
				let data = res;
				if (typeof data === 'string') {
					try { data = JSON.parse(data); } catch (_) {}
				}

				const redirectUrl = data?.redirectUrl;
				if (redirectUrl) window.location.assign(redirectUrl);
				else window.location.reload();
			},
			onFail: ({ err }) => {
				console.error('Merge thread error:', err);
				showMessage('There was an error while merging the threads.', false);
			}
		});
	});
})();
