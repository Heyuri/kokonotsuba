/**
 * dropdownMenu.js — Shared dropdown menu utility
 *
 * Provides window.DropdownMenu, a factory for creating reusable dropdown
 * menus with animated toggle arrows.  Used by postWidget and attachmentWidget.
 */
(function () {
	'use strict';

	// track every instance so the global click-outside handler can close them
	var instances = [];

	/**
	 * Create a new dropdown instance.
	 *
	 * @param {string} [extraClass]  Optional extra CSS class on the dropdown div
	 * @returns {{ open, close, isOpen, getActiveToggle, getMenu }}
	 */
	function create(extraClass) {
		var menu = document.createElement('div');
		menu.className = 'dropdownMenu' + (extraClass ? ' ' + extraClass : '');
		menu.hidden = true;
		document.body.appendChild(menu);

		var activeToggle = null;
		var subMenus = [];

		function open(toggle, buildContent) {
			// clicking the same toggle again → close
			if (activeToggle === toggle && !menu.hidden) {
				close();
				return;
			}

			// close ALL open dropdowns first (including other instances)
			closeAll();

			// let the caller populate the menu
			menu.innerHTML = '';
			subMenus = [];
			buildContent(menu, subMenus);

			// position below the toggle
			var rect = toggle.getBoundingClientRect();
			menu.style.position = 'absolute';
			menu.style.top = (window.scrollY + rect.bottom + 2) + 'px';

			menu.hidden = false; // must be visible to measure width
			var menuWidth = menu.offsetWidth;
			var left = window.scrollX + rect.left;
			var maxLeft = window.scrollX + window.innerWidth - menuWidth - 8;
			menu.style.left = Math.min(left, maxLeft) + 'px';

			toggle.classList.add('menuOpen');
			activeToggle = toggle;
		}

		function close() {
			menu.hidden = true;
			subMenus.forEach(function (sm) { if (sm) sm.hidden = true; });
			if (activeToggle) {
				activeToggle.classList.remove('menuOpen');
			}
			activeToggle = null;
		}

		function isOpen() {
			return !menu.hidden;
		}

		function getActiveToggle() {
			return activeToggle;
		}

		function getMenu() {
			return menu;
		}

		var instance = { open: open, close: close, isOpen: isOpen, getActiveToggle: getActiveToggle, getMenu: getMenu };
		instances.push(instance);
		return instance;
	}

	function closeAll() {
		instances.forEach(function (inst) { inst.close(); });
	}

	// global click-outside handler — close every dropdown when the user
	// clicks somewhere that is not inside any open menu or toggle
	document.addEventListener('click', function (e) {
		// don't interfere when a toggle arrow is clicked (open/close handles that)
		if (e.target.closest('.menuToggle')) return;

		// check if the click is inside any open dropdown
		for (var i = 0; i < instances.length; i++) {
			var m = instances[i].getMenu();
			if (!m.hidden && m.contains(e.target)) return;
		}

		// otherwise close everything
		closeAll();
	});

	window.DropdownMenu = {
		create: create,
		closeAll: closeAll
	};
})();
