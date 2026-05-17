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

		PostActionUtils.openWindow({
			templateId: '#moveThreadFormTemplate',
			title: 'Move thread',
			postEl,
			fields: [],
			onOpen: ({ form }) => {
				// Populate the hidden thread UID field
				const uidInput = form.querySelector('[name="move-thread-uid"]');
				if (uidInput && threadUid) uidInput.value = threadUid;
			},
			onSuccess: ({ res }) => {
				let data = res;
				if (data && typeof data.json === 'function') return;
				if (typeof data === 'string') {
					try { data = JSON.parse(data); } catch (_) {}
				}
				const redirectUrl = data?.redirectUrl;
				if (redirectUrl) {
					window.location.assign(redirectUrl);
				} else {
					window.location.reload();
				}
			},
			onFail: ({ err }) => {
				console.error('Move thread error:', err);
				showMessage('There was an error while moving the thread.', false);
			}
		});
	});
})();
