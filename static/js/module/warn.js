(function() {
	window.postWidget.registerActionHandler('warn', function(ctx) {
		const postEl = ctx?.post || ctx?.arrow?.closest('.post');
		if (!postEl) return;

		PostActionUtils.openWindow({
			templateId: '#warnFormTemplate',
			title: 'Warn user',
			postEl,
			fields: ['postUid', 'post_number'],
			onSubmit: ({ form, postEl }) => {
				// Append greyed-out public warning message immediately
				const publicChk = form.querySelector('input[name="public"]');
				if (publicChk?.checked && postEl) {
					const publicMsg = form.querySelector('textarea[name="msg"], textarea[name="warnmsg"]')?.value || '';
					const commentContainer = postEl.querySelector('.comment');
					if (commentContainer) {
						const warnTmpl = document.querySelector('#publicMessage');
						if (warnTmpl) {
							const warnClone = warnTmpl.content.cloneNode(true);
							const reasonSpan = warnClone.querySelector('.reasonText');
							if (reasonSpan) reasonSpan.textContent = publicMsg;
							const warningEl = warnClone.querySelector('.warning');
							
							// grey it out during the async operation	
							if (warningEl) {
								warningEl.style.opacity = '0.5';
								warningEl.classList.add('tempWarnMsg');
							}
							
							// Remove any existing temp warning message before adding a new one
							const existingTempWarn = commentContainer.querySelector('.tempWarnMsg');
							if (existingTempWarn) existingTempWarn.remove();
							
							commentContainer.appendChild(warnClone);
						}
					}
				}
			},
			onSuccess: ({ res, form, postEl }) => {
				const tempWarningEl = postEl.querySelector('.warning.tempWarnMsg');
				if (tempWarningEl) {
					tempWarningEl.style.opacity = '1.0';
					tempWarningEl.classList.remove('tempWarnMsg');
				}
				showMessage("User was warned for post No. " + postEl.querySelector('.postnum .qu').textContent.trim(), true);
			},
			onFail: ({ err, form, postEl }) => {
				const tempWarningEl = postEl.querySelector('.warning.tempWarnMsg');
				if (tempWarningEl?.parentNode) tempWarningEl.remove();
				showMessage("There was an error while warning user.");
			}
		});
	});
})();