(function() {
	window.postWidget.registerActionHandler('editPost', function(ctx) {
		const postEl = ctx?.post || ctx?.arrow?.closest('.post');
		if (!postEl) return;


		// Open the edit window
		PostActionUtils.openWindow({
			templateId: '#postEditFormTemplate',
			title: 'Edit post',
			postEl,
			fields: ['post_number', 'postUserName', 'postEmail', 'subject', 'comment', 'postUid'], 
			onSuccess: ({ res, form, postEl }) => {
				// Parse JSON if needed
				let data = res;
				if (typeof res === 'string') {
					try { data = JSON.parse(res); } catch {}
				}

				// Update name/email and wrap posterspan if needed
				if (data && 'postUserName' in data) {
					const nameHtml = data.postUserName || '';
					const email = data.postEmail || '';
					let posterSpan = postEl.querySelector('.postername');
					if (posterSpan) {
						// Remove any previous <a> wrapper
						let parent = posterSpan.parentElement;
						if (parent && parent.tagName === 'A') {
							parent.parentNode.insertBefore(posterSpan, parent);
							parent.parentNode.removeChild(parent);
						}
						// Remove any existing .sageText span next to posterSpan
						let next = posterSpan.nextSibling;
						while (next && next.nodeType === 1 && next.classList && next.classList.contains('sageText')) {
							let toRemove = next;
							next = next.nextSibling;
							toRemove.parentNode.removeChild(toRemove);
						}

						// Set the name HTML
						posterSpan.innerHTML = nameHtml;

						// Insert sageText or wrap in <a>
						if (email) {
							// Wrap in <a href="mailto:...">
							const a = document.createElement('a');
							a.href = 'mailto:' + email.replace(/"/g, '&quot;');
							posterSpan.parentNode.insertBefore(a, posterSpan);
							a.appendChild(posterSpan);

							if (email.toLowerCase() === 'sage') {
								// Only insert sageText if it doesn't already exist
								let nextNode = a.nextSibling;
								let hasSage = false;
								while (nextNode) {
									if (nextNode.nodeType === 1 && nextNode.classList && nextNode.classList.contains('sageText')) {
										hasSage = true;
										break;
									}
									nextNode = nextNode.nextSibling;
								}
								if (!hasSage) {
									const sageSpan = document.createElement('span');
									sageSpan.className = 'sageText';
									sageSpan.textContent = ' SAGE!';
									a.parentNode.insertBefore(sageSpan, a.nextSibling);
								}
							}
						}
					}
					// Update dataset fields
					postEl.dataset.postUserName = nameHtml;
					postEl.dataset.postEmail = email;
				}

				// Update subject
				if (data && 'subject' in data) {
					const subj = data.subject || '';
					// Try .title
					let subjEl = postEl.querySelector('.title');
					if (subjEl) subjEl.innerHTML = subj;
					postEl.dataset.subject = subj;
				}

				// Update comment
				if (data && 'comment' in data) {
					let com = data.comment || '';
					// Convert newlines to <br>
					const comHtml = com.replace(/\n/g, '<br>');
					// Try .comment
					let comEl = postEl.querySelector('.comment');
					if (comEl) comEl.innerHTML = comHtml;
					postEl.dataset.comment = com;
				}

				showMessage("Edited post No. " + postEl.dataset.postNumber, true);
			},
			onFail: ({ err, form, postEl }) => {
				showMessage("There was an error while editing post.", false);
			}
		});
    });
})();