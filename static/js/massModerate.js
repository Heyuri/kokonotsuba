/**
 * massModerate.js — the [Moderate] window for the post checkboxes.
 *
 * The whole list comes from the #massModerateTemplate <template> that PHP renders for staff, so
 * opening the window is a clone, never a fetch. Each entry carries its endpoint and its effect as
 * data attributes; this file only knows how to post a selection and how to reflect the result.
 *
 * Depends on: koko.js (kkwmWindow), postDeletionLib.js, message.js
 */
(function () {
	'use strict';

	var windowName = 'Moderate';

	var template = null;
	var bar = null;

	// the open window and its content, so ticking a box while it is up updates what it offers
	var openWin = null;
	var openContent = null;

	function csrfToken() {
		var meta = document.querySelector('meta[name="csrf-token"]');
		return meta ? meta.content : '';
	}

	function notify(text, ok) {
		if (typeof showMessage === 'function') showMessage(text, ok);
	}

	function formatMessage(text, label, count) {
		return String(text).split('{action}').join(label).split('{count}').join(count);
	}

	// ---- selection ----

	// posts: every checked box. threads: the checked boxes that are opening posts.
	function getSelection() {
		var posts = [];
		var threads = [];
		var deleted = 0;

		document.querySelectorAll('.deletionCheckbox:checked').forEach(function (box) {
			var postEl = box.closest('.post');
			if (!postEl) return;

			var entry = { uid: box.name, el: postEl };
			posts.push(entry);
			if (postEl.classList.contains('op')) threads.push(entry);
			if (postEl.classList.contains('deletedPost')) deleted++;
		});

		return { posts: posts, threads: threads, deleted: deleted };
	}

	function clearSelection(targets) {
		targets.forEach(function (target) {
			var box = target.el.querySelector('.deletionCheckbox');
			if (box) box.checked = false;
		});
		selectionChanged();
	}

	function selectionChanged() {
		syncBar();
		refreshWindow();
	}

	function syncBar() {
		if (!bar) return;
		var anyChecked = document.querySelector('.deletionCheckbox:checked') !== null;
		bar.classList.toggle('massModerateHidden', !anyChecked);
	}

	// ---- window ----

	function existingWindow() {
		return (typeof window.$kkwm_name === 'function') ? window.$kkwm_name(windowName) : null;
	}

	function openWindow() {
		var selection = getSelection();
		if (!selection.posts.length) {
			notify(template.dataset.noneSelected, false);
			return;
		}

		closeWindow();

		var open = existingWindow();
		if (open) {
			open.remove();
		}

		// kkwmWindow places the window itself, and prefers wherever it was last dragged to; these
		// are only the fallback coordinates, the corner the checkboxes already work in.
		var win = new kkwmWindow(windowName, defaultRect());
		if (!win.div) return;

		var title = win.div.querySelector('.winname');
		if (title) title.textContent = template.dataset.title;

		var body = win.div.querySelector('.windbody') || win.div;
		var content = template.content.querySelector('.massModerateWindow').cloneNode(true);
		body.appendChild(content);

		openWin = win;
		openContent = content;

		// closing runs 99ms late, by which time this may no longer be the window that is up
		win.onclose = function () {
			if (openWin === win) {
				openWin = null;
				openContent = null;
			}
		};

		bindList(content);
		refreshWindow();
	}

	function closeWindow() {
		if (!openWin) return;

		var win = openWin;
		openWin = null;
		openContent = null;
		win.remove();
	}

	function defaultRect() {
		var margin = 20;
		var width = 240;
		var height = 260;
		var viewportWidth = window.innerWidth || document.documentElement.clientWidth;
		var viewportHeight = window.innerHeight || document.documentElement.clientHeight;

		return {
			x: Math.max(margin, viewportWidth - width - margin),
			y: Math.max(margin, viewportHeight - height - margin),
			w: width,
			h: height
		};
	}

	function bindList(content) {
		content.querySelector('.massModerateControls').classList.add('massModerateHidden');

		content.querySelectorAll('.massModerateItem a[data-mm-action]').forEach(function (link) {
			link.addEventListener('click', function (ev) {
				ev.preventDefault();
				startTool(link, content);
			});
		});
	}

	/**
	 * Bring the open window in line with what is ticked right now: the counts, which entries can do
	 * anything, and which group headings still have entries under them.
	 */
	function refreshWindow() {
		if (!openContent) return;

		var selection = getSelection();

		// nothing left to act on — the bar is gone too, so the window follows it
		if (!selection.posts.length) {
			closeWindow();
			return;
		}

		var count = openContent.querySelector('.massModerateCount');
		var threadCount = openContent.querySelector('.massModerateThreadCount');
		if (count) count.textContent = selection.posts.length;
		if (threadCount) threadCount.textContent = selection.threads.length;

		openContent.querySelectorAll('.massModerateItem').forEach(function (item) {
			var link = item.querySelector('a[data-mm-action]');
			if (!link) return;

			// A thread action with no thread selected, or a deleted-posts action with nothing
			// deleted in the selection, has nothing to act on.
			var unusable = (link.dataset.mmScope === 'thread' && !selection.threads.length)
				|| (link.dataset.mmRequires === 'deleted' && !selection.deleted);

			item.classList.toggle('massModerateHidden', unusable);
		});

		openContent.querySelectorAll('.massModerateGroup').forEach(function (group) {
			var usable = group.querySelector('.massModerateItem:not(.massModerateHidden)');
			group.classList.toggle('massModerateHidden', !usable);
		});
	}

	// ---- running a tool ----

	function startTool(link, content) {
		var formId = link.dataset.mmForm;

		if (formId) {
			showToolForm(link, content, formId);
			return;
		}

		var confirmText = link.dataset.mmConfirm;
		var targets = targetsFor(link, getSelection());
		if (confirmText && !window.confirm(formatMessage(confirmText, link.textContent, targets.length))) return;

		runTool(link, content, {});
	}

	// Tools that need more than a selection (a destination board, a reason) name a <template> of
	// their own; it is swapped in over the list and its fields ride along with the request.
	function showToolForm(link, content, formId) {
		var formTemplate = document.getElementById(formId);
		if (!formTemplate) {
			notify(template.dataset.failed, false);
			return;
		}

		var list = content.querySelector('.massModerateList');
		var extra = content.querySelector('.massModerateExtra');
		var controls = content.querySelector('.massModerateControls');

		extra.textContent = '';
		extra.appendChild(formTemplate.content.cloneNode(true));
		list.classList.add('massModerateHidden');
		controls.classList.remove('massModerateHidden');

		controls.querySelector('.massModerateBack').onclick = function () {
			extra.textContent = '';
			list.classList.remove('massModerateHidden');
			controls.classList.add('massModerateHidden');
		};

		controls.querySelector('.massModerateApply').onclick = function () {
			runTool(link, content, collectFields(extra));
		};
	}

	function collectFields(container) {
		var values = {};

		container.querySelectorAll('input[name], select[name], textarea[name]').forEach(function (field) {
			if ((field.type === 'checkbox' || field.type === 'radio') && !field.checked) return;
			values[field.name] = field.value;
		});

		return values;
	}

	function targetsFor(link, selection) {
		return link.dataset.mmScope === 'thread' ? selection.threads : selection.posts;
	}

	function runTool(link, content, extraParams) {
		// read the selection at click time, not at open time: it may have moved on since
		var targets = targetsFor(link, getSelection());
		if (!targets.length) {
			notify(template.dataset.noThreads, false);
			return;
		}

		var body = new URLSearchParams();
		body.append('csrf_token', csrfToken());

		var params = JSON.parse(link.dataset.mmParams || '{}');
		Object.keys(params).forEach(function (key) { body.append(key, params[key]); });
		Object.keys(extraParams).forEach(function (key) { body.append(key, extraParams[key]); });
		targets.forEach(function (target) { body.append('post_uids[]', target.uid); });

		content.classList.add('massModerateBusy');

		fetch(link.dataset.mmUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'X-Requested-With': 'XMLHttpRequest' },
			body: body
		}).then(function (res) {
			return res.json().catch(function () { return null; }).then(function (data) {
				return { ok: res.ok, data: data };
			});
		}).then(function (result) {
			content.classList.remove('massModerateBusy');

			if (!result.ok) {
				notify((result.data && result.data.message) || template.dataset.failed, false);
				return;
			}

			if (result.data && result.data.redirectUrl) {
				window.location.assign(result.data.redirectUrl);
				return;
			}

			applyEffect(link, targets, result.data || {});
			closeWindow();
			clearSelection(targets);
			notify(formatMessage(template.dataset.done, link.textContent, targets.length), true);
		}).catch(function () {
			content.classList.remove('massModerateBusy');
			notify(template.dataset.failed, false);
		});
	}

	// ---- effects ----

	function applyEffect(link, targets, data) {
		var effect = link.dataset.mmEffect;
		if (effect === 'reload') {
			window.location.reload();
			return;
		}

		var results = data.results || {};

		targets.forEach(function (target) {
			var result = results[target.uid] || {};

			switch (effect) {
				case 'delete':
					markDeleted(target.el, result);
					break;
				case 'restore':
					markRestored(target.el);
					break;
				case 'purge':
					if (typeof fadeAndRemovePost === 'function') fadeAndRemovePost(target.el);
					break;
				case 'flag':
					setIndicator(target.el, link.dataset.mmIndicator, result.active);
					break;
			}
		});
	}

	// Staff who can see deleted posts keep them on the page with the [DELETED] marker; everyone
	// else loses the post, which is what the indicator's absence tells us.
	function markDeleted(postEl, result) {
		if (!postEl.querySelector('.postInfoExtra .indicator-deleted')) {
			if (typeof fadeAndRemovePost === 'function') fadeAndRemovePost(postEl);
			return;
		}

		var thread = postEl.classList.contains('op') ? postEl.closest('.thread') : null;

		if (thread) {
			thread.classList.add('deletedPost');
			thread.querySelectorAll('.post').forEach(function (post) {
				showDeletionIndicator(post, 'post');
			});
		} else {
			postEl.classList.add('deletedPost');
			showDeletionIndicator(postEl, 'post');
		}

		if (result.deleted_link) postEl.dataset.deletedLink = result.deleted_link;
		if (result.deleted_post_id) postEl.dataset.deletedPostId = result.deleted_post_id;
		if (typeof removeWidgetActions === 'function') removeWidgetActions(postEl, ['delete', 'mute']);
	}

	function markRestored(postEl) {
		var thread = postEl.closest('.thread');
		if (postEl.classList.contains('op') && thread) {
			thread.classList.remove('deletedPost');
			thread.querySelectorAll('.post').forEach(function (post) {
				post.classList.remove('deletedPost');
				setIndicator(post, 'indicator-deleted', false);
			});
			return;
		}

		postEl.classList.remove('deletedPost');
		setIndicator(postEl, 'indicator-deleted', false);
	}

	function setIndicator(postEl, indicatorClass, active) {
		if (!indicatorClass) return;

		var extra = postEl.querySelector('.postInfoExtra');
		var indicator = extra ? extra.querySelector('.' + indicatorClass) : null;
		if (!indicator) return;

		indicator.classList.toggle('indicatorHidden', !active);
	}

	// ---- setup ----

	document.addEventListener('DOMContentLoaded', function () {
		template = document.getElementById('massModerateTemplate');
		if (!template || !document.querySelector('.deletionCheckbox')) return;

		bar = template.content.querySelector('.massModerateBar').cloneNode(true);
		bar.classList.add('massModerateHidden');

		// Above the password field, inside the block that already floats bottom-right.
		var userDelete = document.getElementById('userdelete');
		if (userDelete) {
			userDelete.insertBefore(bar, userDelete.firstChild);
		} else {
			// no delete form on this template — put it in the same corner on its own
			bar.classList.add('massModerateFloating');
			document.body.appendChild(bar);
		}

		bar.querySelector('.massModerateOpen').addEventListener('click', function (ev) {
			ev.preventDefault();
			openWindow();
		});

		// click as well as change: checkboxDeletion.js fills in ranges on shift-click, which fires
		// neither event on the boxes it sets
		document.addEventListener('click', function (ev) {
			if (ev.target.classList && ev.target.classList.contains('deletionCheckbox')) selectionChanged();
		});
		document.addEventListener('change', function (ev) {
			if (ev.target.classList && ev.target.classList.contains('deletionCheckbox')) selectionChanged();
		});

		syncBar();
	});
})();
