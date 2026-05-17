(function () {
	const form = document.getElementById('anonIpForm');
	if (!form) return;

	// pollBgTask is loaded from bgTaskPoller.js

	form.addEventListener('submit', async function (e) {
		e.preventDefault();

		const btn = document.getElementById('anonIpSubmit');
		if (btn) btn.disabled = true;

		const progressEl = showMessage('Anonymization in progress', null, 0, true);

		try {
			const response = await fetch(form.action, {
				method: 'POST',
				headers: { 'X-Requested-With': 'XMLHttpRequest' },
				body: new FormData(form),
			});

			const json = await response.json();

			if (json.dispatched && json.jobId) {
				setTimeout(function () {
					window.pollBgTask(form.action, json.jobId, function (status, message) {
						dismissMessage(progressEl);
						if (status === 'completed') {
							showMessage(message || 'Anonymization completed.', true);
						} else {
							showMessage(message || 'Anonymization failed.', false);
						}
						if (btn) btn.disabled = false;
					});
				}, 1500);
			} else {
				dismissMessage(progressEl);
				showMessage(json.message || 'Failed to start anonymization.', false);
				if (btn) btn.disabled = false;
			}
		} catch (_) {
			dismissMessage(progressEl);
			showMessage('Network error while starting anonymization.', false);
			if (btn) btn.disabled = false;
		}
	});
})();



