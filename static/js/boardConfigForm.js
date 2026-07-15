/**
 * Board configuration editor (admin > boards > view board).
 *
 * Two jobs:
 *  - Array settings are edited as rows of inputs. This keeps the hidden JSON input (the one that
 *    actually submits) in sync as rows are typed into, added, or removed, so entries are persisted
 *    by the form's own save button rather than per-row.
 *  - Once anything in the form has been edited, the save button is pinned to the viewport so it
 *    stays reachable without scrolling to the end of a long form.
 */
(function(){
	var form = document.getElementById('boardConfigForm');
	if (!form || form.dataset.arrayEditorInit) return;
	form.dataset.arrayEditorInit = '1';

	// Any edit anywhere in the form pins the save button to the viewport, and - if the confirmation
	// window is open - refreshes its preview so it always reflects the form's current state.
	function markDirty(){
		form.classList.add('configFormDirty');
		refreshChangesWindow();
	}

	function serialize(editor){
		var map = editor.dataset.mode === 'map';
		var out = map ? {} : [];
		editor.querySelectorAll('.configArrayList > .configArrayRow').forEach(function(row){
			var v = row.querySelector('.configArrayValue').value;
			if (map){
				var k = row.querySelector('.configArrayKey').value;
				if (k === '') return;
				out[k] = v;
			} else {
				if (v === '') return;
				out.push(v);
			}
		});
		editor.querySelector('.configArrayJson').value = JSON.stringify(out);
	}

	function addRow(editor){
		var map = editor.dataset.mode === 'map';
		var li = document.createElement('li');
		li.className = 'configArrayRow';
		if (map){
			var nk = editor.querySelector('.configArrayNewKey');
			var ki = document.createElement('input');
			ki.type = 'text'; ki.className = 'configArrayKey';
			ki.value = nk ? nk.value : '';
			li.appendChild(ki);
			if (nk) nk.value = '';
		}
		var nv = editor.querySelector('.configArrayNewValue');
		var vi = document.createElement('input');
		vi.type = 'text'; vi.className = 'configArrayValue';
		vi.value = nv ? nv.value : '';
		li.appendChild(vi);
		if (nv) nv.value = '';
		var rm = document.createElement('button');
		rm.type = 'button'; rm.className = 'configArrayRemove'; rm.textContent = 'x'; rm.title = 'Delete entry';
		li.appendChild(rm);
		editor.querySelector('.configArrayList').appendChild(li);
		serialize(editor);
		markDirty();
	}

	form.addEventListener('click', function(e){
		var btn = e.target.closest ? e.target.closest('button') : null;
		if (!btn) return;
		var editor = btn.closest('.configArrayEditor');
		if (!editor) return;
		if (btn.classList.contains('configArrayAddBtn')){
			e.preventDefault(); addRow(editor);
		} else if (btn.classList.contains('configArrayRemove')){
			e.preventDefault();
			btn.closest('.configArrayRow').remove();
			serialize(editor);
			markDirty();
		}
	});

	// Entries are kept in the hidden JSON input as they're typed; the form's save button is what
	// actually persists them.
	form.addEventListener('input', function(e){
		markDirty();
		var editor = e.target.closest ? e.target.closest('.configArrayEditor') : null;
		if (editor && (e.target.classList.contains('configArrayValue') || e.target.classList.contains('configArrayKey'))){
			serialize(editor);
		}
	});

	// Checkboxes and <select>s in some browsers only report through change.
	form.addEventListener('change', markDirty);

	form.addEventListener('keydown', function(e){
		if (e.key !== 'Enter') return;
		if (e.target.classList.contains('configArrayNewKey') || e.target.classList.contains('configArrayNewValue')){
			e.preventDefault();
			var editor = e.target.closest('.configArrayEditor');
			if (editor) addRow(editor);
		}
	});

	// Reset deletes the board's stored overrides outright, and it sits next to Save.
	var resetButton = document.getElementById('boardConfigResetButton');
	if (resetButton){
		resetButton.addEventListener('click', function(e){
			var ok = window.confirm('Reset this board\'s configuration?\n\nEvery setting goes back to its default and this board\'s saved overrides are deleted. This cannot be undone.');
			if (!ok) e.preventDefault();
		});
	}

	/* ── Change tracking ──────────────────────────────────────────────────────────────────────
	 * Every field's value as the page was served. Comparing against this on submit gives the
	 * exact list of edits to confirm, and lets us tell "nothing changed" from a real save.
	 */

	// The fields that carry config values; the hidden action/CSRF inputs are not among them.
	function configFields(){
		return form.querySelectorAll('[name^="config["]');
	}

	// One comparable string per field. Array editors are compared through the hidden JSON input
	// they keep in sync, so a reordered or edited list registers as a change.
	function fieldValue(field){
		if (field.type === 'checkbox') return field.checked ? 'on' : 'off';
		return field.value;
	}

	// The config key (dot-path) of the setting a field belongs to - what the confirmation window
	// names each change by.
	function fieldKey(field){
		var row = field.closest('tr');
		var key = row ? row.querySelector('.configKey') : null;
		return key ? key.textContent.trim() : field.name;
	}

	// How long a single plain-text value may run before it is cut. List *entries* are never cut:
	// the point of the list view is to show exactly which elements changed.
	var TEXT_LIMIT = 300;

	function truncate(text, limit){
		return text.length > limit ? text.slice(0, limit - 3) + '...' : text;
	}

	function moreText(count){
		return (form.dataset.msgMore || '...and {count} more.').replace('{count}', count);
	}

	// Array settings submit as a JSON string. Decode it so it can be shown as a list rather than
	// as a wall of braces and quotes; anything that isn't a JSON object/array stays plain text.
	function parseListValue(raw){
		if (typeof raw !== 'string' || !/^\s*[\[{]/.test(raw)) return null;
		try {
			var parsed = JSON.parse(raw);
			return (parsed !== null && typeof parsed === 'object') ? parsed : null;
		} catch (err){
			return null;
		}
	}

	// A list as [{ id, text }] entries: id is the array index or the map key (what the two sides
	// are aligned on when diffing), text is the line shown for it.
	function listEntries(value){
		if (Array.isArray(value)){
			return value.map(function(v, i){ return { id: i, text: String(v) }; });
		}
		return Object.keys(value).map(function(k){ return { id: k, text: k + ': ' + String(value[k]) }; });
	}

	// The ids (indices or keys) whose entry differs between the two list values - i.e. the elements
	// that were actually edited, added or removed. These are the ones always shown, un-truncated.
	function editedListIds(fromValue, toValue){
		var edited = new Set();
		var fromArray = Array.isArray(fromValue);
		var toArray = Array.isArray(toValue);

		if (fromArray && toArray){
			for (var i = 0; i < Math.max(fromValue.length, toValue.length); i++){
				var a = i < fromValue.length ? String(fromValue[i]) : null;
				var b = i < toValue.length ? String(toValue[i]) : null;
				if (a !== b) edited.add(i);
			}
		} else if (!fromArray && !toArray){
			var keys = new Set(Object.keys(fromValue).concat(Object.keys(toValue)));
			keys.forEach(function(k){
				var a = (k in fromValue) ? String(fromValue[k]) : null;
				var b = (k in toValue) ? String(toValue[k]) : null;
				if (a !== b) edited.add(k);
			});
		}

		return edited;
	}

	// Render one side of a list change: every edited element in full, and each run of unedited
	// elements between them collapsed to a single "...N more..." line, so a last-and-3rd-last edit
	// shows both of those with the element between them (and everything before) left truncated.
	function listSideNode(value, editedIds){
		var wrap = el('div', 'configChangeValue');
		var entries = listEntries(value);

		if (entries.length === 0){
			wrap.appendChild(el('span', 'configChangeEmpty', form.dataset.msgEmpty || '(empty)'));
			return wrap;
		}

		var ul = el('ul', 'configChangeEntries');
		var hiddenRun = 0;

		var flushHidden = function(){
			if (hiddenRun > 0){
				ul.appendChild(el('li', 'configChangeMore', moreText(hiddenRun)));
				hiddenRun = 0;
			}
		};

		entries.forEach(function(entry){
			if (editedIds.has(entry.id)){
				flushHidden();
				ul.appendChild(el('li', 'configChangeEdited', entry.text));
			} else {
				hiddenRun++;
			}
		});
		flushHidden();

		wrap.appendChild(ul);

		var count = form.dataset.msgEntries || '{count} entries';
		wrap.appendChild(el('div', 'configChangeCount', count.replace('{count}', entries.length)));

		return wrap;
	}

	// A plain (non-list) value, cut if very long.
	function scalarNode(raw){
		if (raw === ''){
			return el('span', 'configChangeEmpty', form.dataset.msgEmpty || '(empty)');
		}
		return el('span', 'configChangeText', truncate(raw, TEXT_LIMIT));
	}

	// The before/after cell contents for one change. When both sides are lists of the same kind
	// they are diffed so only the edited elements are spelled out; otherwise each side renders on
	// its own.
	function changeValueNodes(from, to){
		var fromList = parseListValue(from);
		var toList = parseListValue(to);
		var sameKind = fromList && toList && (Array.isArray(fromList) === Array.isArray(toList));

		if (sameKind){
			var editedIds = editedListIds(fromList, toList);
			return { fromNode: listSideNode(fromList, editedIds), toNode: listSideNode(toList, editedIds) };
		}

		return {
			fromNode: fromList ? listSideNode(fromList, editedListIds(fromList, fromList)) : scalarNode(from),
			toNode:   toList   ? listSideNode(toList, editedListIds(toList, toList))       : scalarNode(to)
		};
	}

	var baseline = new Map();
	function snapshot(){
		baseline.clear();
		configFields().forEach(function(field){
			baseline.set(field.name, fieldValue(field));
		});
	}

	// The array editors serialize on input, but a value typed into an add row and never added
	// would otherwise be dropped on save - commit it first, so it counts as a change too.
	function flushArrayEditors(){
		form.querySelectorAll('.configArrayEditor').forEach(function(editor){
			var nv = editor.querySelector('.configArrayNewValue');
			if (nv && nv.value !== ''){
				addRow(editor);
			}
			serialize(editor);
		});
	}

	// Every setting whose value differs from the one the page was served with, with the values on
	// either side of the edit.
	function collectChanges(){
		var changed = [];
		configFields().forEach(function(field){
			var was = baseline.get(field.name);
			var now = fieldValue(field);
			if (was !== undefined && was !== now){
				changed.push({ key: fieldKey(field), from: was, to: now });
			}
		});
		return changed;
	}

	function el(tag, className, text){
		var node = document.createElement(tag);
		if (className) node.className = className;
		if (text !== undefined) node.textContent = text;
		return node;
	}

	// The confirmation window, while it is open, so further edits to the form can refresh it in
	// place rather than needing it closed and reopened.
	var changesWindow = null;

	// (Re)fill the table body with a row per change. Edited list elements are always shown; the
	// window's own scroll handles the length.
	function fillChangeRows(tbody, changes){
		tbody.textContent = '';

		changes.forEach(function(change){
			var row = document.createElement('tr');
			row.appendChild(el('td', 'configChangeKey', change.key));

			var nodes = changeValueNodes(change.from, change.to);

			var from = el('td', 'configChangeFrom');
			from.appendChild(nodes.fromNode);
			row.appendChild(from);

			var to = el('td', 'configChangeTo');
			to.appendChild(nodes.toNode);
			row.appendChild(to);

			tbody.appendChild(row);
		});
	}

	// Recompute the changes and re-render the open window. Called whenever the form is edited while
	// it is open; if every change has been reverted the window closes.
	function refreshChangesWindow(){
		if (!changesWindow) return;

		// Sync each editor's hidden JSON from its committed rows (but do NOT flush the add-row: a
		// half-typed new entry must not be committed just because the preview refreshed).
		form.querySelectorAll('.configArrayEditor').forEach(serialize);

		var changes = collectChanges();

		if (changes.length === 0){
			changesWindow.win.remove();   // onclose clears changesWindow
			return;
		}

		fillChangeRows(changesWindow.tbody, changes);
	}

	// Show the pending edits in a kkwm window: it has room for the full before -> after values,
	// which a native confirm() dialog does not.
	function openChangesWindow(changes, onConfirm){
		var body = el('div', 'configChangesBody');

		var list = el('div', 'configChangesList');

		// postlists is the admin table style used by the action log, staff list and manage-posts
		// tables, so this window matches the rest of the panel and picks up the theme's colours.
		var table = el('table', 'postlists configChangesTable');

		var headRow = document.createElement('tr');
		headRow.appendChild(el('th', 'configChangeKey', form.dataset.msgColSetting || 'Setting'));
		headRow.appendChild(el('th', null, form.dataset.msgColFrom || 'Current'));
		headRow.appendChild(el('th', null, form.dataset.msgColTo || 'New'));

		var head = document.createElement('thead');
		head.appendChild(headRow);
		table.appendChild(head);

		var tbody = document.createElement('tbody');
		fillChangeRows(tbody, changes);
		table.appendChild(tbody);

		list.appendChild(table);
		body.appendChild(list);

		var buttons = el('div', 'configChangesButtons');
		var cancel = el('button', null, form.dataset.msgCancel || 'Cancel');
		var apply = el('button', null, form.dataset.msgApply || 'Confirm');
		cancel.type = 'button';
		apply.type = 'button';
		buttons.appendChild(cancel);
		buttons.appendChild(apply);
		body.appendChild(buttons);

		// A kkwm window is keyed by its title: closing this one on both paths means reopening it
		// builds a fresh list rather than flashing a stale window.
		var title = form.dataset.msgConfirm || 'Save these changes?';
		var win = new kkwmWindow(title, { w: 600, h: 400 });
		win.div.appendChild(body);

		changesWindow = { win: win, tbody: tbody };
		// Closing by any route (the window's own X, cancel, confirm) stops the live refresh.
		win.onclose = function(){ changesWindow = null; };

		// kkwm places the window from the nominal size above, before this content exists, so a tall
		// change list would hang off the bottom of the screen (taking the buttons with it). Now that
		// it has been laid out, pull it back inside the viewport.
		requestAnimationFrame(function(){
			var margin = 20;
			var viewportWidth = document.documentElement.clientWidth;
			var viewportHeight = document.documentElement.clientHeight;

			var x = Math.max(margin, Math.min(
				parseInt(win.div.style.left, 10) || 0,
				viewportWidth - win.div.offsetWidth - margin
			));
			var y = Math.max(margin, Math.min(
				parseInt(win.div.style.top, 10) || 0,
				viewportHeight - win.div.offsetHeight - margin
			));

			if (typeof win.move === 'function'){
				win.move(x, y);
			} else {
				win.div.style.left = x + 'px';
				win.div.style.top = y + 'px';
			}
		});

		cancel.addEventListener('click', function(){ win.remove(); });
		apply.addEventListener('click', function(){
			win.remove();
			onConfirm();
		});
	}

	// Without the window manager (koko.js missing), fall back to a plain confirm listing the keys -
	// values would be unreadable crammed into it.
	function confirmWithDialog(changes, onConfirm){
		var shown = changes.slice(0, 25);
		var lines = shown.map(function(change){ return '  ' + change.key; });

		if (changes.length > shown.length){
			var more = form.dataset.msgMore || '...and {count} more.';
			lines.push('  ' + more.replace('{count}', changes.length - shown.length));
		}

		var intro = form.dataset.msgConfirm || 'Save these changes?';
		if (window.confirm(intro + '\n\n' + lines.join('\n'))) onConfirm();
	}

	function confirmChanges(changes, onConfirm){
		if (typeof kkwmWindow === 'function'){
			openChangesWindow(changes, onConfirm);
		} else {
			confirmWithDialog(changes, onConfirm);
		}
	}

	// The override markers (the * and the row highlight) are derived from what is stored, so the
	// server tells us which settings are overridden now and we re-mark the rows in place.
	function applyOverrideMarkers(overridden){
		if (!Array.isArray(overridden)) return;
		var set = new Set(overridden);

		form.querySelectorAll('.configKey').forEach(function(key){
			var row = key.closest('tr');
			if (!row) return;
			var isOverridden = set.has(key.textContent.trim());
			var marker = row.querySelector('.configOverridden');

			row.classList.toggle('configRowOverridden', isOverridden);

			if (isOverridden && !marker){
				var span = document.createElement('span');
				span.className = 'configOverridden';
				span.title = 'Overridden - differs from the value this scope inherits';
				span.textContent = '*';
				var label = row.querySelector('label');
				if (label) label.insertAdjacentElement('afterend', span);
			} else if (!isOverridden && marker){
				marker.remove();
			}
		});
	}

	function notify(text, isSuccess){
		if (typeof showMessage === 'function'){
			showMessage(text, isSuccess);
		} else if (!isSuccess){
			window.alert(text);
		}
	}

	// Post the form in the background, so saving doesn't cost the admin their scroll position.
	function save(submitter){
		submitter.disabled = true;

		fetch(form.action, {
			method: 'POST',
			body: new FormData(form),
			credentials: 'same-origin',
			headers: { 'X-Requested-With': 'XMLHttpRequest' }
		})
			.then(function(response){
				return response.json().catch(function(){ return { success: false }; });
			})
			.then(function(data){
				if (data && data.success){
					notify(data.message || 'Configuration edited.', true);
					applyOverrideMarkers(data.overridden);
					snapshot();                                   // these values are the saved ones now
					form.classList.remove('configFormDirty');     // nothing outstanding, so unstick save
				} else {
					notify((data && data.message) || form.dataset.msgFailed || 'Could not save the configuration.', false);
				}
			})
			.catch(function(){
				notify(form.dataset.msgFailed || 'Could not save the configuration.', false);
			})
			.finally(function(){
				submitter.disabled = false;
			});
	}

	snapshot();

	form.addEventListener('submit', function(e){
		flushArrayEditors();

		// Reset is a different action entirely: let it post normally and reload the page with the
		// restored values. Same for any browser that gives us no submitter to inspect.
		var submitter = e.submitter;
		if (!submitter || submitter.id !== 'boardConfigSaveButton') return;

		// No fetch() means no AJAX: fall through to the plain POST + redirect, which still works.
		if (typeof window.fetch !== 'function') return;

		// From here the save is ours: the confirmation is asynchronous (it's a window, not a modal
		// dialog), so the submit is always cancelled and re-driven from the Confirm button.
		e.preventDefault();

		var changes = collectChanges();
		if (changes.length === 0){
			notify(form.dataset.msgNoChanges || 'No changes to save.', false);
			return;
		}

		confirmChanges(changes, function(){
			save(submitter);
		});
	});
})();
