/**
 * sideNav.js - the side navigation's hide setting, board directories toggle, remembered collapsed
 * groups and style selector. The nav itself is rendered and shown by the server; the inline script
 * before it applies .sideNavHidden and .sideNavShowDirs before paint.
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

	/** Shows each board link as "/id/ - Title"; the inline script before the nav applies it before paint. */
	var dirs = nav.querySelector('.sideNavDirsToggle a');
	if (dirs) dirs.addEventListener('click', function (e) {
		e.preventDefault();
		var on = document.body.classList.toggle('sideNavShowDirs');
		try { localStorage.setItem('sideNavDirs', on ? 'true' : 'false'); } catch (err) {}
	});

	/** The style selector is drawn by the server; this switches the style and keeps it in step with the footer's. */
	var styleSelect = nav.querySelector('.sideNavStyle select');
	if (styleSelect && typeof kkstyle !== 'undefined') {
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
})();
