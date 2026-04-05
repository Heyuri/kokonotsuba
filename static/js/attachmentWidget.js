/**
 * attachmentWidget.js — Dropdown menu for attachment buttons
 *
 * Reads from a hidden .attachmentWidgetData container (rendered by PHP) to
 * build a dropdown menu. Original buttons are wrapped in <noscript> by the
 * server, so they only appear when JS is disabled.
 *
 * Depends on: dropdownMenu.js (loaded first)
 */
(function () {
	'use strict';

	var dropdown = DropdownMenu.create('attachmentMenuDropdown');

	// selectors for actionable nodes inside .attachmentWidgetData
	var BUTTON_SELECTOR = '.indicator, .attachmentButton, .warning';

	/**
	 * Collect menu items from the hidden .attachmentWidgetData container.
	 */
	function collectFromBar(bar) {
		var dataContainer = bar.querySelector('.attachmentWidgetData');
		if (!dataContainer) return [];

		var items = [];
		var nodes = dataContainer.querySelectorAll(BUTTON_SELECTOR);

		nodes.forEach(function (node) {
			// skip if hidden by the PHP layer
			if (node.classList.contains('indicatorHidden')) return;
			if (node.closest('.indicatorHidden')) return;

			// skip nested duplicates (e.g. .attachmentButton inside .indicator)
			if (node.parentElement && node.parentElement.closest(BUTTON_SELECTOR)
				&& node.parentElement.closest('.attachmentWidgetData') === dataContainer) {
				return;
			}

			// find anchors with real hrefs
			var links = node.querySelectorAll('a[href]');
			links.forEach(function (a) {
				var href = a.getAttribute('href') || '';
				if (!href || href === '#') return;

				items.push({
					href: a.href,
					label: a.title || a.textContent.replace(/[\[\]]/g, '').trim(),
					target: a.target || ''
				});
			});
		});

		return items;
	}

	// ---- init ----

	function initBar(bar) {
		if (bar.dataset.attachmentWidget) return;
		bar.dataset.attachmentWidget = '1';

		var items = collectFromBar(bar);
		if (!items.length) return;

		// create toggle arrow (shared .menuToggle class for animation)
		var toggle = document.createElement('a');
		toggle.className = 'menuToggle attachmentMenuToggle';
		toggle.setAttribute('role', 'button');
		toggle.setAttribute('aria-label', 'Attachment menu');
		toggle.textContent = '\u25B6'; // ▶

		// insert right after file properties, or at end of bar
		var fileProps = bar.querySelector('.fileProperties');
		if (fileProps && fileProps.nextSibling) {
			bar.insertBefore(toggle, fileProps.nextSibling);
		} else {
			bar.appendChild(toggle);
		}
	}

	// ---- click handling ----

	document.addEventListener('click', function (e) {
		var toggle = e.target.closest('.attachmentMenuToggle');

		if (toggle) {
			e.preventDefault();
			var bar = toggle.closest('.filesize');
			if (!bar) return;

			dropdown.open(toggle, function (menu) {
				var items = collectFromBar(bar);
				items.forEach(function (item) {
					var a = document.createElement('a');
					a.href = item.href;
					if (item.target) a.target = item.target;
					a.rel = 'nofollow';
					a.textContent = item.label;
					menu.appendChild(a);
				});
			});
			return;
		}

		// menu item clicked — let it navigate, then close
		var menuItem = e.target.closest('.attachmentMenuDropdown a');
		if (menuItem) {
			dropdown.close();
			return;
		}
	});

	// ---- boot ----

	document.querySelectorAll('.filesize').forEach(initBar);

	// observe for dynamically-inserted content (auto-update, inline expansion)
	if (typeof MutationObserver !== 'undefined') {
		new MutationObserver(function (mutations) {
			mutations.forEach(function (m) {
				m.addedNodes.forEach(function (node) {
					if (node.nodeType !== 1) return;
					if (node.classList && node.classList.contains('filesize')) {
						initBar(node);
					}
					var bars = node.querySelectorAll ? node.querySelectorAll('.filesize') : [];
					bars.forEach(initBar);
				});
			});
		}).observe(document.body, { childList: true, subtree: true });
	}

})();
