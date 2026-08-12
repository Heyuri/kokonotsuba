(function() {
	window.PostActionUtils = {
		/**
		 * Build an Error for a failed response, carrying the server's own explanation.
		 *
		 * renderJsonErrorPage() sends {error, code, message}; the message is the part worth
		 * showing, so it becomes the Error's message and is also exposed as .serverMessage for
		 * callers that want to tell a real explanation apart from a bare transport failure.
		 */
		responseError: async function (res) {
			let serverMessage = '';

			try {
				const contentType = res.headers.get('content-type');
				if (contentType && contentType.includes('application/json')) {
					const body = await res.json();
					if (body && body.message) serverMessage = String(body.message);
				}
			} catch (parseError) {
				// Not JSON, or truncated — fall back to the status below.
			}

			const err = new Error(serverMessage || ('HTTP ' + res.status));
			err.status = res.status;
			err.serverMessage = serverMessage;
			return err;
		},

		/** The server's explanation if there was one, otherwise the caller's wording. */
		errorMessage: function (err, fallback) {
			return (err && err.serverMessage) ? err.serverMessage : fallback;
		},

		openWindow: function({
			templateId,
			title = '',
			postEl,
			onSubmit,
			onSuccess,
			onFail,
			onOpen,
			fields = []
		}) {
			const tmpl = document.querySelector(templateId);
			if (!tmpl) {
				console.error('Template not found:', templateId);
				return null;
			}
			const clone = tmpl.content.cloneNode(true);

			// Generic field assignment
			fields.forEach(field => {
				// Support both name and id selectors
				let sourceValue = null;
				// Try data attribute first
				if (postEl.dataset && postEl.dataset[field]) {
					sourceValue = postEl.dataset[field];
				}
				else if (field === 'post_number') {
					// Special case: extract from .postnum .qu
					const postNumEl = postEl.querySelector('.postnum .qu');
					if (postNumEl) {
						sourceValue = postNumEl.textContent.trim();
					}
				} else {
					// Try input[name] or element with id
					const input = postEl.querySelector(`[name="${field}"]`) || postEl.querySelector(`#${field}`) || postEl.querySelector(`.${field}`);
					if (input) {
						if ('value' in input) {
							sourceValue = input.value;
						} else {
							sourceValue = input.textContent.trim();
						}
					}
				}
				// Assign to clone's input[name] or element with id
				const target = clone.querySelector(`[name="${field}"]`) || clone.querySelector(`#${field}`) || clone.querySelector(`.${field}`);
				if (target && sourceValue !== null) {
					if ('value' in target) target.value = sourceValue;
					else target.textContent = sourceValue;
				}
			});

			// Use a unique internal name for each window
			let uniqueWinName;
			do {
				uniqueWinName = 'kkwm_' + Date.now() + '_' + Math.floor(Math.random() * 100000);
			} while (window.$kkwm_name && window.$kkwm_name(uniqueWinName));
			const win = new kkwmWindow(uniqueWinName, { x: 0, y: 0, w: 420, h: 420 });
			
			// Set the visible window title
			if (win.div) {
				const winNameEl = win.div.querySelector('.winname');
				if (winNameEl) winNameEl.textContent = title;
			}
			
			// Use only the new window's div
			const body = win.div && win.div.querySelector ? (win.div.querySelector('.windbody') || win.div) : null;
			
			if (!body) {
				console.error('Window body could not be created.');
				return null;
			}
			body.appendChild(clone);

			// Call onOpen if provided, after the form is in the DOM
            const form = body.querySelector('form');
            if (typeof onOpen === 'function') {
                onOpen({ form, win, postEl });
            }

			requestAnimationFrame(() => {
				const rectWidth = win.div.offsetWidth;
				const rectHeight = win.div.offsetHeight;
				const viewportWidth = window.innerWidth || document.documentElement.clientWidth;
				const viewportHeight = window.innerHeight || document.documentElement.clientHeight;
				const margin = 20;
				let x = viewportWidth - rectWidth - margin;
				let y = parseInt(win.div.style.top, 10) || 0;
				if (y + rectHeight > viewportHeight - margin) {
					y = viewportHeight - rectHeight - margin;
				}
				if (y < margin) y = margin;
				win.div.style.left = `${x}px`;
				win.div.style.top = `${y}px`;
			});

			if (!form) return win;

			form.addEventListener('submit', async ev => {
				ev.preventDefault();
				const formData = new FormData(form);

				// append submission name as value for compatability with certain forms
				if (ev.submitter && ev.submitter.name) {
					formData.append(ev.submitter.name, ev.submitter.value);
				}

				if (onSubmit) {
					const result = await onSubmit({ form, formData, postEl, win });
					if (result === false) return;
				}

				try {
					const actionUrl = new URL(form.getAttribute('action'), location.href);

					const res = await fetch(actionUrl, {
						method: 'POST',
						body: formData,
						credentials: 'same-origin',
						headers: {
        					'X-Requested-With': 'XMLHttpRequest'
    					}
					});

					// Backends answer a rejected action with renderJsonErrorPage(), whose body
					// carries the reason the reader needs to see ("You are banned", "You have
					// already reported this post"). Lift it onto the error so onFail can show it
					// instead of a status code.
					if (!res.ok) {
						throw await PostActionUtils.responseError(res);
					}

					let data = res;
					
					// Try to parse JSON if content-type is JSON
        			const contentType = res.headers.get('content-type');
       				if (contentType && contentType.includes('application/json')) {
        				data = await res.json();
        			}

					win.remove();
					if (onSuccess) onSuccess({ res: data, form, postEl });
				} catch (err) {
					console.error('Form submission error:', err);
					if (onFail) onFail({ err, form, postEl });
				}
			});

			return win;
		}
	};
})();