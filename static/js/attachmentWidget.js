/**
 * attachmentWidget.js — Dropdown menu for attachment buttons
 *
 * Reads from a hidden .attachmentWidgetData container (rendered by PHP) to
 * build a dropdown menu. Original buttons are wrapped in <noscript> by the
 * server, so they only appear when JS is disabled.
 *
 * Supports action handlers and menu augmenters for JS-only modules.
 *
 * Depends on: dropdownMenu.js (loaded first)
 */
(function () {
	'use strict';

	var dropdown = DropdownMenu.create('attachmentMenuDropdown');

	// registry for JS-only actions, label providers, and augmenters
	var actionHandlers = new Map();
	var labelProviders = new Map();
	var menuAugmenters = [];

	/**
	 * Ensure all existing .filesize bars get a toggle arrow if needed.
	 * Called lazily when the first augmenter is registered.
	 */
	function ensureAllToggles() {
		document.querySelectorAll('.filesize').forEach(ensureToggle);
	}

	// expose api for other modules
	window.attachmentWidget = {
		registerActionHandler: function (action, cb) {
			if (typeof cb === 'function') actionHandlers.set(action, cb);
		},
		registerLabelProvider: function (action, cb) {
			if (typeof cb === 'function') labelProviders.set(action, cb);
		},
		registerMenuAugmenter: function (cb) {
			if (typeof cb === 'function') {
				var wasEmpty = menuAugmenters.length === 0;
				menuAugmenters.push(cb);
				// first augmenter → ensure all existing bars have toggle arrows
				if (wasEmpty) ensureAllToggles();
			}
		}
	};

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

			// find anchors
			var links = node.querySelectorAll('a[href]');
			links.forEach(function (a) {
				var href = a.getAttribute('href') || '';
				var action = a.dataset.action || '';

				// skip empty hrefs unless they have a data-action
				if ((!href || href === '#') && !action) return;

				items.push({
					href: a.href,
					label: a.title || a.textContent.replace(/[\[\]]/g, '').trim(),
					target: a.target || '',
					action: action
				});
			});
		});

		return items;
	}

	/**
	 * Build a context object for augmenters and action handlers.
	 */
	function buildContext(toggle) {
		var bar = toggle.closest('.filesize');
		var container = bar ? bar.closest('.attachmentContainer') : null;
		var post = toggle.closest('.post');
		return { toggle: toggle, bar: bar, container: container, post: post };
	}

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
		toggle.textContent = '\u25B6';
		bar.appendChild(toggle);
	}

	// ---- init ----

	function initBar(bar) {
		if (bar.dataset.attachmentWidget) return;
		bar.dataset.attachmentWidget = '1';

		// if there are augmenters registered, always ensure a toggle arrow
		if (menuAugmenters.length > 0) {
			ensureToggle(bar);
		}
	}

	// ---- click handling ----

	document.addEventListener('click', function (e) {
		var toggle = e.target.closest('.attachmentMenuToggle');

		if (toggle) {
			e.preventDefault();
			var bar = toggle.closest('.filesize');
			if (!bar) return;

			var ctx = buildContext(toggle);

			dropdown.open(toggle, function (menu) {
				// PHP-rendered items
				var items = collectFromBar(bar);
				items.forEach(function (item) {
					var a = document.createElement('a');
					a.href = item.href;
					if (item.target) a.target = item.target;
					if (item.action) a.dataset.action = item.action;


					// use label provider for dynamic text if available
					var label = item.label;
					if (item.action && labelProviders.has(item.action)) {
						label = labelProviders.get(item.action)(ctx) || label;
					}
					a.textContent = label;
					menu.appendChild(a);
				});

				// JS augmenter items
				menuAugmenters.forEach(function (aug) {
					var extra = aug(ctx);
					if (!extra || !extra.length) return;
					extra.forEach(function (item) {
						var a = document.createElement('a');
						a.href = item.href || '#';
						if (item.action) a.dataset.action = item.action;
						a.textContent = item.label || '';
						menu.appendChild(a);
					});
				});
			});
			return;
		}

		// menu item clicked
		var menuItem = e.target.closest('.attachmentMenuDropdown a');
		if (menuItem) {
			var action = menuItem.dataset.action;
			if (action && actionHandlers.has(action)) {
				e.preventDefault();
				var activeToggle = dropdown.getActiveToggle();
				var handlerCtx = activeToggle ? buildContext(activeToggle) : {};
				handlerCtx.action = action;
				handlerCtx.menuItem = menuItem;
				dropdown.close();
				actionHandlers.get(action)(handlerCtx);
				return;
			}

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
