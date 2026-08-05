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
 *
 * Staff who would rather not be interrupted can turn notifications off under Staff in the
 * settings window; polling stops with them.
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

	var NOTIFY_KEY = 'reportNotifs';
	var settingAdded = false;

	/** An absent settings registry means koko.js isn't there to turn anything off — leave it on. */
	function notificationsEnabled() {
		return typeof kkSetting === 'undefined' || kkSetting.get(NOTIFY_KEY);
	}

	/**
	 * Offer the toggle, once, to someone the server has just confirmed can see reports.
	 *
	 * Registered on the first answered poll rather than at load: this file is baked into static
	 * HTML during a rebuild, so an ordinary reader downloads it too and has no business being
	 * shown a staff setting. Turning notifications off does not unregister it — the switch that
	 * turns them back on has to stay.
	 */
	function addSetting() {
		if (settingAdded || typeof kkSetting === 'undefined') return;
		settingAdded = true;

		kkSetting.add({
			key: NOTIFY_KEY,
			label: 'Notify me about new reports',
			onChange: function (enabled) {
				// Turning it back on shouldn't wait out a whole poll interval.
				if (enabled) checkForReports();
			}
		}, 'Moderation');
	}

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

				// An answered poll is the server confirming this account can see reports, which
				// is the point the toggle is worth showing.
				addSetting();

				updateUnreadIndicator(data.unreadCount || 0);

				if (!data.reports || !data.reports.length) return;

				// Left unread on purpose: switching notifications back on should surface what
				// came in while they were off, rather than having silently consumed it.
				if (!notificationsEnabled()) return;

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

	// Poll while the tab is in the background too — the whole point is to reach a moderator who is
	// looking at something else. Skipped when the network is down, and when notifications are off:
	// on the live frontend there is no unread badge to keep current, so the request would be for
	// nothing. The first poll above still runs either way, so the setting gets registered.
	setInterval(function () {
		if (navigator.onLine === false) return;
		if (!notificationsEnabled() && settingAdded) return;

		checkForReports();
	}, pollSeconds * 1000);
})();
