/**
 * reportAdmin.js — browser notifications for new post reports.
 *
 * Polls the report module for pending reports filed inside the configured window (an hour by
 * default) that this moderator has not opened yet, and raises one notification per report. The
 * server only reports what is unread; this file tells it what was actually shown, so a report
 * that never produced a notification stays unread and gets another chance next tick.
 *
 * Permission is requested once, on the first poll that finds something worth showing, and only
 * while permission is still "default" — a moderator who said no is not asked again.
 */
(function () {
	'use strict';

	var apiMeta = document.querySelector('meta[name="reportNotifyApi"]');
	if (!apiMeta) return;

	var apiUrl = apiMeta.getAttribute('content');
	if (!apiUrl) return;

	if (!('Notification' in window)) return;

	var intervalMeta = document.querySelector('meta[name="reportNotifyInterval"]');
	var pollSeconds = intervalMeta ? parseInt(intervalMeta.getAttribute('content'), 10) : 60;
	if (!pollSeconds || pollSeconds < 10) pollSeconds = 60;

	var permissionAsked = false;

	/** Keep the (n) badge in the admin nav in step with what the server just told us. */
	function updateUnreadIndicator(unreadCount) {
		var indicator = document.querySelector('.indicator-reportUnread');
		if (!indicator) return;

		indicator.textContent = ' (' + unreadCount + ')';
		indicator.classList.toggle('indicatorHidden', !unreadCount);
	}

	/** Tell the server these reports have been surfaced, so they stop coming back. */
	function markRead(markReadUrl, reportIds) {
		if (!markReadUrl || !reportIds.length) return;

		var csrfMeta = document.querySelector('meta[name="csrf-token"]');
		var body = new URLSearchParams();

		body.append('action', 'markRead');
		body.append('csrf_token', csrfMeta ? csrfMeta.content : '');
		reportIds.forEach(function (reportId) {
			body.append('reportIds[]', reportId);
		});

		fetch(markReadUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'X-Requested-With': 'XMLHttpRequest' },
			body: body
		}).catch(function () { /* left unread; the next poll will retry */ });
	}

	function notifyAbout(data) {
		var shown = [];

		data.reports.forEach(function (report) {
			var title = data.title || 'New report';
			var bodyText = 'No.' + report.postNumber
				+ (report.boardTitle ? ' on ' + report.boardTitle : '')
				+ (report.reason ? ' — ' + report.reason : '');

			var notification = new Notification(title, { body: bodyText });
			notification.onclick = function () {
				window.focus();
				window.location.href = report.url;
			};

			shown.push(report.reportId);
		});

		markRead(data.markReadUrl, shown);
	}

	function checkForReports() {
		fetch(apiUrl, {
			credentials: 'same-origin',
			headers: { 'X-Requested-With': 'XMLHttpRequest' }
		})
			.then(function (res) { return res.json(); })
			.then(function (data) {
				if (!data) return;

				updateUnreadIndicator(data.unreadCount || 0);

				if (!data.reports || !data.reports.length) return;

				if (Notification.permission === 'granted') {
					notifyAbout(data);
					return;
				}

				// Asking outright is blocked by browsers without a user gesture, so the prompt
				// rides on the next click. Only ever set up once per page.
				if (Notification.permission === 'default' && !permissionAsked) {
					permissionAsked = true;
					document.addEventListener('click', function handler() {
						document.removeEventListener('click', handler);
						Notification.requestPermission();
					}, { once: true });
				}
			})
			.catch(function () { /* transient failure; the next tick tries again */ });
	}

	checkForReports();

	// Poll while the tab is in the background too — the whole point is to reach a moderator
	// who is looking at something else. Skip ticks with no network rather than burning a fetch.
	setInterval(function () {
		if (navigator.onLine !== false) checkForReports();
	}, pollSeconds * 1000);
})();
