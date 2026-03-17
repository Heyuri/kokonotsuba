(function() {
	window.PostActionUtils = {
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

			const win = new kkwmWindow(title, { x: 0, y: 0, w: 420, h: 420 });
			const body = win.div.querySelector('.windbody') || win.div;
			
			body.appendChild(clone);

			// Call onOpen if provided, after the form is in the DOM
            const form = body.querySelector('form');
            if (typeof onOpen === 'function') {
                onOpen({ form, win, postEl });
            }

			requestAnimationFrame(() => {
				const rectWidth = win.div.offsetWidth;
				const viewportWidth = window.innerWidth || document.documentElement.clientWidth;
				const margin = 20;
				let x = viewportWidth - rectWidth - margin;
				win.div.style.left = `${x}px`;
			});

			if (!form) return win;

			form.addEventListener('submit', async ev => {
				ev.preventDefault();
				const formData = new FormData(form);

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

					if (!res.ok) throw new Error('HTTP ' + res.status);

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