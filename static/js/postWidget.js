/**
 * postWidget.js — Dropdown menu for post actions
 *
 * Builds a per-post dropdown from hidden widget refs injected by PHP modules.
 * Supports action handlers, label providers, menu augmenters, and submenus.
 *
 * Depends on: dropdownMenu.js (loaded first)
 */
(function () {
	'use strict';

	var dropdown = DropdownMenu.create('postMenuDropdown');

	// registry for javascript-only actions
	var actionHandlers = new Map();
	var labelProviders = new Map();
	var menuAugmenters = [];

	// expose api for other modules
	window.postWidget = {
		registerActionHandler: function (action, cb) {
			if (typeof cb === 'function') actionHandlers.set(action, cb);
		},
		registerLabelProvider: function (action, cb) {
			if (typeof cb === 'function') labelProviders.set(action, cb);
		},
		registerMenuAugmenter: function (cb) {
			if (typeof cb === 'function') menuAugmenters.push(cb);
		}
	};

	// ---- click delegation ----

	document.addEventListener('click', function (e) {
		// --- toggle arrow ---
		var arrow = e.target.closest('.postMenu .menuToggle');
		if (arrow) {
			e.preventDefault();

			dropdown.open(arrow, function (menu, subMenus) {
				buildMenuContent(menu, subMenus, arrow);
			});
			return;
		}

		// --- menu item ---
		var menuItem = e.target.closest('.postMenuDropdown a');
		if (menuItem) {
			e.preventDefault();

			// submenu header — do nothing
			if (menuItem.dataset && menuItem.dataset.submenuToggle === '1') return;

			var action = menuItem.dataset.action || '';
			var hasHandler = actionHandlers.has(action);

			if (!hasHandler) {
				// treat as normal link
				var url = menuItem.href;
				if (url && url !== '#') {
					if (e.metaKey || e.ctrlKey || e.shiftKey || e.button === 1) {
						window.open(url, '_blank');
					} else {
						window.location.assign(url);
					}
				}
				dropdown.close();
				return;
			}

			handleWidgetAction(action, menuItem.href, menuItem);
			dropdown.close();
			return;
		}
	});

	// ---- menu content builder ----

	function buildMenuContent(menu, subMenus, arrow) {
		var post = arrow.closest('.post');
		var refs = post.querySelectorAll('.widgetRefs a');

		var rootItems = [];
		var groups = {};

		refs.forEach(function (ref) {
			var subName = (ref.dataset.submenu || '').trim();
			var itemData = {
				href: ref.href,
				action: ref.dataset.action,
				label: ref.dataset.label
			};

			if (subName) {
				if (!groups[subName]) groups[subName] = [];
				groups[subName].push(itemData);
			} else {
				rootItems.push(itemData);
			}
		});

		// let external modules inject extra items
		menuAugmenters.forEach(function (aug) {
			try {
				var extra = aug({ post: post, arrow: arrow });
				if (Array.isArray(extra)) {
					extra.forEach(function (item) {
						if (!item || (!item.label && !item.action)) return;
						var subName = (item.subMenu || '').trim();
						var data = {
							href: item.href || '#',
							action: item.action || '',
							label: item.label || ''
						};
						if (subName) {
							if (!groups[subName]) groups[subName] = [];
							groups[subName].push(data);
						} else {
							rootItems.push(data);
						}
					});
				}
			} catch (err) {
				console.error('menu augmenter error', err);
			}
		});

		// helper
		function buildMenuItem(item) {
			var a = document.createElement('a');
			a.href = item.href;
			a.dataset.action = item.action;

			var label = item.label;
			var lp = labelProviders.get(a.dataset.action);
			if (lp) {
				try {
					var custom = lp({
						action: a.dataset.action,
						url: a.href,
						arrow: arrow,
						post: post
					});
					if (typeof custom === 'string' && custom.length) label = custom;
				} catch (err) {
					console.error('label provider error', err);
				}
			}

			a.textContent = label;
			return a;
		}

		// root items
		rootItems.forEach(function (item) {
			menu.appendChild(buildMenuItem(item));
		});

		// submenus
		Object.keys(groups).forEach(function (groupName) {
			var wrapper = document.createElement('div');
			wrapper.className = 'submenuWrapper';

			var headerA = document.createElement('a');
			headerA.href = 'javascript:void(0);';
			headerA.textContent = groupName + ' \u25B6';
			headerA.dataset.submenuToggle = '1';
			wrapper.appendChild(headerA);

			var subDiv = document.createElement('div');
			subDiv.className = 'dropdownMenu submenu';
			subDiv.hidden = true;

			groups[groupName].forEach(function (item) {
				subDiv.appendChild(buildMenuItem(item));
			});

			// hover logic
			var hideTimeout;

			function showSubmenu() {
				clearTimeout(hideTimeout);
				var wrapperRect = wrapper.getBoundingClientRect();
				var mainMenuRect = menu.getBoundingClientRect();
				subDiv.style.position = 'absolute';
				subDiv.style.top = (window.scrollY + wrapperRect.top) + 'px';
				subDiv.style.left = (window.scrollX + mainMenuRect.right + 2) + 'px';
				subDiv.hidden = false;
			}

			function scheduleHide() {
				clearTimeout(hideTimeout);
				hideTimeout = setTimeout(function () { subDiv.hidden = true; }, 150);
			}

			wrapper.addEventListener('mouseenter', showSubmenu);
			wrapper.addEventListener('mouseleave', scheduleHide);
			subDiv.addEventListener('mouseenter', showSubmenu);
			subDiv.addEventListener('mouseleave', scheduleHide);

			menu.appendChild(wrapper);
			document.body.appendChild(subDiv);
			subDiv.style.position = 'fixed';

			subMenus.push(subDiv);
		});
	}

	// ---- action handling ----

	function handleWidgetAction(action, url, menuItem) {
		var handler = actionHandlers.get(action);
		if (handler) {
			var activeToggle = dropdown.getActiveToggle();
			handler({
				action: action,
				url: url,
				menuItem: menuItem,
				arrow: activeToggle,
				post: activeToggle ? activeToggle.closest('.post') : null
			});
			return;
		}

		if (url && url !== '#') {
			window.location.assign(url);
		}
	}

})();
