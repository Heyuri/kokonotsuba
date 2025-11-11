(function() {
	window.PostActionUtils = {
		openWindow: function({templateId, title='', postEl, successMessage, failMessage}) {
			const tmpl = document.querySelector(templateId);
			if (!tmpl) {
				console.error('Template not found:', templateId);
				return null;
			}
			const clone = tmpl.content.cloneNode(true);

			// If the template has a #post_number or input[name=post_uid], fill it
			const postNumSpan = clone.querySelector('#post_number');
			if (postNumSpan) {
				const quLink = postEl.querySelector('.postnum .qu');
				postNumSpan.textContent = quLink ? quLink.textContent.trim() : '';
			}

			const hiddenUidInput = clone.querySelector('input[name="post_uid"]');
			if (hiddenUidInput) {
				const checkbox = postEl.querySelector('input[type="checkbox"][name]');
				hiddenUidInput.value = checkbox ? checkbox.getAttribute('name') : '';
			}

			// If template has IP input
			const ipInput = clone.querySelector('#ip');
			if (ipInput) {
				const ipLink = postEl.querySelector('.postInfoExtra a[href*="ip_address"]');
				ipInput.value = ipLink ? ipLink.textContent.trim() : '';
			}

			// Create window
			const rect = {w: 420, h: 420};
			const d = document.documentElement;
			const x = d.clientWidth / 2 - rect.w/2;
			const y = d.clientHeight / 2 - rect.h/2;
			const win = new kkwmWindow(title, {x, y, w: rect.w, h: rect.h});
			const body = win.div.querySelector('.windbody') || win.div;
			body.appendChild(clone);

			// Handle form submission
			const form = body.querySelector('form');
			if (!form) return win;

			form.addEventListener('submit', async ev => {
				ev.preventDefault();
				const formData = new FormData(form);

				try {
					const res = await fetch(form.action, {
						method: 'POST',
						body: formData,
						credentials: 'same-origin'
					});

					if (!res.ok) throw new Error('HTTP ' + res.status);

					// If public checkbox checked and post element exists, append comment
					const publicChk = form.querySelector('input[name="public"]');
					if (publicChk?.checked) {
						const postCommentContainer = postEl.querySelector('.comment'); // adjust selector
						if (postCommentContainer) {
							const publicMsg = form.querySelector('textarea[name="banmsg"], textarea[name="msg"]')?.value || '';
							const commentNode = document.createElement('div');
							commentNode.className = 'publicComment';
							commentNode.innerHTML = publicMsg;
							postCommentContainer.appendChild(commentNode);
						}
					}

					win.remove();
					showMessage(successMessage, true);

				} catch (err) {
					console.error('Form submission error:', err);
					failMessage(failMessage, false);
				}
			});

			return win;
		}
	};
})();
