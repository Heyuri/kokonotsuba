/**
 * Polls a background task status endpoint until a terminal state is reached.
 *
 * @param {string}   baseUrl  URL of the page that hosts the poll endpoint
 * @param {string}   jobId    Job ID returned by the dispatch endpoint
 * @param {Function} onDone   Called with (status, message) on terminal state
 * @param {number}   [attempt] Internal retry counter — do not pass on first call
 */
if (typeof window.pollBgTask === 'undefined') {
	window.pollBgTask = function pollBgTask(baseUrl, jobId, onDone, attempt) {
		attempt = attempt || 0;

		const url = new URL(baseUrl, window.location.href);
		url.searchParams.set('pollJob', jobId);

		fetch(url.toString(), {
			headers: { 'X-Requested-With': 'XMLHttpRequest' },
		})
		.then(function (r) { return r.json(); })
		.then(function (json) {
			const status = json.status;
			if (status === 'completed' || status === 'failed' || status === 'not_found') {
				onDone(status, json.message || '');
			} else {
				const delay = Math.min(1500 * Math.pow(1.4, attempt), 12000);
				setTimeout(function () {
					window.pollBgTask(baseUrl, jobId, onDone, attempt + 1);
				}, delay);
			}
		})
		.catch(function () {
			if (attempt < 12) {
				setTimeout(function () {
					window.pollBgTask(baseUrl, jobId, onDone, attempt + 1);
				}, 3000);
			} else {
				onDone('failed', 'Lost contact with server while checking task status.');
			}
		});
	};
}
