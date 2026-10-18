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

	// ---- the "## OP" checkbox ----

	/** The thread the reply form posts to, on a thread page. */
	function currentThreadUid() {
		if (!isThreadView()) return '';
		var op = document.querySelector('.post.op[data-thread-uid]');
		return op ? op.dataset.threadUid : '';
	}

	function suffix() {
		return ' ## ' + capcodeTrigger;
	}

	function hasSuffix(value) {
		return new RegExp('\\s##\\s+' + capcodeTrigger.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\s*$', 'i').test(value);
	}

	function attachCapcodeBox(nameInput) {
		if (!nameInput || nameInput.dataset.threadModBox) return;
		nameInput.dataset.threadModBox = '1';

		var label = document.createElement('label');
		label.className = 'threadModCapcodeLabel';
		label.title = 'Sign this post as the thread OP';

		var box = document.createElement('input');
		box.type = 'checkbox';
		box.name = 'opCapcode';
		box.className = 'threadModCapcodeBox';
		box.checked = hasSuffix(nameInput.value);

		label.appendChild(box);
		label.appendChild(document.createTextNode(' ##' + capcodeTrigger));
		nameInput.insertAdjacentElement('afterend', label);

		box.addEventListener('change', function () {
			var value = nameInput.value;
			if (box.checked) {
				if (!hasSuffix(value)) nameInput.value = value.replace(/\s+$/, '') + suffix();
			} else {
				nameInput.value = value.replace(new RegExp('\\s*##\\s+' + capcodeTrigger + '\\s*$', 'i'), '');
			}
			nameInput.dispatchEvent(new Event('input', { bubbles: true }));
		});

		nameInput.addEventListener('input', function () {
			box.checked = hasSuffix(nameInput.value);
		});
	}

	function updateCapcodeBoxes() {
		var uid = currentThreadUid();
		var show = !!(uid && roles[uid]);

		['name', 'qrname'].forEach(function (id) {
			var input = document.getElementById(id);
			if (!input) return;
			attachCapcodeBox(input);
			var label = input.nextElementSibling;
			if (label && label.classList.contains('threadModCapcodeLabel')) label.hidden = !show;
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

	['delete', 'undo', 'grant', 'transfer'].forEach(function (action) {
		window.postWidget.registerActionHandler('threadMod_' + action, function (ctx) {
			var postEl = ctx && ctx.post;
			if (postEl) send(action, postEl);
		});
	});

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', discover);
	} else {
		discover();
	}
})();
