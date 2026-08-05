/**
 * staffAlerts.js — the staff alerts widget.
 *
 * A small docked panel listing the moderation queues this staff member can reach and how many
 * entries in each they haven't read. The list is whatever the server sends: this file knows
 * nothing about reports or banners, it renders rows.
 *
 * The panel arrives one of two ways, and this file handles both: a page rendered live for staff
 * already has it, while static HTML — which cannot carry it, being written once and served to
 * everyone — ships the same blocks as <template>s for this file to fill from the module's
 * endpoint. A reader loads this file too; their request is refused and no panel appears. Either
 * way the counts are refreshed on a poll from then on.
 *
 * It wears the koko window's chrome (.window, a title bar) but is not one: it is docked by CSS
 * rather than placed by the window manager, so there is nothing to drag and nothing to close —
 * the arrow on the left of its bar collapses it to a tab against the edge instead, and that
 * choice is remembered.
 *
 * Staff who would rather not have it can turn it off under Staff in the settings window.
 *
 * Depends on: koko.js (kkSetting)
 */
(function () {
	'use strict';

	var apiMeta = document.querySelector('meta[name="staffAlertsApi"]');
	if (!apiMeta) return;

	var apiUrl = apiMeta.getAttribute('content');
	if (!apiUrl) return;

	var intervalMeta = document.querySelector('meta[name="staffAlertsInterval"]');
	var pollSeconds = intervalMeta ? parseInt(intervalMeta.getAttribute('content'), 10) : 60;
	if (!pollSeconds || pollSeconds < 10) pollSeconds = 60;

	var COLLAPSED_KEY = 'staffAlerts_collapsed';
	var SETTING_KEY = 'staffAlerts';
	var CACHE_KEY = 'staffAlerts_cache';

	var widget = null;
	var listEl = null;
	var totalEl = null;
	var toggleEl = null;
	var lastPayload = null;
	var settingAdded = false;

	function isCollapsed() {
		return localStorage.getItem(COLLAPSED_KEY) === '1';
	}

	/**
	 * The last answer the server gave, kept so a page that has to build the panel itself can
	 * draw it at once instead of after a round trip.
	 *
	 * Static HTML cannot carry the panel, and a panel that appears a moment late appears at a
	 * different size than the one that was there a moment ago — the queue names and counts are
	 * what give it its height. Drawing yesterday's answer and correcting it when this one lands
	 * is steadier than drawing nothing and growing into place. Dropped the moment the server
	 * refuses a request, so it cannot outlive the session it belongs to.
	 */
	function readCache() {
		try {
			return JSON.parse(localStorage.getItem(CACHE_KEY) || 'null');
		} catch (err) {
			return null;
		}
	}

	function writeCache(payload) {
		try {
			localStorage.setItem(CACHE_KEY, JSON.stringify(payload));
		} catch (err) { /* full or unavailable; the panel just goes back to waiting on the fetch */ }
	}

	function clearCache() {
		try {
			localStorage.removeItem(CACHE_KEY);
		} catch (err) { /* nothing to do about it */ }
	}

	/**
	 * Whether to show the widget: this staff member's choice, falling back to the server's
	 * default for staff who have not made one (JS_DEFAULT_SETTINGS in globalconfig).
	 */
	function isEnabled() {
		return typeof kkSetting === 'undefined' || kkSetting.get(SETTING_KEY);
	}

	/**
	 * Offer the setting, once, to someone the server has just confirmed is staff.
	 *
	 * Registered from the point staff-ness is known rather than from the point the widget is
	 * drawn: turning the widget off must not take the switch that turns it back on with it.
	 */
	function addSetting() {
		if (settingAdded || typeof kkSetting === 'undefined') return;
		settingAdded = true;

		kkSetting.add({
			key: SETTING_KEY,
			label: 'Show the staff alerts widget',
			onChange: function () {
				if (lastPayload) render(lastPayload);
			}
		}, 'Moderation');
	}

	/** Fold the widget down to just its arrow, or unfold it, and remember which. */
	function setCollapsed(collapsed) {
		localStorage.setItem(COLLAPSED_KEY, collapsed ? '1' : '0');

		if (!widget) return;

		widget.classList.toggle('staffAlertsCollapsed', collapsed);

		if (toggleEl) {
			// The arrow points the way the widget will move: out to the edge, or back in.
			toggleEl.textContent = collapsed ? '◀' : '▶';
			toggleEl.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
			toggleEl.title = collapsed
				? (lastPayload && lastPayload.expandTitle) || ''
				: (lastPayload && lastPayload.collapseTitle) || '';
		}
	}

	/** Everything the panel needs once it is in the DOM, however it got there. */
	function activate(el) {
		widget = el;
		listEl = el.querySelector('.staffAlertsList');
		totalEl = el.querySelector('.staffAlertsTotal');
		toggleEl = el.querySelector('.staffAlertsToggle');

		if (toggleEl) {
			toggleEl.addEventListener('click', function (ev) {
				ev.preventDefault();
				setCollapsed(!widget.classList.contains('staffAlertsCollapsed'));
			});
		}

		setCollapsed(isCollapsed());
	}

	/** Build the panel from the template, for a page that arrived without one. */
	function build(payload) {
		var widgetTemplate = document.querySelector('#staffAlertsWidgetTemplate');
		if (!widgetTemplate) return false;

		var fragment = widgetTemplate.content.cloneNode(true);
		var el = fragment.querySelector('.staffAlertsWindow');
		if (!el) return false;

		var nameEl = el.querySelector('.winname');
		if (nameEl) nameEl.textContent = payload.title || '';

		document.body.appendChild(fragment);
		activate(el);

		return true;
	}

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

	/** Draw the payload, putting the panel up or taking it down as the situation calls for it. */
	function render(payload) {
		lastPayload = payload;

		var alerts = (payload && payload.alerts) || [];

		// Nothing to watch, or this moderator has turned it off. Whether there is room for it on
		// screen is the stylesheet's business, not this file's.
		if (!alerts.length || !isEnabled()) {
			if (widget) widget.hidden = true;
			return;
		}

		if (!widget && !build(payload)) return;

		widget.hidden = false;

		if (listEl) {
			listEl.textContent = '';

			var total = 0;
			alerts.forEach(function (alert) {
				total += alert.count || 0;

				var row = buildRow(alert, payload);
				if (row) listEl.appendChild(row);
			});

			// Collapsed, the rows are hidden — the tab carries the running total so folding the
			// widget away doesn't mean losing sight of the queue.
			if (totalEl) {
				totalEl.textContent = '(' + total + ')';
				totalEl.classList.toggle('indicatorHidden', !total);
				totalEl.title = payload.unreadTitle || '';
			}
		}
	}

	function checkForAlerts() {
		fetch(apiUrl, {
			credentials: 'same-origin',
			headers: { 'X-Requested-With': 'XMLHttpRequest' }
		})
			.then(function (res) {
				// Anyone who isn't staff lands here (this file ships inside static HTML too).
				if (!res.ok) {
					var refused = new Error('HTTP ' + res.status);
					refused.refused = true;
					throw refused;
				}
				return res.json();
			})
			.then(function (data) {
				if (!data) return;

				// An answer at all means staff, so the setting is offered even when it says to
				// show nothing.
				addSetting();
				writeCache(data);
				render(data);
			})
			.catch(function (err) {
				// A refusal is an answer: this browser is no longer staff, so anything drawn
				// from the cache comes down and the cache goes with it. A transport failure
				// says nothing — leave the panel alone and try again next tick.
				if (!err || !err.refused) return;

				clearCache();
				if (widget) widget.hidden = true;
			});
	}

	// A panel already in the page is proof enough that the server rendered it for staff, and it
	// is drawn with counts as of that render — the poll below only keeps them current.
	var rendered = document.querySelector('.staffAlertsWindow');
	if (rendered) {
		addSetting();
		activate(rendered);
		if (!isEnabled()) rendered.hidden = true;
	} else {
		// Nothing rendered: put up the last answer we were given, at full size, before asking
		// for a fresh one. Usually the two agree and nothing moves.
		var cached = readCache();
		if (cached) render(cached);

		checkForAlerts();
	}

	setInterval(function () {
		if (navigator.onLine !== false) checkForAlerts();
	}, pollSeconds * 1000);
})();
