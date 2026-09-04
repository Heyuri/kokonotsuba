(function () {
	// ======================================================
	//  Keeps the domain-wide marker in step with the server.
	//
	//  The marker is a cookie on the registrable domain (planted from yay.html in a hidden
	//  frame), so it is readable from every board on the domain rather than just this one. This
	//  script asks the server whether the browser is banned and plants or clears it to match, so
	//  the marker cannot go stale and stay stale once a ban is lifted or runs out.
	//
	//  It deliberately shows nothing and touches nothing on the page. A visitor learns they are
	//  banned by being stopped, on the ban page, and not a moment sooner - a notice above the
	//  post form would only tell somebody which of their addresses or browsers is still clean.
	//
	//  Nothing here decides anything either: the ban lives in the database and is enforced there
	//  when the post is submitted.
	// ======================================================
	const statusMeta = document.querySelector('meta[name="statusUrl"]');
	if (!statusMeta || !statusMeta.content) return;

	const statusUrl = statusMeta.content;
	const markerMeta = document.querySelector('meta[name="markerUrl"]');
	const cookieMeta = document.querySelector('meta[name="markerCookie"]');

	const markerUrl = markerMeta ? markerMeta.content : '';
	const markerName = cookieMeta ? cookieMeta.content : '';

	// Nothing to keep in step without somewhere to keep it.
	if (!markerUrl || !markerName) return;

	function hasMarker() {
		const escaped = markerName.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
		return new RegExp('(?:^|; )' + escaped + '=').test(document.cookie);
	}

	// Planted and cleared from a frame on the marker page's own origin, which is what puts the
	// cookie on the domain every board shares rather than on this one board.
	function markerFrame(clear) {
		const url = markerUrl +
			(markerUrl.indexOf('?') === -1 ? '?' : '&') +
			'c=' + encodeURIComponent(markerName) + (clear ? '&clear=1' : '');

		const frame = document.createElement('iframe');
		frame.src = url;
		frame.style.display = 'none';
		frame.setAttribute('aria-hidden', 'true');
		frame.setAttribute('tabindex', '-1');
		frame.title = '';

		document.body.appendChild(frame);
	}

	function check() {
		const marked = hasMarker();

		// The token mirror may still be putting the cookie back; asking before it has settled
		// would be asking about the wrong browser.
		const settled = window.kokoPrefs || Promise.resolve('');

		settled.catch(function () {}).then(function () {
			return fetch(statusUrl, {
				credentials: 'same-origin',
				headers: { 'X-Requested-With': 'XMLHttpRequest' }
			});
		}).then(function (response) {
			return response && response.ok ? response.json() : null;
		}).then(function (data) {
			// No answer at all: leave the marker as it stands rather than guessing.
			if (!data) return;

			if (data.banned) {
				if (!marked) markerFrame(false);
			} else if (marked) {
				markerFrame(true);
			}
		}).catch(function () {});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', check);
	} else {
		check();
	}
})();
