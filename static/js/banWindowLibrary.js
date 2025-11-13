(function() {
	window.PostActionUtils = {
		openWindow: function({templateId, title='', postEl, successMessage, failMessage, isWarn=false}) {
			const tmpl = document.querySelector(templateId);
			if (!tmpl) {
				console.error('Template not found:', templateId);
				return null;
			}
			const clone = tmpl.content.cloneNode(true);

			// Fill dynamic fields
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
			const ipInput = clone.querySelector('#ip');
			if (ipInput) {
				const ipLink = postEl.querySelector('.postInfoExtra a[href*="ip_address"]');
				ipInput.value = ipLink ? ipLink.textContent.trim() : '';
			}

			// Create window
			const rect = {w: 420, h: 420};
			const d = document.documentElement;
			const x = d.clientWidth / 2 - rect.w / 2;
			const y = d.clientHeight / 2 - rect.h / 2;
			const win = new kkwmWindow(title, {x, y, w: rect.w, h: rect.h});
			const body = win.div.querySelector('.windbody') || win.div;
			body.appendChild(clone);

			const form = body.querySelector('form');
			if (!form) return win;

			form.addEventListener('submit', async ev => {
				ev.preventDefault();
				const formData = new FormData(form);

				// ---- Create and append the greyed-out message immediately ----
				let tempMsgEl = null;
				const publicChk = form.querySelector('input[name="public"]');
				if (publicChk?.checked && postEl) {
					const publicMsg = form.querySelector('textarea[name="banmsg"], textarea[name="msg"]')?.value || '';

					if (isWarn) {
						// For warn, use the #publicMessage template
						const warnTmpl = document.querySelector('#publicMessage');
						if (warnTmpl) {
							const warnClone = warnTmpl.content.cloneNode(true);
							const reasonSpan = warnClone.querySelector('.reasonText');
							if (reasonSpan) reasonSpan.textContent = publicMsg;

							const warningEl = warnClone.querySelector('.warning');
							if (warningEl) warningEl.style.opacity = '0.5';

							const commentContainer = postEl.querySelector('.comment');
							if (commentContainer) {
								commentContainer.appendChild(warnClone);
								tempMsgEl = commentContainer.lastElementChild;
							}
						}
					}
					else {
						// For ban, simple div message
						const commentContainer = postEl.querySelector('.comment');
						if (commentContainer) {
							const commentNode = document.createElement('div');
							commentNode.className = 'publicComment';
							commentNode.innerHTML = publicMsg;
							commentNode.style.opacity = '0.5';
							commentContainer.appendChild(commentNode);
							tempMsgEl = commentNode;
						}
					}
				}

				try {
					const res = await fetch(form.action, {
						method: 'POST',
						body: formData,
						credentials: 'same-origin'
					});

					if (!res.ok) throw new Error('HTTP ' + res.status);

					// Success → make message fully visible
					if (tempMsgEl) tempMsgEl.style.opacity = '1.0';

					win.remove();
					showMessage(successMessage, true);
				} catch (err) {
					console.error('Form submission error:', err);
					// Remove greyed-out message if error
					if (tempMsgEl?.parentNode) tempMsgEl.remove();
					showMessage(failMessage, false);
				}
			});

			return win;
		}
	};
})();
