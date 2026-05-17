(function () {
	const form = document.getElementById('rebuildForm');
	if (!form) return;

	// pollBgTask is loaded from bgTaskPoller.js

	form.addEventListener('submit', async function (e) {
		e.preventDefault();

		const btn = document.getElementById('rebuildSubmit');
		if (btn) btn.disabled = true;

		const progressEl = showMessage('Rebuild in progress', null, 0, true);

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
							showMessage(message || 'Boards rebuilt successfully.', true);
						} else {
							showMessage(message || 'Rebuild failed.', false);
						}
						if (btn) btn.disabled = false;
					});
				}, 1500);
			} else {
				dismissMessage(progressEl);
				showMessage(json.message || 'Failed to start rebuild.', false);
				if (btn) btn.disabled = false;
			}
		} catch (_) {
			dismissMessage(progressEl);
			showMessage('Network error while starting rebuild.', false);
			if (btn) btn.disabled = false;
		}
	});
})();
