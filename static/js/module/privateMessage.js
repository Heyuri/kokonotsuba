(function() {
	var msgs = document.querySelector('.threadMessages');
	var details = document.querySelector('.threadReplyForm');
	if (msgs) {
		msgs.scrollTop = msgs.scrollHeight;
	}
	if (details && msgs) {
		details.addEventListener('toggle', function() {
			msgs.scrollTop = msgs.scrollHeight;
		});
	}

	// browser notifications for new unread PMs
	var apiMeta = document.querySelector('meta[name="pmNotifyApi"]');
	if (!apiMeta) return;

	var apiUrl = apiMeta.getAttribute('content');
	if (!apiUrl) return;

	if (!('Notification' in window)) return;

	var ackKey = 'pm_acknowledged';

	function checkAndNotify() {
		fetch(apiUrl, { credentials: 'same-origin' })
			.then(function(res) { return res.json(); })
			.then(function(data) {
				if (!data.unreadCount || data.unreadCount <= 0) {
					localStorage.removeItem(ackKey);
					return;
				}

				// on the PM page, acknowledge current count without notifying
			// strip origin to compare only path+query (avoids protocol/host mismatches)
				if (data.url) {
					var pmPath = data.url.replace(/^https?:\/\/[^\/]+/, '');
					var currentPath = window.location.pathname + window.location.search;
					if (currentPath.indexOf(pmPath) !== -1) {
						localStorage.setItem(ackKey, String(data.unreadCount));
						return;
					}
				}

				// only notify when unread count exceeds what was last acknowledged
				var acknowledged = parseInt(localStorage.getItem(ackKey) || '0', 10);
				if (data.unreadCount <= acknowledged) return;

				function showNotification() {
					var n = new Notification(data.title, { body: data.body });
					n.onclick = function() {
						window.focus();
						window.location.href = data.url;
					};
					localStorage.setItem(ackKey, String(data.unreadCount));
				}

				if (Notification.permission === 'granted') {
					showNotification();
				} else if (Notification.permission !== 'denied') {
					document.addEventListener('click', function handler() {
						Notification.requestPermission().then(function(p) {
							if (p === 'granted') showNotification();
						});
						document.removeEventListener('click', handler);
					}, { once: true });
				}
			})
			.catch(function() { /* silently ignore fetch errors */ });
	}

	checkAndNotify();
	setInterval(checkAndNotify, 60000);
})();
