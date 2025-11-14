// ======================================================
//  BAN ACTION HANDLER
// ======================================================
window.postWidget.registerActionHandler('ban', function(ctx) {
	const postEl = ctx?.post || ctx?.arrow?.closest('.post');
	if (!postEl) return;

	// Open the form window
	PostActionUtils.openWindow({
		templateId: '#banFormTemplate',
		title: '',
		postEl,
		successMessage: "User was banned for this post!",
		failMessage: "There was an error while banning user.",
		isWarn: false
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
