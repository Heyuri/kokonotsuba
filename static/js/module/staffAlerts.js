/**
 * staffAlerts.js — the staff alerts widget.
 *
 * The panel's contents are rendered into the page by the module (or built from the template and
 * the endpoint on a static page, which cannot carry them). This file puts them in an ordinary
 * koko window, so it drags, stacks, minimises and remembers where it was put like any other.
 *
 * Depends on: koko.js (kkwmWindow, kkSetting)
 */
(function () {
	'use strict';

	var apiMeta = document.querySelector('meta[name="staffAlertsApi"]');
	if (!apiMeta) return;

	var apiUrl = apiMeta.getAttribute('content');
	if (!apiUrl) return;

	if (typeof kkwmWindow !== 'function') return;

	var intervalMeta = document.querySelector('meta[name="staffAlertsInterval"]');
	var pollSeconds = intervalMeta ? parseInt(intervalMeta.getAttribute('content'), 10) : 60;
	if (!pollSeconds || pollSeconds < 10) pollSeconds = 60;

	var SETTING_KEY = 'staffAlerts';
	var EDGE_MARGIN = 12;

	var content = null;
	var win = null;
	var totalEl = null;
	var settingAdded = false;

	function isEnabled() {
		return typeof kkSetting === 'undefined' || kkSetting.get(SETTING_KEY);
	}

	/**
	 * Sit against the right edge, once the window has a width to measure.
	 *
	 * The manager places windows before they are in the page, so the width it clamps against is
	 * zero and a right-hand position overshoots. Skipped entirely once it has a position of its
	 * own to remember.
	 */
	function placeByRightEdge(name) {
		if (localStorage.getItem('kkwm_pos_' + name)) return;

		requestAnimationFrame(function () {
			if (!win) return;

			var box = win.div.getBoundingClientRect();
			win.move(document.documentElement.clientWidth - box.width - EDGE_MARGIN, box.top);
		});
	}

	function open() {
		if (win || !content) return;

		var title = content.getAttribute('data-title') || 'Alerts';

		win = new kkwmWindow(title, { y: Math.max(0, Math.floor(document.documentElement.clientHeight / 2) - 80), w: 220, h: 160 });
		if (!win || !win.div) {
			win = null;
			return;
		}

		win.div.classList.add('staffAlertsWindow');

		// The running total goes on the title bar, where a minimised window still shows it.
		var winbar = win.div.querySelector('.winbar');
		totalEl = content.querySelector('.staffAlertsTotal');
		if (winbar && totalEl) winbar.insertBefore(totalEl, winbar.firstChild);

		win.div.appendChild(content);
		content.hidden = false;

		placeByRightEdge(title);
	}

	function close() {
		if (!win) return;

		// Keep the contents: the setting can put them back without another fetch.
		if (content && content.parentNode) {
			content.hidden = true;
			document.body.appendChild(content);
		}

		win.remove();
		win = null;
		totalEl = null;
	}

	function applySetting() {
		if (isEnabled()) open();
		else close();

		announce();
	}

	/**
	 * Say where the panel stands, for anything that docks a control of its own in it (the
	 * [Moderate] button). A static page builds the panel from a fetch, so it can arrive late, and
	 * the setting can put it away again at any point.
	 */
	function announce() {
		document.dispatchEvent(new CustomEvent('staffAlerts:ready', {
			detail: { widget: content, visible: !!content && !content.hidden }
		}));
	}

	/** Offered once there are contents to show, which means the server called this reader staff. */
	function addSetting() {
		if (settingAdded || typeof kkSetting === 'undefined') return;
		settingAdded = true;

		kkSetting.add({
			key: SETTING_KEY,
			label: 'Show the staff alerts widget',
			onChange: applySetting
		}, 'Moderation');
	}

	// ─── Rows ────────────────────────────────────────────────────────────────

	function buildRow(alert, payload) {
		var rowTemplate = document.querySelector('#staffAlertsRowTemplate');
		if (!rowTemplate) return null;

		var row = rowTemplate.content.cloneNode(true);

		var link = row.querySelector('.staffAlertsLink');
		if (link) {
			link.href = alert.url || '#';
			link.title = alert.title || '';
		}

		var label = row.querySelector('.staffAlertsLabel');
		if (label) label.textContent = alert.label || alert.key || '';

		var count = row.querySelector('.staffAlertsCount');
		if (count) {
			count.textContent = '(' + (alert.count || 0) + ')';
			count.classList.toggle('indicatorHidden', !alert.count);
			count.title = payload.unreadTitle || '';
		}

		var rowEl = row.querySelector('.staffAlertsRow');
		if (rowEl) rowEl.classList.toggle('staffAlertsRowUnread', !!alert.count);

		return row;
	}

	/** Redraw the rows from a fresh payload. */
	function fill(payload) {
		var alerts = (payload && payload.alerts) || [];
		var list = content && content.querySelector('.staffAlertsList');
		if (!list) return;

		list.textContent = '';

		var total = 0;
		alerts.forEach(function (alert) {
			total += alert.count || 0;

			var row = buildRow(alert, payload);
			if (row) list.appendChild(row);
		});

		if (totalEl) {
			totalEl.textContent = '(' + total + ')';
			totalEl.classList.toggle('indicatorHidden', !total);
			totalEl.title = payload.unreadTitle || '';
		}
	}

	/** Build the panel from the template, for a static page that arrived without one. */
	function buildContent(payload) {
		var widgetTemplate = document.querySelector('#staffAlertsWidgetTemplate');
		if (!widgetTemplate) return false;

		var fragment = widgetTemplate.content.cloneNode(true);
		content = fragment.querySelector('.staffAlertsWidget');
		if (!content) return false;

		content.setAttribute('data-title', payload.title || '');
		document.body.appendChild(fragment);

		return true;
	}

	function checkForAlerts() {
		fetch(apiUrl, {
			credentials: 'same-origin',
			headers: { 'X-Requested-With': 'XMLHttpRequest' }
		})
			.then(function (res) {
				// Anyone who isn't staff lands here (this file ships inside static HTML too).
				if (!res.ok) throw new Error('HTTP ' + res.status);
				return res.json();
			})
			.then(function (data) {
				if (!data || !data.alerts || !data.alerts.length) return;

				if (!content && !buildContent(data)) return;

				addSetting();
				applySetting();
				fill(data);
			})
			.catch(function () { /* transient failure; the next tick tries again */ });
	}

	// ─── Start ───────────────────────────────────────────────────────────────

	content = document.querySelector('.staffAlertsWidget');

	if (content) {
		addSetting();
		applySetting();
	} else {
		checkForAlerts();
	}

	setInterval(function () {
		if (navigator.onLine !== false) checkForAlerts();
	}, pollSeconds * 1000);
})();
