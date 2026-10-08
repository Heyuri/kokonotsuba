(function () {
	const form = document.getElementById('threadCacheForm');
	if (!form) return;

	const statusEl = document.getElementById('threadCacheStatus');
	const buttons = form.querySelectorAll('button[name="threadCacheMode"]');

	function setBusy(busy) {
		buttons.forEach(function (b) { b.disabled = busy; });
	}

	/** Poll the job, showing its progress, until it ends; a finished job reloads the figures. */
	function poll(jobId, attempt) {
		const url = new URL(form.action, window.location.href);
		url.searchParams.set('pollJob', jobId);

		fetch(url.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
			.then(function (r) { return r.json(); })
			.then(function (json) {
				statusEl.textContent = json.message || json.error || '';
				if (json.status === 'completed') {
					window.location.reload();
				} else if (json.status === 'failed' || json.status === 'not_found') {
					setBusy(false);
				} else {
					setTimeout(function () { poll(jobId, 0); }, 1500);
				}
			})
			.catch(function () {
				if (attempt < 10) {
					setTimeout(function () { poll(jobId, attempt + 1); }, 3000);
				} else {
					statusEl.textContent = 'Lost contact with the server while checking the job.';
					setBusy(false);
				}
			});
	}

	form.addEventListener('submit', async function (e) {
		const submitter = e.submitter;
		if (!submitter || submitter.name !== 'threadCacheMode') return;

		if (submitter.value === 'clear' && !window.confirm(form.dataset.confirmClear)) {
			e.preventDefault();
			return;
		}

		e.preventDefault();
		const body = new FormData(form);
		body.set('threadCacheMode', submitter.value);
		setBusy(true);

		try {
			const response = await fetch(form.action, {
				method: 'POST',
				headers: { 'X-Requested-With': 'XMLHttpRequest' },
				body: body,
			});
			const json = await response.json();
			statusEl.textContent = json.message || '';

			if (json.dispatched && json.jobId) {
				poll(json.jobId, 0);
			} else {
				setBusy(false);
			}
		} catch (_) {
			statusEl.textContent = 'Network error while starting the job.';
			setBusy(false);
		}
	});
})();
