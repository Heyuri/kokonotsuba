/**
 * attachmentWidget.js — Dropdown menu for attachment buttons
 *
 * Same menu as postWidget.js (see widgetMenu.js), built from the hidden .attachmentWidgetData
 * container instead, and styled smaller through .attachmentMenuDropdown. The original buttons are
 * wrapped in <noscript> by the server, so they only appear when JS is disabled.
 *
 * Depends on: dropdownMenu.js, widgetMenu.js (loaded first)
 */
(function () {
	'use strict';

	/**
	 * Ensure an attachment has a toggle arrow, creating one if needed
	 * (for attachments that have no PHP-rendered buttons).
	 */
	function ensureToggle(bar) {
		var existing = bar.querySelector('.attachmentMenuToggle');
		if (existing) return;

		var toggle = document.createElement('a');
		toggle.className = 'menuToggle attachmentMenuToggle';
		toggle.setAttribute('role', 'button');
		toggle.setAttribute('aria-label', 'Attachment menu');
		toggle.textContent = '▶';
		bar.appendChild(toggle);
	}

	window.attachmentWidget = WidgetMenu.create({
		menuClass: 'attachmentMenuDropdown',
		submenuClass: 'attachmentMenuSubmenu',
		toggleSelector: '.attachmentMenuToggle',

		// entries without a handler stay plain links, so target and new-tab clicks keep working
		nativeLinks: true,

		// PHP pre-renders each widget as an <a> tag with data-action and data-label
		collectItems: function (toggle) {
			var bar = toggle.closest('.filesize');
			var dataContainer = bar ? bar.querySelector('.attachmentWidgetData') : null;
			if (!dataContainer) return [];

			var items = [];

			dataContainer.querySelectorAll('a').forEach(function (a) {
				var href = a.getAttribute('href') || '';
				var action = a.dataset.action || '';

				if (!href && !action) return;

				items.push({
					href: a.href,
					label: a.dataset.label || a.textContent.trim(),
					target: a.target || '',
					action: action,
					subMenu: a.dataset.submenu,
					params: WidgetMenu.collectParams(a)
				});
			});

			return items;
		},

		buildContext: function (toggle) {
			var bar = toggle.closest('.filesize');
			return {
				toggle: toggle,
				bar: bar,
				container: bar ? bar.closest('.attachmentContainer') : null,
				post: toggle.closest('.post')
			};
		},

		// attachments with no PHP-rendered buttons have no arrow of their own until a JS-only
		// module wants one, so the first augmenter brings them all out
		onAugmenter: function (isFirst) {
			if (isFirst) document.querySelectorAll('.filesize').forEach(ensureToggle);
		}
	});

	// ---- boot ----

	function initBar(bar) {
		if (bar.dataset.attachmentWidget) return;
		bar.dataset.attachmentWidget = '1';

		// if there are augmenters registered, always ensure a toggle arrow
		if (window.attachmentWidget.hasAugmenters()) {
			ensureToggle(bar);
		}
	}

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
