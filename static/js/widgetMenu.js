/**
 *
 * Both menus work the same way: a toggle arrow opens a dropdown built from items the server
 * rendered into hidden markup, grouped into hover-opened submenus, with modules registering
 * action handlers, label providers and augmenters against them.
 *
 * The two only differ in where their items come from, what context handlers get, and which CSS
 * class the dropdown carries (which is what makes the attachment menu the smaller of the two),
 * so those are the options create() takes.
 *
 * Depends on: dropdownMenu.js (loaded first)
 */
(function () {
	'use strict';

	/**
	 * Collect data-param-* attributes from an element into a plain object.
	 * Keys are as the browser stores them (always lowercased for HTML attributes).
	 */
	function collectParams(el) {
		var params = {};
		if (!el || !el.attributes) return params;
		for (var i = 0; i < el.attributes.length; i++) {
			var attr = el.attributes[i];
			if (attr.name.indexOf('data-param-') === 0) {
				params[attr.name.slice(11)] = attr.value;
			}
		}
		return params;
	}

	/** Copy a params object onto an element as data-param-* attributes */
	function applyParams(el, params) {
		if (!params) return;
		for (var key in params) {
			if (params.hasOwnProperty(key)) {
				el.setAttribute('data-param-' + key, params[key]);
			}
		}
	}

	/**
	 * Create a menu.
	 *
	 * @param {Object} config
	 * @param {string}   config.menuClass       Extra class on the dropdown, and on its submenus
	 * @param {string}   config.submenuClass    Extra class on submenus only
	 * @param {string}   config.toggleSelector  Selector matching this menu's toggle arrows
	 * @param {Function} config.collectItems    (toggle) => item[], the server-rendered entries
	 * @param {Function} config.buildContext    (toggle) => object merged into every ctx
	 * @param {boolean} [config.nativeLinks]    Let entries without a handler behave as plain links
	 * @param {Function} [config.onAugmenter]   Called when an augmenter is registered
	 * @returns {{registerActionHandler, registerLabelProvider, registerMenuAugmenter, getDropdown}}
	 */
	function create(config) {
		var dropdown = DropdownMenu.create(config.menuClass);

		// registry for javascript-only actions
		var actionHandlers = new Map();
		var labelProviders = new Map();
		var menuAugmenters = [];

		// submenus live outside the dropdown, so they're tracked here for hit-testing and cleanup
		var subMenuEls = [];

		// track the open toggle ourselves — dropdownMenu.js clears activeToggle before our click
		// handler fires when the click lands in a submenu, which is outside the menu element
		var currentToggle = null;

		// ---- menu building ----

		/** Build one menu anchor, letting a label provider override the text */
		function buildMenuItem(item, ctx) {
			var a = document.createElement('a');
			a.href = item.href || '#';
			if (item.target) a.target = item.target;
			a.dataset.action = item.action || '';
			applyParams(a, item.params);

			var label = item.label || '';
			var labelProvider = labelProviders.get(a.dataset.action);

			if (labelProvider) {
				try {
					var custom = labelProvider(Object.assign({}, ctx, {
						action: a.dataset.action,
						url: a.href
					}));
					if (typeof custom === 'string' && custom.length) label = custom;
				} catch (err) {
					console.error('label provider error', err);
				}
			}

			a.textContent = label;
			return a;
		}

		/** Add a named group as a hover-opened submenu hanging off <body> */
		function buildSubmenu(menu, groupName, items, ctx) {
			var wrapper = document.createElement('div');
			wrapper.className = 'submenuWrapper';

			var header = document.createElement('a');
			header.href = 'javascript:void(0);';
			header.textContent = groupName + ' ▶';
			header.dataset.submenuToggle = '1';
			wrapper.appendChild(header);

			var subMenu = document.createElement('div');
			subMenu.className = 'dropdownMenu submenu ' + config.menuClass + ' ' + config.submenuClass;
			subMenu.hidden = true;
			subMenu.style.position = 'absolute';

			items.forEach(function (item) {
				subMenu.appendChild(buildMenuItem(item, ctx));
			});

			var hideTimeout;

			function showSubmenu() {
				clearTimeout(hideTimeout);
				var wrapperRect = wrapper.getBoundingClientRect();
				var menuRect = menu.getBoundingClientRect();
				subMenu.style.top = (window.scrollY + wrapperRect.top) + 'px';
				subMenu.style.left = (window.scrollX + menuRect.right + 2) + 'px';
				subMenu.hidden = false;
			}

			function scheduleHide() {
				clearTimeout(hideTimeout);
				hideTimeout = setTimeout(function () { subMenu.hidden = true; }, 150);
			}

			wrapper.addEventListener('mouseenter', showSubmenu);
			wrapper.addEventListener('mouseleave', scheduleHide);
			subMenu.addEventListener('mouseenter', showSubmenu);
			subMenu.addEventListener('mouseleave', scheduleHide);

			menu.appendChild(wrapper);
			document.body.appendChild(subMenu);

			return subMenu;
		}

		function buildMenuContent(menu, subMenus, toggle) {
			var ctx = config.buildContext(toggle);

			// server-rendered entries, plus anything the augmenters add
			var items = config.collectItems(toggle) || [];

			menuAugmenters.forEach(function (augmenter) {
				try {
					var extra = augmenter(ctx);
					if (!Array.isArray(extra)) return;

					extra.forEach(function (item) {
						if (!item || (!item.label && !item.action)) return;
						items.push(item);
					});
				} catch (err) {
					console.error('menu augmenter error', err);
				}
			});

			var groups = {};

			items.forEach(function (item) {
				var groupName = (item.subMenu || '').trim();

				// ungrouped entries sit in the menu itself
				if (!groupName) {
					menu.appendChild(buildMenuItem(item, ctx));
					return;
				}

				if (!groups[groupName]) groups[groupName] = [];
				groups[groupName].push(item);
			});

			Object.keys(groups).forEach(function (groupName) {
				var subMenu = buildSubmenu(menu, groupName, groups[groupName], ctx);
				subMenus.push(subMenu);
				subMenuEls.push(subMenu);
			});
		}

		function openMenu(toggle) {
			// submenus hang off <body>, so clear the previous menu's leftovers
			subMenuEls.forEach(function (el) { el.remove(); });
			subMenuEls = [];

			dropdown.open(toggle, function (menu, subMenus) {
				buildMenuContent(menu, subMenus, toggle);
			});

			// clicking the same arrow twice closes instead of opening
			currentToggle = dropdown.isOpen() ? toggle : null;
		}

		// ---- click handling ----

		/** Whether an element belongs to this menu rather than another one on the page */
		function ownsElement(el) {
			if (dropdown.getMenu().contains(el)) return true;

			return subMenuEls.some(function (subMenu) {
				return subMenu.contains(el);
			});
		}

		function closeMenu() {
			currentToggle = null;
			dropdown.close();
		}

		function handleMenuItem(e, menuItem) {
			var action = menuItem.dataset.action || '';
			var handler = actionHandlers.get(action);

			// prefer our own toggle, see currentToggle above
			var toggle = currentToggle || dropdown.getActiveToggle();
			var ctx = toggle ? config.buildContext(toggle) : {};

			ctx.action = action;
			ctx.menuItem = menuItem;
			ctx.url = menuItem.href;
			ctx.params = collectParams(menuItem);

			if (handler) {
				e.preventDefault();
				closeMenu();
				handler(ctx);
				return;
			}

			// no handler: an ordinary link, followed by the browser so it can open in a new tab
			if (config.nativeLinks) {
				closeMenu();
				return;
			}

			e.preventDefault();

			if (ctx.url && ctx.url !== '#') {
				if (e.metaKey || e.ctrlKey || e.shiftKey || e.button === 1) {
					window.open(ctx.url, '_blank');
				} else {
					window.location.assign(ctx.url);
				}
			}

			closeMenu();
		}

		document.addEventListener('click', function (e) {
			var toggle = e.target.closest(config.toggleSelector);

			if (toggle) {
				e.preventDefault();
				openMenu(toggle);
				return;
			}

			var menuItem = e.target.closest('a');
			if (!menuItem || !ownsElement(menuItem)) return;

			// submenu header — opens on hover, does nothing on click
			if (menuItem.dataset.submenuToggle === '1') {
				e.preventDefault();
				return;
			}

			handleMenuItem(e, menuItem);
		});

		// ---- api for other modules ----

		return {
			registerActionHandler: function (action, cb) {
				if (typeof cb === 'function') actionHandlers.set(action, cb);
			},
			registerLabelProvider: function (action, cb) {
				if (typeof cb === 'function') labelProviders.set(action, cb);
			},
			registerMenuAugmenter: function (cb) {
				if (typeof cb !== 'function') return;

				var wasEmpty = menuAugmenters.length === 0;
				menuAugmenters.push(cb);

				if (config.onAugmenter) config.onAugmenter(wasEmpty);
			},
			hasAugmenters: function () {
				return menuAugmenters.length > 0;
			},
			getDropdown: function () {
				return dropdown;
			}
		};
	}

	window.WidgetMenu = {
		create: create,
		collectParams: collectParams,
		applyParams: applyParams
	};
})();
