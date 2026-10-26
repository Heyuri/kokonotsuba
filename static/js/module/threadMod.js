/**
 * threadMod.js - OP moderation tools in the post menu.
 *
 * Depends on: postWidget.js, message.js, koko.js
 */
(function () {
	'use strict';

	var meta = document.querySelector('meta[name="threadModUrl"]');
	var moduleUrl = meta ? meta.getAttribute('content') : '';
	var triggerMeta = document.querySelector('meta[name="threadModCapcodeTrigger"]');
	var capcodeTrigger = triggerMeta ? triggerMeta.getAttribute('content') : 'OP';
	var modTriggerMeta = document.querySelector('meta[name="threadModModCapcodeTrigger"]');
	var modCapcodeTrigger = modTriggerMeta ? modTriggerMeta.getAttribute('content') : 'Thread Mod';

	if (!moduleUrl) return;

	var COOKIE = 'koko_threadmod';

	/** thread uid => 'owner' | 'mod' */
	var roles = {};

	/** The cookie's hint: thread uid => role, as the server last wrote it. */
	function readCookie() {
		var match = document.cookie.match(new RegExp('(?:^|; )' + COOKIE + '=([^;]*)'));
		var found = {};
		if (!match) return found;
		decodeURIComponent(match[1]).split(',').forEach(function (entry) {
			var parts = entry.split(':');
			if (parts.length === 2) found[parts[0]] = parts[1] === 'o' ? 'owner' : 'mod';
		});
		return found;
	}

	function csrfToken() {
		return fetch(moduleUrl + '&pageName=token', {
			credentials: 'same-origin',
			headers: { 'X-Requested-With': 'XMLHttpRequest' }
		}).then(function (res) { return res.json(); }).then(function (data) { return data.token || ''; });
	}

	function isThreadView() {
		return new URLSearchParams(location.search).has('res');
	}

	/** Every thread drawn on the page, by uid. */
	function threadsOnPage() {
		var found = {};
		document.querySelectorAll('.post.op[data-thread-uid]').forEach(function (op) {
			var thread = op.closest('.thread');
			if (thread) found[op.dataset.threadUid] = thread;
		});
		return found;
	}

	/** Whether one of the reader's own posts (kkOwnPosts: "board_no") sits in this thread. */
	function postedIn(threadEl) {
		var own;
		try {
			own = JSON.parse((typeof kkStore !== 'undefined' ? kkStore.get('kkOwnPosts') : null) || localStorage.getItem('kkOwnPosts') || '{}');
		} catch (e) {
			return false;
		}
		return Object.keys(own).some(function (key) {
			return threadEl.querySelector('#p' + key.replace(/[^A-Za-z0-9_]/g, ''));
		});
	}

	function threadUidOf(postEl) {
		var thread = postEl.closest('.thread');
		var op = thread ? thread.querySelector('.post.op[data-thread-uid]') : null;
		return op ? op.dataset.threadUid : '';
	}

	function roleFor(postEl) {
		return roles[threadUidOf(postEl)] || '';
	}

	// ---- discovery ----

	function discover() {
		var threads = threadsOnPage();
		var known = readCookie();
		var toAsk = [];

		Object.keys(threads).forEach(function (uid) {
			if (known[uid]) {
				roles[uid] = known[uid];
			} else if (postedIn(threads[uid])) {
				// posted here but not told yet: a grant may have arrived since
				toAsk.push(uid);
			}
		});

		if (!toAsk.length) {
			applyRoles();
			return;
		}

		fetch(moduleUrl + '&pageName=status&thread_uids=' + encodeURIComponent(toAsk.slice(0, 100).join(',')), {
			credentials: 'same-origin',
			headers: { 'X-Requested-With': 'XMLHttpRequest' }
		})
			.then(function (res) { return res.ok ? res.json() : { roles: {} }; })
			.then(function (data) {
				var answered = (data && data.roles) || {};
				toAsk.forEach(function (uid) {
					if (answered[uid]) roles[uid] = answered[uid];
				});
				applyRoles();
			})
			.catch(applyRoles);
	}

	function applyRoles() {
		var threads = threadsOnPage();
		Object.keys(threads).forEach(function (uid) {
			threads[uid].classList.toggle('threadModActive', !!roles[uid]);
			threads[uid].classList.toggle('threadModOwner', roles[uid] === 'owner');
		});
		updateCapcodeBoxes();
	}

	// ---- the "## OP" / "## Thread Mod" checkbox ----

	/** The thread the reply form posts to, on a thread page. */
	function currentThreadUid() {
		if (!isThreadView()) return '';
		var op = document.querySelector('.post.op[data-thread-uid]');
		return op ? op.dataset.threadUid : '';
	}

	/** What this reader types after "## " in the current thread: the owner's trigger or a mod's. */
	function currentTrigger() {
		return roles[currentThreadUid()] === 'mod' ? modCapcodeTrigger : capcodeTrigger;
	}

	function escapeRegExp(text) {
		return text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
	}

	/** " ## OP" or " ## Thread Mod" ending the name, spaced the way the server reads a capcode */
	function suffixPattern() {
		return new RegExp('(?:^|\\s+)##\\s+(?:' + escapeRegExp(capcodeTrigger) + '|' + escapeRegExp(modCapcodeTrigger) + ')\\s*$', 'i');
	}

	function hasSuffix(value) {
		return suffixPattern().test(value);
	}

	function attachCapcodeBox(nameInput) {
		if (!nameInput || nameInput.dataset.threadModBox) return;
		nameInput.dataset.threadModBox = '1';

		var label = document.createElement('label');
		label.className = 'threadModCapcodeLabel';

		var box = document.createElement('input');
		box.type = 'checkbox';
		box.name = 'opCapcode';
		box.className = 'threadModCapcodeBox';
		box.checked = hasSuffix(nameInput.value);

		label.appendChild(box);
		label.appendChild(document.createElement('span'));
		nameInput.insertAdjacentElement('afterend', label);

		function sync() {
			box.checked = hasSuffix(nameInput.value);
		}

		box.addEventListener('change', function () {
			var value = nameInput.value;
			if (box.checked) {
				if (!hasSuffix(value)) nameInput.value = value.replace(/\s+$/, '') + ' ## ' + currentTrigger();
			} else {
				nameInput.value = value.replace(suffixPattern(), '');
			}
			nameInput.dispatchEvent(new Event('input', { bubbles: true }));
		});

		nameInput.addEventListener('input', sync);
		if (nameInput.form) nameInput.form.addEventListener('reset', function () { setTimeout(sync, 0); });

		// the name cookie and quick reply's copying set the value from script, which fires no event
		var native = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value');
		Object.defineProperty(nameInput, 'value', {
			configurable: true,
			get: function () { return native.get.call(this); },
			set: function (value) { native.set.call(this, value); sync(); }
		});
	}

	function updateCapcodeBoxes() {
		var uid = currentThreadUid();
		var show = !!(uid && roles[uid]);
		var trigger = currentTrigger();

		['name', 'qrname'].forEach(function (id) {
			var input = document.getElementById(id);
			if (!input) return;
			attachCapcodeBox(input);
			var label = input.nextElementSibling;
			if (!label || !label.classList.contains('threadModCapcodeLabel')) return;

			// a hidden box would still be submitted, and the server refuses it from a stranger
			label.hidden = !show;
			label.querySelector('input').disabled = !show;
			label.querySelector('span').textContent = ' ## ' + trigger;
			label.title = roles[uid] === 'mod' ? 'Sign this post as a thread mod' : 'Sign this post as the thread OP';
		});
	}

	// The quick reply builds its form when opened, so watch for its name field
	new MutationObserver(function () {
		if (document.getElementById('qrname') && !document.getElementById('qrname').dataset.threadModBox) updateCapcodeBoxes();
	}).observe(document.body, { childList: true, subtree: true });

	// ---- menu entries ----

	function entry(action, label, postEl) {
		return {
			href: moduleUrl + '&pageName=confirm&action=' + action + '&postUid=' + encodeURIComponent(postEl.dataset.postUid || ''),
			action: 'threadMod_' + action,
			label: label,
			subMenu: '',
			params: { postuid: postEl.dataset.postUid || '', threadmodaction: action }
		};
	}

	function isOpDeleted(postEl) {
		return postEl.classList.contains('opDeleted');
	}

	if (!window.postWidget) return;

	window.postWidget.registerMenuAugmenter(function (ctx) {
		var postEl = ctx && ctx.post;
		if (!postEl) return [];

		var role = roleFor(postEl);
		if (!role) return [];

		var items = [];
		var isOp = postEl.classList.contains('op');

		if (isOp) {
			// the menu is open, so the click on "Thread tools" is likely: ask for the window's state now
			if (document.querySelector('#threadModToolsTemplate')) toolsState(threadUidOf(postEl));

			items.push({
				href: moduleUrl + '&threadUid=' + encodeURIComponent(threadUidOf(postEl)),
				action: 'threadMod_tools',
				label: 'Thread tools',
				subMenu: '',
				params: {}
			});
			return items;
		}

		if (isOpDeleted(postEl)) {
			items.push(entry('undo', 'Undo OP deletion', postEl));
		} else {
			items.push(entry('delete', 'Delete as OP', postEl));
		}

		if (role === 'owner') {
			items.push(entry('grant', 'Give thread mod', postEl));
			items.push(entry('transfer', 'Transfer thread ownership', postEl));
		}

		return items;
	});

	// Only the hand-over asks first: it cannot be taken back by the one who did it
	var CONFIRM = {
		transfer: 'Hand this thread over to this poster? You will stay on as a mod.'
	};

	function send(action, postEl) {
		if (CONFIRM[action] && !window.confirm(CONFIRM[action])) return;

		csrfToken().then(function (token) {
			var body = new URLSearchParams();
			body.append('csrf_token', token);
			body.append('action', action);
			body.append('postUid', postEl.dataset.postUid || '');
			body.append('threadView', isThreadView() ? '1' : '0');

			return fetch(moduleUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'X-Requested-With': 'XMLHttpRequest' },
				body: body
			});
		}).then(async function (res) {
			if (!res.ok) throw await PostActionUtils.responseError(res);
			return res.json();
		}).then(function (data) {
			forgetToolsState(threadUidOf(postEl));
			if (data && data.html) replacePost(postEl, data.html);
			showMessage((data && data.message) || 'Done.', true);
		}).catch(function (err) {
			showMessage(PostActionUtils.errorMessage(err, 'That could not be done.'), false);
		});
	}

	/**
	 * Swap the post for the server's redraw in place, so the collapse is the real one. The reply
	 * container is swapped when the template has one, else the post element itself.
	 */
	function replacePost(postEl, html) {
		var temp = document.createElement('div');
		temp.innerHTML = html;

		var wrapper = postEl.closest('.reply-container');
		var fresh = wrapper ? temp.querySelector('.reply-container') : null;
		var freshPost = temp.querySelector('.post');

		if (wrapper && fresh) {
			wrapper.replaceWith(fresh);
		} else if (freshPost) {
			postEl.replaceWith(freshPost);
			fresh = freshPost;
		} else {
			location.reload();
			return;
		}

		document.dispatchEvent(new CustomEvent('threadMod:replaced', { detail: { post: fresh.matches('.post') ? fresh : fresh.querySelector('.post') } }));
	}

	// ---- the tools window ----

	/** thread uid => {at, promise}: the state asked for when the OP's menu opened, ahead of the click */
	var stateCache = {};
	var STATE_TTL = 30000;

	function toolsState(threadUid) {
		var cached = stateCache[threadUid];
		if (cached && Date.now() - cached.at < STATE_TTL) return cached.promise;

		var promise = fetch(moduleUrl + '&pageName=state&threadUid=' + encodeURIComponent(threadUid), {
			credentials: 'same-origin',
			headers: { 'X-Requested-With': 'XMLHttpRequest' }
		}).then(async function (res) {
			if (!res.ok) throw await PostActionUtils.responseError(res);
			return res.json();
		});

		// a failure is not kept, so the next open asks again
		promise.catch(function () { forgetToolsState(threadUid); });

		stateCache[threadUid] = { at: Date.now(), promise: promise };
		return promise;
	}

	function forgetToolsState(threadUid) {
		delete stateCache[threadUid];
	}

	/**
	 * The tools form, cloned from the blank <template> in the page head and filled from
	 * pageName=state: the page itself may be static html, so it carries nothing about the reader.
	 */
	function openTools(opEl) {
		var threadUid = threadUidOf(opEl);
		if (!threadUid || !document.querySelector('#threadModToolsTemplate')) return;

		var state = toolsState(threadUid);
		forgetToolsState(threadUid);

		PostActionUtils.openWindow({
			templateId: '#threadModToolsTemplate',
			title: 'Thread tools',
			postEl: opEl,
			keepOpen: true,
			onOpen: function (opened) {
				var form = opened.form;
				var win = opened.win;
				if (!form) return;

				form.querySelector('[name="threadUid"]').value = threadUid;

				var picker = form.querySelector('select[name="postNumber"]');
				if (picker) picker.addEventListener('change', function () { showPreview(form, picker); });

				state.then(function (data) {
					fillTools(form, win, data);
				}).catch(function (err) {
					win.remove();
					showMessage(PostActionUtils.errorMessage(err, 'The thread tools could not be opened.'), false);
				});
			},
			onSubmit: async function (ctx) {
				var action = ctx.formData.has('rosterKey') ? 'revoke' : String(ctx.formData.get('action') || '');
				if (CONFIRM[action] && !window.confirm(CONFIRM[action])) return false;

				try {
					ctx.formData.set('csrf_token', await csrfToken());
				} catch (err) {
					showMessage('That could not be done.', false);
					return false;
				}

				ctx.formData.append('threadView', isThreadView() ? '1' : '0');
			},
			onSuccess: function (ctx) {
				var data = ctx.res || {};
				forgetToolsState(threadUid);

				// a hidden or revealed post is swapped on the page when it is drawn here
				var postEl = data.postUid ? document.querySelector('.post[data-post-uid="' + data.postUid + '"]') : null;
				if (postEl && data.html) replacePost(postEl, data.html);

				showMessage(data.message || 'Done.', true);

				// a hand-over or a revoke changes what this browser may do
				discover();

				// the window stays for the next action, reset to what the thread looks like now
				var fresh = toolsState(threadUid);
				forgetToolsState(threadUid);
				fresh.then(function (state) {
					fillTools(ctx.form, ctx.win, state);
				}).catch(function () {
					// no longer this thread's mod
					ctx.win.remove();
				});
			},
			onFail: function (ctx) {
				showMessage(PostActionUtils.errorMessage(ctx.err, 'That could not be done.'), false);
			}
		});
	}

	/** Fill the form from a state answer, replacing whatever an earlier one put there. */
	function fillTools(form, win, data) {
		var name = win.div.querySelector('.winname');
		if (name && data.title) name.textContent = data.title;

		var picker = form.querySelector('select[name="postNumber"]');
		picker.querySelectorAll('option[data-post-uid]').forEach(function (option) { option.remove(); });
		(data.replies || []).forEach(function (reply) {
			var option = document.createElement('option');
			option.value = reply.no;
			option.dataset.postUid = reply.uid;
			option.textContent = reply.label;
			picker.appendChild(option);
		});
		picker.value = '';

		var previewRow = form.querySelector('.threadModPreviewRow');
		previewRow.hidden = true;
		previewRow.querySelector('.threadModPreview').textContent = '';

		var count = form.querySelector('.threadModCount');
		if (count) count.textContent = data.countText || '';

		// a hand-over leaves the old owner a mod, without these
		var isOwner = data.role === 'owner';
		var section = form.querySelector('.threadModRosterSection');
		var table = section.querySelector('.threadModRoster');
		var roster = isOwner ? (data.roster || []) : [];

		form.querySelector('.threadModOwnerButtons').hidden = !isOwner;
		section.hidden = !isOwner;
		section.querySelector('.threadModRosterEmpty').hidden = roster.length > 0;
		table.hidden = roster.length === 0;

		// the first row is the header
		while (table.rows.length > 1) table.deleteRow(1);

		roster.forEach(function (entry) {
			var row = table.insertRow();
			[entry.role, entry.from, entry.since].forEach(function (text) {
				row.insertCell().textContent = text;
			});

			var code = document.createElement('code');
			code.textContent = entry.keyLabel;
			row.insertCell().appendChild(code);

			var revoke = document.createElement('button');
			revoke.type = 'submit';
			revoke.name = 'rosterKey';
			revoke.value = entry.rosterKey;
			revoke.textContent = table.dataset.revokeLabel || 'Revoke';
			row.insertCell().appendChild(revoke);
		});
	}

	/** The picked reply, from the page when it is drawn there, else from the post API. */
	function showPreview(form, picker) {
		var row = form.querySelector('.threadModPreviewRow');
		var cell = row ? row.querySelector('.threadModPreview') : null;
		if (!cell) return;

		var option = picker.selectedOptions[0];
		var postUid = option ? option.dataset.postUid : '';
		cell.textContent = '';
		row.hidden = !postUid;
		if (!postUid) return;

		var onPage = document.querySelector('.post[data-post-uid="' + CSS.escape(postUid) + '"]');
		if (onPage) {
			fillPreview(cell, onPage.cloneNode(true));
			return;
		}

		var apiMeta = document.querySelector('meta[name="postApiUrl"]');
		if (!apiMeta) {
			row.hidden = true;
			return;
		}

		var apiUrl = apiMeta.content;
		var staff = !!document.querySelector('meta[name="postApiStaff"]');
		fetch(apiUrl + (apiUrl.indexOf('?') === -1 ? '?' : '&') + 'post_uid=' + encodeURIComponent(postUid), { credentials: staff ? 'include' : 'omit' })
			.then(function (res) { return res.ok ? res.json() : null; })
			.then(function (data) {
				// the reader may have picked another reply meanwhile
				if (picker.selectedOptions[0] !== option) return;

				var temp = document.createElement('div');
				temp.innerHTML = (data && data.html) || '';
				var post = temp.querySelector('.post');
				if (post) fillPreview(cell, post);
				else row.hidden = true;
			})
			.catch(function () { row.hidden = true; });
	}

	function fillPreview(cell, post) {
		// a copy must not share ids or controls with the real post
		post.removeAttribute('id');
		post.querySelectorAll('[id]').forEach(function (el) { el.removeAttribute('id'); });
		post.querySelectorAll('.deletionCheckbox, input, button').forEach(function (el) { el.remove(); });
		post.classList.add('threadModPreviewPost');

		// a reply the OP deleted is shown in full, so the undo is an informed one
		post.classList.remove('opDeleted');
		post.querySelectorAll('.opDeletedDetails').forEach(function (el) { el.remove(); });

		cell.appendChild(post);
	}

	['delete', 'undo', 'grant', 'transfer'].forEach(function (action) {
		window.postWidget.registerActionHandler('threadMod_' + action, function (ctx) {
			var postEl = ctx && ctx.post;
			if (postEl) send(action, postEl);
		});
	});

	window.postWidget.registerActionHandler('threadMod_tools', function (ctx) {
		var postEl = ctx && ctx.post;
		if (postEl) openTools(postEl);
	});

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', discover);
	} else {
		discover();
	}
})();
