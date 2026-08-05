/**
 * staffNav.js — the sticky staff nav.
 *
 * The bar itself is rendered into the page by the module, on every page the server renders for a
 * signed-in staff member. This file never builds one and never asks the server for anything: it
 * takes the bar it finds, opens and closes its drop-downs, and honours the setting that hides it.
 * A page without a bar gets nothing from this file at all.
 *
 * Where the bar sits is the stylesheet's business — the menus are anchored to their own button by
 * CSS rather than positioned here.
 *
 * Depends on: koko.js (kkSetting)
 */
(function () {
	'use strict';

	var SETTING_KEY = 'staffNav';

	var bar = document.querySelector('.staffNav');
	if (!bar) return;

	var openGroup = null;

	// ─── The setting ─────────────────────────────────────────────────────────

	/**
	 * Whether to show the bar: this staff member's choice, falling back to the server's default
	 * for staff who have not made one (JS_DEFAULT_SETTINGS in globalconfig).
	 */
	function isEnabled() {
		return typeof kkSetting === 'undefined' || kkSetting.get(SETTING_KEY);
	}

	function applyVisibility(enabled) {
		bar.hidden = !enabled;
	}

	// ─── Drop-downs ──────────────────────────────────────────────────────────

	function closeGroup() {
		if (!openGroup) return;

		openGroup.menu.hidden = true;
		openGroup.toggle.setAttribute('aria-expanded', 'false');
		openGroup.item.classList.remove('staffNavGroupOpen');
		openGroup = null;
	}

	function wireGroup(item) {
		var toggle = item.querySelector('.staffNavGroupToggle');
		var menu = item.querySelector('.staffNavMenu');
		if (!toggle || !menu) return;

		var group = { item: item, toggle: toggle, menu: menu };

		toggle.addEventListener('click', function (ev) {
			ev.preventDefault();
			ev.stopPropagation();

			if (openGroup === group) {
				closeGroup();
				return;
			}

			closeGroup();

			menu.hidden = false;
			toggle.setAttribute('aria-expanded', 'true');
			item.classList.add('staffNavGroupOpen');
			openGroup = group;
		});
	}

	// ─── Start ───────────────────────────────────────────────────────────────

	Array.prototype.forEach.call(bar.querySelectorAll('.staffNavGroup'), wireGroup);

	// A bar in the page is proof enough that the server rendered it for staff, so the setting is
	// safe to offer here.
	if (typeof kkSetting !== 'undefined') {
		kkSetting.add({
			key: SETTING_KEY,
			label: 'Show the staff navigation bar',
			onChange: applyVisibility
		}, 'Staff');
	}

	applyVisibility(isEnabled());

	// A drop-down should get out of the way as soon as attention moves elsewhere.
	document.addEventListener('click', function (ev) {
		if (openGroup && !openGroup.item.contains(ev.target)) closeGroup();
	});

	document.addEventListener('keydown', function (ev) {
		if (ev.key === 'Escape') closeGroup();
	});
})();
