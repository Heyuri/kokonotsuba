/**
 * attachmentDownload.js — Fetch-based download handler for attachment download buttons.
 *
 * Intercepts clicks on .downloadLink anchors and downloads the file as a blob,
 * so the browser always presents a save dialog regardless of server headers.
 * Falls back to opening the URL in a new tab if fetch fails (e.g. cross-origin
 * without CORS headers).
 */
(function () {
	'use strict';

	function handleDownloadClick(a) {
		var url = a.href;
		var filename = a.dataset.filename || '';
		fetch(url)
			.then(function (r) { return r.blob(); })
			.then(function (blob) {
				var blobUrl = URL.createObjectURL(blob);
				var dl = document.createElement('a');
				dl.href = blobUrl;
				dl.download = filename;
				dl.click();
				setTimeout(function () { URL.revokeObjectURL(blobUrl); }, 100);
			})
			.catch(function () { window.open(url, '_blank'); });
	}

	document.addEventListener('click', function (e) {
		var dl = e.target.closest('.downloadLink');
		if (!dl) return;
		e.preventDefault();
		handleDownloadClick(dl);
	});

})();
