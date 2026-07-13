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

	// Any edit anywhere in the form pins the save button to the viewport.
	function markDirty(){
		form.classList.add('configFormDirty');
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

	// The config key (dot-path) of the setting a field belongs to. The prompt names the keys that
	// changed rather than their before/after values: a JSON list or a block of text is unreadable
	// squeezed into a confirm dialog, and the key is what identifies the setting anyway.
	function fieldKey(field){
		var row = field.closest('tr');
		var key = row ? row.querySelector('.configKey') : null;
		return key ? key.textContent.trim() : field.name;
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

	// The config keys whose value differs from the one the page was served with.
	function collectChanges(){
		var changed = [];
		configFields().forEach(function(field){
			var was = baseline.get(field.name);
			if (was !== undefined && was !== fieldValue(field)){
				changed.push(fieldKey(field));
			}
		});
		return changed;
	}

	// Name the settings that are about to be written, rather than a bare "are you sure".
	function confirmChanges(changes){
		var shown = changes.slice(0, 25);
		var lines = shown.map(function(key){
			return '  ' + key;
		});

		if (changes.length > shown.length){
			var more = form.dataset.msgMore || '...and {count} more.';
			lines.push('  ' + more.replace('{count}', changes.length - shown.length));
		}

		var intro = form.dataset.msgConfirm || 'Save these changes?';
		return window.confirm(intro + '\n\n' + lines.join('\n'));
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

	snapshot();

	form.addEventListener('submit', function(e){
		flushArrayEditors();

		// Reset is a different action entirely: let it post normally and reload the page with the
		// restored values. Same for any browser that gives us no submitter to inspect.
		var submitter = e.submitter;
		if (!submitter || submitter.id !== 'boardConfigSaveButton') return;

		// No fetch() means no AJAX: fall through to the plain POST + redirect, which still works.
		if (typeof window.fetch !== 'function') return;

		var changes = collectChanges();
		if (changes.length === 0){
			e.preventDefault();
			notify(form.dataset.msgNoChanges || 'No changes to save.', false);
			return;
		}

		if (!confirmChanges(changes)){
			e.preventDefault();
			return;
		}

		e.preventDefault();
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
	});
})();
