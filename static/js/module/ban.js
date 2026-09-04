(function() {
	// ======================================================
	//  BAN ACTION HANDLER
	// ======================================================
	// Only board pages carry the post widget. The admin ban pages load this script for the
	// form wiring below, so the handler is registered only when there is something to
	// register it on - reaching through an absent postWidget would throw here and leave the
	// rest of the file, the ban form included, unwired.
	if (window.postWidget && typeof window.postWidget.registerActionHandler === 'function') {
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
					showMessage("There was an error while banning user.", false);
				}
			});
		});
	}

	// ======================================================
	//  BAN FORM FIELD WIRING
	// ======================================================
	function syncSelectAll(form) {
		const selectAll = form.querySelector('#banSelectAllCheckpoints');
		if (!selectAll) return;

		const boxes = Array.from(form.querySelectorAll('.banCheckpoint'));
		const checked = boxes.filter(box => box.checked).length;

		selectAll.checked = boxes.length > 0 && checked === boxes.length;
		selectAll.indeterminate = checked > 0 && checked < boxes.length;
	}

	// A permanent ban has no duration to type, so the field goes away rather than being ignored.
	function syncPermanent(form) {
		const permanent = form.querySelector('#banPermanent');
		const duration = form.querySelector('#duration');
		if (!permanent || !duration) return;

		duration.disabled = permanent.checked;
	}

	/**
	 * The browser tie is read off the post being banned, so the box is only usable with one.
	 *
	 * The window's form is cloned from a template rendered with no post, which arrives disabled;
	 * openWindow fills the post uid in before inserting it, so the state is derived from that
	 * rather than left as the template baked it.
	 */
	function syncTieToken(form) {
		const tieToken = form.querySelector('#tieToken');
		if (!tieToken) return;

		const postUid = form.querySelector('[name="postUid"]')?.value.trim() || '';

		tieToken.disabled = postUid === '' || postUid === '0';
	}

	function initBanForm(form) {
		const publicChk = form.querySelector('#public');
		const banmsg = form.querySelector('#banmsg');
		if (publicChk && banmsg) banmsg.disabled = !publicChk.checked;

		syncSelectAll(form);
		syncPermanent(form);
		syncTieToken(form);
	}

	document.addEventListener('change', function(e) {
		const target = e.target;
		if (!target) return;

		const form = target.closest('form');
		if (!form) return;

		if (target.id === 'public') {
			const textarea = form.querySelector('#banmsg');
			if (textarea) textarea.disabled = !target.checked;
			return;
		}

		if (target.id === 'banSelectAllCheckpoints') {
			form.querySelectorAll('.banCheckpoint').forEach(box => { box.checked = target.checked; });
			target.indeterminate = false;
			return;
		}

		if (target.classList.contains('banCheckpoint')) {
			syncSelectAll(form);
			return;
		}

		if (target.id === 'banPermanent') syncPermanent(form);
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

				initBanForm(form);
			}
		}
	});

	observer.observe(document.body, { childList: true, subtree: true });

	// The ban page's own form is in the document already, so it never trips the observer.
	document.addEventListener('DOMContentLoaded', function() {
		const form = document.querySelector('#banForm');
		if (form) initBanForm(form);
	});
})();