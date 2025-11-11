(function () {

	// Create a single global dropdown menu
	const menu = document.createElement('div');
	menu.className = 'postMenuDropdown';
	menu.hidden = true;

	let activeArrow = null; // currently open arrow

	// registry for javascript-only actions
	const actionHandlers = new Map();
	const labelProviders = new Map();

	// ADD: simple list of augmenters
	const menuAugmenters = [];

	// expose minimal api for other modules to hook custom actions and labels
	window.postWidget = {
		registerActionHandler(action, cb) {
			if (typeof cb === 'function') {
				actionHandlers.set(action, cb);
			}
		},
		registerLabelProvider(action, cb) {
			if (typeof cb === 'function') {
				labelProviders.set(action, cb);
			}
		},
		// ADD: let modules add menu items dynamically (e.g., "View deleted post")
		registerMenuAugmenter(cb) {
			if (typeof cb === 'function') {
				menuAugmenters.push(cb);
			}
		}
	};

	document.addEventListener('click', e => {
		const arrow = e.target.closest('.menuToggle');
		const menuItem = e.target.closest('.postMenuDropdown a');

		// Clicked an arrow
		if (arrow) {
			e.preventDefault();

			// If clicking the same arrow again, close it
			if (activeArrow === arrow && !menu.hidden) {
				closeMenu();
				return;
			}

			// Open new menu for this post
			openMenu(arrow);
			return;
		}

		// Clicked a menu item
		if (menuItem) {
			e.preventDefault();

			// if this is just a submenu header ("▶ Moderate", etc.), do nothing
			if (menuItem.dataset && menuItem.dataset.submenuToggle === '1') {
				return;
			}

			// if no handler is registered for this action, treat it as a normal link (navigate)
			const action = menuItem.dataset.action || '';
			const hasHandler = actionHandlers.has(action);

			if (!hasHandler) {
				const url = menuItem.href;
				if (url && url !== '#') {
					if (e.metaKey || e.ctrlKey || e.shiftKey || e.button === 1) {
						window.open(url, '_blank');
					} else {
						window.location.assign(url);
					}
				}
				closeMenu();
				return;
			}

			// run the action while activeArrow is still set
			handleWidgetAction(action, menuItem.href, menuItem);

			// then close the menu (which also clears activeArrow)
			closeMenu();
			return;
		}

		// Clicked anywhere else -> close menu
		if (!menu.hidden && !menu.contains(e.target)) {
			closeMenu();
		}
	});

	function openMenu(arrow) {
		const post = arrow.closest('.post');
		const postMenu = arrow.closest('.postMenu');
		const refs = post.querySelectorAll('.widgetRefs a');

		// Reset arrow states
		resetArrows();

		// Build dropdown
		menu.innerHTML = '';

		// group refs:
		// - rootItems = items with no data-subMenu (or empty)
		// - groups = { "Moderate": [items...], ... }
		const rootItems = [];
		const groups = {};

		refs.forEach(ref => {
			const subName = (ref.dataset.submenu || '').trim();
			const itemData = {
				href: ref.href,
				action: ref.dataset.action,
				label: ref.dataset.label
			};

			if (subName) {
				if (!groups[subName]) {
					groups[subName] = [];
				}
				groups[subName].push(itemData);
			} else {
				rootItems.push(itemData);
			}
		});

		// ✅ ADD: let external modules inject extra items (e.g., "View deleted post")
		menuAugmenters.forEach(function (aug) {
			try {
				const extra = aug({ post: post, arrow: arrow });
				if (Array.isArray(extra)) {
					extra.forEach(function (item) {
						if (!item || (!item.label && !item.action)) return;
						const subName = (item.subMenu || '').trim();
						const data = {
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
			} catch (e) {
				console.error('menu augmenter error', e);
			}
		});

		// helper to build a clickable <a> for an item
		function buildMenuItem(item) {
			const a = document.createElement('a');
			a.href = item.href;
			a.dataset.action = item.action;

			// default label from server / php
			let label = item.label;

			// allow modules to provide dynamic labels per action/post
			const lp = labelProviders.get(a.dataset.action);
			if (lp) {
				try {
					const custom = lp({
						action: a.dataset.action,
						url: a.href,
						arrow: arrow,
						post: post
					});
					if (typeof custom === 'string' && custom.length) {
						label = custom;
					}
				} catch (e) {
					console.error('label provider error', e);
				}
			}

			a.textContent = label;
			return a;
		}

		// add all root-level items first
		rootItems.forEach(function(item) {
			menu.appendChild(buildMenuItem(item));
		});

		// now build submenus for each group
		Object.keys(groups).forEach(function (groupName) {
			// wrapper that shows up in the main menu
			const wrapper = document.createElement('div');
			wrapper.className = 'submenuWrapper';

			// header anchor that the user hovers
			const headerA = document.createElement('a');
			headerA.href = 'javascript:void(0);';
			// add arrow ▶ after submenu label
			headerA.textContent = groupName + ' ▶';
			headerA.dataset.submenuToggle = '1';
			wrapper.appendChild(headerA);

			// actual submenu dropdown
			const subDiv = document.createElement('div');
			subDiv.className = 'postMenuDropdown submenu';
			subDiv.hidden = true;

			// fill submenu with that group's items
			groups[groupName].forEach(function (item) {
				subDiv.appendChild(buildMenuItem(item));
			});

			// hover logic: keep submenu visible if mouse is over either the wrapper OR the submenu
			let hideTimeout;

			function showSubmenu() {
				clearTimeout(hideTimeout);

				const wrapperRect = wrapper.getBoundingClientRect();
				const containerRect = postMenu.getBoundingClientRect();
				const mainMenuRect = menu.getBoundingClientRect();

				subDiv.style.position = 'absolute';
				subDiv.style.top = ((wrapperRect.top - containerRect.top)) + 'px';
				subDiv.style.left = ((mainMenuRect.right - containerRect.left) + 2) + 'px';

				subDiv.hidden = false;
			}

			function scheduleHideSubmenu() {
				clearTimeout(hideTimeout);
				hideTimeout = setTimeout(function () {
					subDiv.hidden = true;
				}, 150);
			}

			wrapper.addEventListener('mouseenter', function () {
				showSubmenu();
			});

			wrapper.addEventListener('mouseleave', function () {
				scheduleHideSubmenu();
			});

			subDiv.addEventListener('mouseenter', function () {
				showSubmenu();
			});

			subDiv.addEventListener('mouseleave', function () {
				scheduleHideSubmenu();
			});

			// put the wrapper in the main menu
			menu.appendChild(wrapper);

			// put the submenu next to the main menu, at the same .postMenu level
			postMenu.appendChild(subDiv);

			// remember all submenus so we can hide/remove them later
			if (!menu._subMenus) menu._subMenus = [];
			menu._subMenus.push(subDiv);
		});

		// Append dropdown menu inside this post's .postMenu block
		postMenu.appendChild(menu);

		// Make sure .postMenu is a positioned container so the absolute menu
		const computedPos = window.getComputedStyle(postMenu).position;
		if (computedPos === 'static') {
			postMenu.style.position = 'relative';
		}

		// Position menu directly under the clicked arrow
		const arrowRect = arrow.getBoundingClientRect();
		const containerRect = postMenu.getBoundingClientRect();

		menu.style.position = 'absolute';
		menu.style.top = ((arrowRect.bottom - containerRect.top) + 2) + 'px';
		menu.style.left = (arrowRect.left - containerRect.left) + 'px';

		// Show menu
		menu.hidden = false;

		// Mark active arrow
		arrow.classList.add('menuOpen');
		activeArrow = arrow;
	}

	function closeMenu() {
		menu.hidden = true;

		// hide any open submenus too
		if (menu._subMenus && menu._subMenus.length) {
			menu._subMenus.forEach(function (sm) {
				if (sm) {
					sm.hidden = true;
				}
			});
		}

		resetArrows();
		activeArrow = null;
	}

	function resetArrows() {
		document.querySelectorAll('.menuToggle.menuOpen').forEach(btn =>
			btn.classList.remove('menuOpen')
		);
	}

	// Example action handler — adapt for real actions
	function handleWidgetAction(action, url, menuItem) {
		// check for a registered javascript-only handler first
		const handler = actionHandlers.get(action);
		if (handler) {
			// pass some useful context to the handler
			handler({
				action: action,
				url: url,
				menuItem: menuItem,
				arrow: activeArrow,
				post: activeArrow ? activeArrow.closest('.post') : null
			});
			return;
		}

		// default behavior: navigate to the link (redirect)
		if (url && url !== '#') {
			window.location.assign(url);
		}
	}

})();
