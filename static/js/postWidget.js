/**
 * postWidget.js — Dropdown menu for post actions
 *
 * Builds a per-post dropdown from hidden widget refs injected by PHP modules.
 * The menu itself is widgetMenu.js; this only says where the items come from.
 *
 * Depends on: dropdownMenu.js, widgetMenu.js (loaded first)
 */
(function () {
	'use strict';

	window.postWidget = WidgetMenu.create({
		menuClass: 'postMenuDropdown',
		submenuClass: 'postMenuSubmenu',
		toggleSelector: '.postMenu .menuToggle',

		// PHP renders each entry as an empty <a> in the post's hidden .widgetRefs container
		collectItems: function (arrow) {
			var post = arrow.closest('.post');
			if (!post) return [];

			var items = [];

			post.querySelectorAll('.widgetRefs a').forEach(function (ref) {
				items.push({
					href: ref.href,
					action: ref.dataset.action,
					label: ref.dataset.label,
					subMenu: ref.dataset.submenu,
					params: WidgetMenu.collectParams(ref)
				});
			});

			return items;
		},

		buildContext: function (arrow) {
			return { arrow: arrow, post: arrow.closest('.post') };
		}
	});
})();
