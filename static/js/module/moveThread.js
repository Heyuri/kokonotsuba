(function () {
	window.postWidget.registerActionHandler('moveThread', function (ctx) {
		const postEl = ctx?.post || ctx?.arrow?.closest('.post');
		if (!postEl) return;

		// Extract thread_uid from the widget URL
		let threadUid = '';
		try {
			const url = new URL(ctx.url, location.href);
			threadUid = url.searchParams.get('thread_uid') || '';
		} catch (_) {}

		const params = ctx.params || {};

		PostActionUtils.openWindow({
			templateId: '#moveThreadFormTemplate',
			title: 'Move thread',
			postEl,
			fields: [],
			onOpen: ({ form }) => {
				// Thread UID (hidden)
				const uidInput = form.querySelector('[name="move-thread-uid"]');
				if (uidInput && threadUid) uidInput.value = threadUid;

				// Thread number (display)
				const threadNumEl = form.querySelector('#move-thread-num');
				if (threadNumEl && params.thread_number) threadNumEl.textContent = params.thread_number;

				// Current board name (display)
				const boardEl = form.querySelector('#move-thread-board');
				if (boardEl && params.board_name) boardEl.textContent = params.board_name;

				// Current board UID (hidden)
				const boardUidInput = form.querySelector('[name="move-thread-board-uid"]');
				if (boardUidInput && params.board_uid) boardUidInput.value = params.board_uid;

				// Hide the radio option for the thread's own board
				if (params.board_uid) {
					const radio = form.querySelector(`[name="radio-board-selection"][value="${CSS.escape(params.board_uid)}"]`);
					if (radio) radio.closest('label').style.display = 'none';
				}
			},
			onSuccess: ({ res, postEl, form }) => {
				let data = res;
				if (typeof data === 'string') {
					try { data = JSON.parse(data); } catch (_) {}
				}
				const redirectUrl = data?.redirectUrl;

				const leaveShadow = form.querySelector('[name="leave-shadow-thread"]')?.checked ?? true;

				if (!leaveShadow) {
					// Hard move: fade out the thread then navigate
					const thread = postEl.closest('.thread');
					if (thread) {
						thread.style.transition = 'opacity 0.5s ease';
						thread.style.opacity = '0';
						setTimeout(() => {
							thread.style.display = 'none';
							if (redirectUrl) window.location.assign(redirectUrl);
						}, 500);
						return;
					}
				}

				if (redirectUrl) window.location.assign(redirectUrl);
				else window.location.reload();
			},
			onFail: ({ err }) => {
				console.error('Move thread error:', err);
				showMessage('There was an error while moving the thread.', false);
			}
		});
	});
})();
