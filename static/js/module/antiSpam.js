/**
 * antiSpam.js - "Filter post" window for the post menu
 *
 * Clones the new spam rule form shipped in the page header, fills the pattern with the post's
 * own comment, and submits it over AJAX so the board page is never left.
 *
 * Depends on: postWidget.js, windowLibrary.js, message.js
 */
(function() {
	'use strict';

	// Turn a post's comment markup into the plain text the pattern field expects: line breaks
	// kept, tags dropped, entities decoded. Mirrors what the server-side form prefill does.
	function getPostText(postEl) {
		const commentEl = postEl.querySelector('.comment:not(.belowComment)');
		if (!commentEl) return '';

		const clone = commentEl.cloneNode(true);

		// public ban messages are appended into the comment by other modules, not the post's text
		clone.querySelectorAll('.publicComment').forEach(el => el.remove());

		const holder = document.createElement('div');
		holder.innerHTML = clone.innerHTML.replace(/<br\s*\/?>/gi, '\n');

		return (holder.textContent || '').trim();
	}

	window.postWidget.registerActionHandler('filterPost', function(ctx) {
		const postEl = ctx?.post || ctx?.arrow?.closest('.post');
		if (!postEl) return;

		const postNumber = postEl.querySelector('.postnum .qu')?.textContent?.trim() || '';

		PostActionUtils.openWindow({
			templateId: '#filterPostFormTemplate',
			title: postNumber ? 'Filter post No.' + postNumber : 'Filter post',
			postEl,
			onOpen: ({ form }) => {
				if (!form) return;

				// prefill the pattern with the post's comment, leaving it editable
				const pattern = form.elements['pattern'];
				if (pattern && !pattern.value) {
					pattern.value = getPostText(postEl);
				}
			},
			onSuccess: () => {
				showMessage('Spam rule added from post No. ' + (postNumber || '?'), true);
			},
			onFail: ({ err }) => {
				showMessage(PostActionUtils.errorMessage(err, 'There was an error while adding the spam rule.'), false);
			}
		});
	});
})();
