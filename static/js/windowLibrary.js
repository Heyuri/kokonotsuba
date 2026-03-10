(function() {
    window.PostActionUtils = {
        openWindow: function({
            templateId,
            title = '',
            postEl,
            onSubmit,
            onSuccess,
            onFail
        }) {
            const tmpl = document.querySelector(templateId);
            if (!tmpl) {
                console.error('Template not found:', templateId);
                return null;
            }
            const clone = tmpl.content.cloneNode(true);

            // Fill dynamic fields (keep generic)
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

            const win = new kkwmWindow(title, { x: 0, y: 0, w: 420, h: 420 });
            const body = win.div.querySelector('.windbody') || win.div;
            body.appendChild(clone);

            requestAnimationFrame(() => {
                const rectWidth = win.div.offsetWidth;
                const viewportWidth = window.innerWidth || document.documentElement.clientWidth;
                const margin = 20;
                let x = viewportWidth - rectWidth - margin;
                win.div.style.left = `${x}px`;
            });

            const form = body.querySelector('form');
            if (!form) return win;

            form.addEventListener('submit', async ev => {
                ev.preventDefault();
                const formData = new FormData(form);

                // Call user-provided onSubmit callback
                if (onSubmit) {
                    const result = await onSubmit({ form, formData, postEl, win });
                    if (result === false) return; // allow cancel
                }

                try {
                    const res = await fetch(form.action, {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin'
                    });
                    if (!res.ok) throw new Error('HTTP ' + res.status);

                    win.remove();
                    if (onSuccess) onSuccess({ res, form, postEl });
                } catch (err) {
                    console.error('Form submission error:', err);
                    if (onFail) onFail({ err, form, postEl });
                }
            });

            return win;
        }
    };
})();