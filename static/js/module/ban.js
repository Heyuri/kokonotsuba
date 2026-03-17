(function() {
	// ======================================================
	//  BAN ACTION HANDLER
	// ======================================================
	window.postWidget.registerActionHandler('ban', function(ctx) {
		const postEl = ctx?.post || ctx?.arrow?.closest('.post');
		if (!postEl) return;

		PostActionUtils.openWindow({
			templateId: '#banFormTemplate',
			title: 'Ban user',
			postEl,
			fields: ['postUid', 'post_number', 'ipAddress'],
			onSubmit: ({ form, postEl }) => {
				// Append greyed-out public ban message immediately
				const publicChk = form.querySelector('input[name="public"]');
				if (publicChk?.checked && postEl) {
					const publicMsg = form.querySelector('textarea[name="banmsg"], textarea[name="msg"]')?.value || '';
					const commentContainer = postEl.querySelector('.comment');
					if (commentContainer) {
						const commentNode = document.createElement('div');
						commentNode.className = 'publicComment tempBanMsg';
						commentNode.innerHTML = publicMsg;
						commentNode.style.opacity = '0.5';
						commentContainer.appendChild(commentNode);
					}
				}
			},
			onSuccess: ({ res, form, postEl }) => {
				// Refetch the temp ban message and make it fully visible
				const tempMsgEl = postEl.querySelector('.publicComment.tempBanMsg');
				if (tempMsgEl) tempMsgEl.style.opacity = '1.0';
				showMessage("User was banned for post No. " + postEl.querySelector('.postnum .qu').textContent.trim(), true);
			},
			onFail: ({ err, form, postEl }) => {
				// Refetch and remove the temp ban message if error
				const tempMsgEl = postEl.querySelector('.publicComment.tempBanMsg');
				if (tempMsgEl?.parentNode) tempMsgEl.remove();
				showMessage("There was an error while banning user.");
			}
		});
	});
	// ======================================================
	//  EVENT DELEGATION: TOGGLE PUBLIC BAN MESSAGE FIELD
	// ======================================================
	document.addEventListener('change', function(e) {
		if (e.target && e.target.id === 'public') {
			const form = e.target.closest('form');
			if (!form) return;

			const textarea = form.querySelector('#banmsg');
			if (textarea) textarea.disabled = !e.target.checked;
		}
	});

	// ======================================================
	//  MUTATIONOBSERVER: INITIALIZE WHEN BAN FORM APPEARS
	// ======================================================
	const observer = new MutationObserver(mutations => {
		for (const m of mutations) {
			for (const node of m.addedNodes) {
				if (node.nodeType !== 1) continue;

				const form = node.matches('form') ? node : node.querySelector('form');
				if (!form) continue;

				// Only apply to ban forms that contain #banmsg and #public
				if (!form.querySelector('#banmsg') || !form.querySelector('#public')) continue;

				const checkbox = form.querySelector('#public');
				const textarea = form.querySelector('#banmsg');

				textarea.disabled = !checkbox.checked;
			}
		}
	});

	observer.observe(document.body, { childList: true, subtree: true });
})();