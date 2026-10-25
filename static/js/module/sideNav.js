/**
 * sideNav.js - the side navigation's hide setting, remembered collapsed groups, and its offset under
 * a pinned nav section. The nav itself is rendered and shown by the server; the inline script before
 * it applies .sideNavHidden before paint.
 *
 * Depends on: koko.js (kkSetting)
 */
(function () {
	'use strict';

	var COLLAPSED_KEY = 'sideNavCollapsed';

	if (typeof kkSetting !== 'undefined') kkSetting.add({ key: 'sidenav', label: 'Side navigation',
		onChange: function (v) { document.body.classList.toggle('sideNavHidden', !v); } }, 'Layout');

	var nav = document.querySelector('.sideNav');
	if (!nav) return;

	function readCollapsed() {
		try { return JSON.parse(localStorage.getItem(COLLAPSED_KEY)) || []; } catch (e) { return []; }
	}

	/** Same as unticking the setting; Settings brings it back. */
	var hide = nav.querySelector('.sideNavHide a');
	if (hide) hide.addEventListener('click', function (e) {
		e.preventDefault();
		try { localStorage.setItem('sidenav', 'false'); } catch (err) {}
		document.body.classList.add('sideNavHidden');
	});

	/** A second style selector mirroring the footer's, whose options the footer script already filled. */
	var styleSelect = nav.querySelector('.sideNavStyle select');
	var footerSelect = document.getElementById('styleSwitcherSelect');
	if (styleSelect && footerSelect && typeof kkstyle !== 'undefined') {
		styleSelect.innerHTML = footerSelect.innerHTML;
		styleSelect.value = footerSelect.value;
		styleSelect.addEventListener('change', function () { kkstyle.set_stylesheet(this.value, false); });

		// Follow changes made from the footer or the Settings window.
		var setStylesheet = kkstyle.set_stylesheet;
		kkstyle.set_stylesheet = function () {
			setStylesheet.apply(kkstyle, arguments);
			styleSelect.value = kkstyle.get_active_stylesheet();
		};
	} else if (styleSelect) {
		styleSelect.parentNode.parentNode.remove();
	}

	var collapsed = readCollapsed();
	nav.querySelectorAll('.sideNavGroup').forEach(function (group) {
		var id = group.getAttribute('data-group');
		if (collapsed.indexOf(id) !== -1) group.open = false;

		group.addEventListener('toggle', function () {
			var list = readCollapsed().filter(function (v) { return v !== id; });
			if (!group.open) list.push(id);
			try { localStorage.setItem(COLLAPSED_KEY, JSON.stringify(list)); } catch (e) {}
		});
	});

	/** Under Persistent navigation the nav section stays on screen, so start below it. */
	var stickyNav = document.querySelector('.stickyNav');
	function updateTop() {
		var top = stickyNav && document.body.classList.contains('persistnav') ? stickyNav.offsetHeight : 0;
		document.documentElement.style.setProperty('--side-nav-top', top + 'px');
	}

	updateTop();
	new MutationObserver(updateTop).observe(document.body, { attributes: true, attributeFilter: ['class'] });
	if (stickyNav && typeof ResizeObserver !== 'undefined') new ResizeObserver(updateTop).observe(stickyNav);
})();
