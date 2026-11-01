/**
 * sideNavAdmin.js - enhances the side nav editor.
 *
 * The page is one server-rendered form where every button submits an action and reloads. This
 * script does those actions in place instead (add, delete, ↑/↓, the group select), adds dragging by
 * the ☰ handle and a live preview, and saves everything at once after listing the changes.
 * New rows and boxes come from the server's own templates (data.templates).
 *
 * Depends on: koko.js (kkwmWindow, optional)
 */
(function () {
	'use strict';

	var form = document.getElementById('sideNavEditor');
	if (!form) return;

	var data = JSON.parse(form.querySelector('.sideNavEditorData').textContent);
	var t = data.templates;
	var boxes = form.querySelector('.sideNavEditorBoxes');
	var preview = form.querySelector('.sideNavEditorPreviewBody');
	var saveButton = form.querySelector('.sideNavEditorSave');
	var dragging = null;
	var submitting = false;
	var newKeys = 0;

	/** Fill a template's %%TOKEN%% slots. */
	function fill(template, values) {
		return template.replace(/%%([A-Z_]+)%%/g, function (m, key) { return values[key]; });
	}

	function escapeHtml(text) {
		return String(text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
	}

	function fromHtml(html) {
		var holder = document.createElement('div');
		holder.innerHTML = html.trim();
		return holder.firstElementChild;
	}

	function newKey() {
		return 'new' + Date.now().toString(36) + (++newKeys);
	}

	// ─── Reading the form ────────────────────────────────────────────────────

	function serialize() {
		return Array.prototype.map.call(boxes.querySelectorAll('.sideNavBox'), function (box) {
			return {
				key: box.dataset.key,
				kind: box.dataset.kind,
				title: box.querySelector('.sideNavBoxTitle').value.trim(),
				links: Array.prototype.map.call(box.querySelectorAll('.sideNavRow'), function (row) {
					var auto = row.dataset.source;
					return {
						key: row.dataset.key,
						source: auto || null,
						label: row.querySelector('.sideNavRowLabel').value.trim(),
						// an automatic link's URL and default label come from the server (the label as HTML)
						url: auto ? row.dataset.defaultUrl : row.querySelector('.sideNavRowUrl').value.trim(),
						defaultLabel: auto ? row.dataset.defaultLabel : '',
						title: auto ? row.dataset.linkTitle : '',
						hidden: auto ? row.querySelector('.sideNavRowHidden input').checked : false
					};
				})
			};
		});
	}

	var initial = JSON.stringify(serialize());

	// ─── Keeping the form consistent ─────────────────────────────────────────

	/** Each plain row's group select lists the current groups and names the box the row is in. */
	function syncGroupSelects() {
		var options = Array.prototype.map.call(boxes.querySelectorAll('.sideNavBox'), function (box) {
			return '<option value="' + escapeHtml(box.dataset.key) + '">' + escapeHtml(box.querySelector('.sideNavBoxTitle').value) + '</option>';
		}).join('');

		boxes.querySelectorAll('.sideNavRow:not(.sideNavAutoRow)').forEach(function (row) {
			var select = row.querySelector('.sideNavRowGroup');
			select.innerHTML = options;
			select.value = row.closest('.sideNavBox').dataset.key;
		});
	}

	function renderPreview(layout) {
		var groups = layout.map(function (group) {
			var items = group.links.filter(function (l) { return !l.hidden; }).map(function (l) {
				var label = l.label ? escapeHtml(l.label) : l.defaultLabel;
				if (l.source === 'hide') return fill(t.hide, { LABEL: label });
				if (l.source === 'dirs') return fill(t.dirs, { LABEL: label });
				return fill(t.item, { URL: escapeHtml(l.url), LABEL: label, LINK_TITLE: escapeHtml(l.label || l.title) });
			});
			if (!items.length) return '';
			return fill(t.group, { ID: /^\d+$/.test(group.key) ? group.key : '0', TITLE: escapeHtml(group.title), ITEMS: items.join('') });
		});

		preview.innerHTML = fill(t.nav, { GROUPS: groups.join('') });
	}

	function changed() {
		syncGroupSelects();
		var layout = serialize();
		renderPreview(layout);
		form.classList.toggle('sideNavEditorDirty', JSON.stringify(layout) !== initial);
	}

	/** Same rule as the server: relative, http(s), irc(s) or mailto, no whitespace or control characters. */
	function validateUrl(field) {
		var v = field.value.trim();
		var scheme = /^([a-z][a-z0-9+.-]*):/i.exec(v);
		var bad = /[\x00-\x20\x7f]/.test(v) || (scheme && !/^(https?|ircs?|mailto)$/i.test(scheme[1]));
		field.setCustomValidity(bad ? 'Use a relative URL or one starting with http://, https://, irc://, ircs:// or mailto:' : '');
	}

	// ─── Actions ─────────────────────────────────────────────────────────────

	function addGroup() {
		var title = form.querySelector('.sideNavEditorNewTitle');
		if (!title.value.trim()) {
			title.focus();
			return;
		}
		var box = fromHtml(fill(t.adminGroup, { KEY: newKey(), TITLE: escapeHtml(title.value.trim()) }));
		boxes.appendChild(box);
		title.value = '';
		changed();
		box.querySelector('.sideNavNewLabel').focus();
	}

	function addLink(box) {
		var label = box.querySelector('.sideNavNewLabel');
		var url = box.querySelector('.sideNavNewUrl');
		if (!label.value.trim() || !url.value.trim()) {
			(label.value.trim() ? url : label).focus();
			return;
		}
		var row = fromHtml(fill(t.adminLink, { KEY: newKey(), LABEL: escapeHtml(label.value.trim()), URL: escapeHtml(url.value.trim()), GROUP_OPTIONS: '' }));
		box.querySelector('.sideNavBoxLinks').appendChild(row);
		validateUrl(row.querySelector('.sideNavRowUrl'));
		label.value = '';
		url.value = '';
		label.focus();
		changed();
	}

	function move(item, up) {
		var other = up ? item.previousElementSibling : item.nextElementSibling;
		if (other) item.parentNode.insertBefore(up ? item : other, up ? other : item);
	}

	/** The buttons' own actions, done in place. Save is left to the submit handler. */
	form.addEventListener('click', function (e) {
		var b = e.target.closest('button[name="action"]');
		if (!b || b.value === 'save') return;
		e.preventDefault();

		var parts = b.value.split(':');
		var row = b.closest('.sideNavRow');
		var box = b.closest('.sideNavBox');

		switch (parts[0]) {
			case 'add_group': addGroup(); return;
			case 'add_link': addLink(box); return;
			case 'delete_group': box.remove(); break;
			case 'delete_link': row.remove(); break;
			case 'group_up': case 'group_down': move(box, parts[0] === 'group_up'); break;
			case 'link_up': case 'link_down': move(row, parts[0] === 'link_up'); break;
		}
		b.focus();
		changed();
	});

	form.addEventListener('change', function (e) {
		if (!e.target.classList.contains('sideNavRowGroup')) return;
		var target = boxes.querySelector('.sideNavBox[data-key="' + CSS.escape(e.target.value) + '"] .sideNavBoxLinks');
		if (target) target.appendChild(e.target.closest('.sideNavRow'));
		changed();
	});

	form.addEventListener('input', function (e) {
		if (e.target.classList.contains('sideNavRowUrl')) validateUrl(e.target);
		if (!e.target.closest('.sideNavBoxAdd, .sideNavEditorAddGroup')) changed();
	});

	form.addEventListener('keydown', function (e) {
		if (e.key !== 'Enter') return;
		if (e.target.closest('.sideNavBoxAdd')) {
			e.preventDefault();
			addLink(e.target.closest('.sideNavBox'));
		} else if (e.target.closest('.sideNavEditorAddGroup')) {
			e.preventDefault();
			addGroup();
		}
	});

	// ─── Drag and drop ───────────────────────────────────────────────────────

	/** The handle turns dragging on for its row or box only while held, so inputs stay selectable. */
	form.addEventListener('mousedown', function (e) {
		if (e.target.classList.contains('sideNavGrip')) e.target.closest('.sideNavRow, .sideNavBox').draggable = true;
	});

	form.addEventListener('mouseup', function (e) {
		if (e.target.classList.contains('sideNavGrip')) e.target.closest('.sideNavRow, .sideNavBox').draggable = false;
	});

	/** The child of container that the pointer is above the middle of, or null for the end. */
	function insertionPoint(container, selector, y) {
		var items = container.querySelectorAll(':scope > ' + selector + ':not(.sideNavDragging)');
		for (var i = 0; i < items.length; i++) {
			var r = items[i].getBoundingClientRect();
			if (y < r.top + r.height / 2) return items[i];
		}
		return null;
	}

	form.addEventListener('dragstart', function (e) {
		var target = e.target;
		if (!target.classList || !(target.classList.contains('sideNavRow') || target.classList.contains('sideNavBox'))) return;
		dragging = target;
		target.classList.add('sideNavDragging');
		e.dataTransfer.effectAllowed = 'move';
		e.dataTransfer.setData('text/plain', '');
	});

	form.addEventListener('dragover', function (e) {
		if (!dragging) return;

		if (dragging.classList.contains('sideNavRow')) {
			var box = e.target.closest('.sideNavBox');
			// an automatic link stays in its own group
			if (!box || (dragging.dataset.source && box !== dragging.closest('.sideNavBox'))) return;
			var list = box.querySelector('.sideNavBoxLinks');
			e.preventDefault();
			list.insertBefore(dragging, insertionPoint(list, '.sideNavRow', e.clientY));
		} else if (e.target.closest('.sideNavEditorBoxes')) {
			e.preventDefault();
			var before = insertionPoint(boxes, '.sideNavBox', e.clientY);
			if (before !== dragging) boxes.insertBefore(dragging, before);
		}
	});

	form.addEventListener('drop', function (e) {
		if (dragging) e.preventDefault();
	});

	form.addEventListener('dragend', function () {
		if (!dragging) return;
		dragging.classList.remove('sideNavDragging');
		dragging.draggable = false;
		dragging = null;
		changed();
	});

	// ─── Changes and saving ──────────────────────────────────────────────────

	function linkText(link) {
		return (link.label || link.title) + (link.hidden ? ' (hidden)' : link.url ? ' (' + link.url + ')' : '');
	}

	/** Rows of [item, current, new] describing how layout differs from what was served. */
	function collectChanges(layout) {
		var before = JSON.parse(initial);
		var changes = [];
		var oldGroups = {}, oldLinks = {}, newLinks = {};

		function index(groups, into) {
			groups.forEach(function (group) {
				group.links.forEach(function (link) { into[link.key] = { link: link, group: group.title, groupKey: group.key }; });
			});
		}
		before.forEach(function (g) { oldGroups[g.key] = g; });
		index(before, oldLinks);
		index(layout, newLinks);

		var seen = {};
		layout.forEach(function (group) {
			if (!oldGroups[group.key]) {
				changes.push(['Group', '', group.title]);
				return;
			}
			seen[group.key] = true;
			if (oldGroups[group.key].title !== group.title) changes.push(['Group', oldGroups[group.key].title, group.title]);
		});
		before.forEach(function (group) {
			if (!seen[group.key]) changes.push(['Group', group.title, '']);
		});

		// groups that exist on both sides, compared by relative order
		var oldOrder = before.filter(function (g) { return seen[g.key]; }).map(function (g) { return g.key; });
		var newOrder = layout.filter(function (g) { return seen[g.key]; }).map(function (g) { return g.key; });
		if (oldOrder.join() !== newOrder.join()) {
			changes.push(['Group order',
				oldOrder.map(function (k) { return oldGroups[k].title; }).join(', '),
				layout.filter(function (g) { return seen[g.key]; }).map(function (g) { return g.title; }).join(', ')]);
		}

		layout.forEach(function (group) {
			group.links.forEach(function (link) {
				if (!oldLinks[link.key]) changes.push(['Link in ' + group.title, '', linkText(link)]);
			});
		});
		Object.keys(oldLinks).forEach(function (key) {
			var was = oldLinks[key], now = newLinks[key];
			if (!now) {
				changes.push(['Link in ' + was.group, linkText(was.link), '']);
				return;
			}
			if (linkText(was.link) !== linkText(now.link)) changes.push(['Link', linkText(was.link), linkText(now.link)]);
			if (was.groupKey !== now.groupKey) changes.push(['Link ' + now.link.label, was.group, now.group]);
		});

		// links that stayed in a group, compared by relative order so one insert does not move them all
		layout.forEach(function (group) {
			var old = oldGroups[group.key];
			if (!old) return;
			var stayed = function (link) { return oldLinks[link.key] && newLinks[link.key] && oldLinks[link.key].groupKey === newLinks[link.key].groupKey; };
			var label = function (link) { return newLinks[link.key].link.label; };
			var was = old.links.filter(stayed).map(label).join(', ');
			var now = group.links.filter(stayed).map(label).join(', ');
			if (was !== now) changes.push(['Link order in ' + group.title, was, now]);
		});

		return changes;
	}

	function el(tag, className, text) {
		var node = document.createElement(tag);
		if (className) node.className = className;
		if (text !== undefined) node.textContent = text;
		return node;
	}

	function confirmChanges(changes, onConfirm) {
		if (typeof kkwmWindow !== 'function') {
			var lines = changes.slice(0, 25).map(function (c) { return '  ' + c[0] + ': ' + (c[1] || '(new)') + ' -> ' + (c[2] || '(deleted)'); });
			if (window.confirm('Save these changes?\n\n' + lines.join('\n'))) onConfirm();
			return;
		}

		var body = el('div', 'configChangesBody');
		var list = el('div', 'configChangesList');
		var table = el('table', 'postlists configChangesTable');
		var headRow = el('tr');
		['Item', 'Current', 'New'].forEach(function (h) { headRow.appendChild(el('th', null, h)); });
		var thead = el('thead');
		thead.appendChild(headRow);
		table.appendChild(thead);

		var tbody = el('tbody');
		changes.forEach(function (c) {
			var tr = el('tr');
			tr.appendChild(el('td', 'configChangeKey', c[0]));
			tr.appendChild(el('td', 'configChangeFrom', c[1]));
			tr.appendChild(el('td', 'configChangeTo', c[2]));
			tbody.appendChild(tr);
		});
		table.appendChild(tbody);
		list.appendChild(table);
		body.appendChild(list);

		var buttons = el('div', 'configChangesButtons');
		var cancel = el('button', null, 'Cancel');
		var apply = el('button', null, 'Confirm');
		cancel.type = apply.type = 'button';
		buttons.appendChild(cancel);
		buttons.appendChild(apply);
		body.appendChild(buttons);

		var win = new kkwmWindow('Save these changes?', { w: 600, h: 400 });
		win.div.appendChild(body);
		cancel.addEventListener('click', function () { win.remove(); });
		apply.addEventListener('click', function () { win.remove(); onConfirm(); });
	}

	/** Every submit is a save once JS is on; it goes through the change list first. */
	form.addEventListener('submit', function (e) {
		if (submitting) return;
		e.preventDefault();
		if (!form.reportValidity()) return;
		var changes = collectChanges(serialize());
		if (!changes.length) return;

		confirmChanges(changes, function () {
			submitting = true;
			form.requestSubmit(saveButton);
		});
	});

	window.addEventListener('beforeunload', function (e) {
		if (submitting || JSON.stringify(serialize()) === initial) return;
		e.preventDefault();
		e.returnValue = '';
	});

	changed();
})();
