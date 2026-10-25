/**
 * threadModAdmin.js - staff side of OP moderation: the "OP tools off" toggle on a thread and the
 * undo entries, which post to the module and redraw.
 */
document.addEventListener('DOMContentLoaded', function () {
	if (!window.postWidget) return;

	window.postWidget.registerActionHandler('threadModStrip', async function (ctx) {
		await handleThreadToggle(ctx, {
			actionName: 'threadModStrip',
			indicatorClass: 'indicator-threadModStrip',
			messageOn: 'OP tools switched off for this thread.',
			messageOff: 'OP tools switched back on.',
			labelOn: 'Enable OP tools',
			labelOff: 'Disable OP tools'
		});
	});

	/** Swap a post for the server's redraw: the reply container when the template has one, else the post. */
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
		}
	}

	function post(ctx, confirmText) {
		if (confirmText && !window.confirm(confirmText)) return;

		var token = document.querySelector('meta[name="csrf-token"]');
		var body = new URLSearchParams();
		body.append('csrf_token', token ? token.content : '');
		body.append('threadView', new URLSearchParams(location.search).has('res') ? '1' : '0');
		Object.keys(ctx.params || {}).forEach(function (key) { body.append(key, ctx.params[key]); });

		fetch(ctx.url, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'X-Requested-With': 'XMLHttpRequest' },
			body: body
		}).then(async function (res) {
			if (!res.ok) throw await PostActionUtils.responseError(res);
			return res.json();
		}).then(function (data) {
			var posts = (data && data.posts) || {};
			var count = data && data.count !== undefined ? data.count : Object.keys(posts).length;

			Object.keys(posts).forEach(function (uid) {
				var postEl = document.querySelector('.post[data-post-uid="' + uid + '"]');
				if (postEl) replacePost(postEl, posts[uid]);
			});

			showMessage(count === 1 ? 'Revealed 1 post.' : 'Revealed ' + count + ' posts.', true);
		}).catch(function (err) {
			showMessage(PostActionUtils.errorMessage(err, 'That could not be done.'), false);
		});
	}

	window.postWidget.registerActionHandler('threadModUndo', function (ctx) { post(ctx, ''); });
	window.postWidget.registerActionHandler('threadModUndoAll', function (ctx) {
		post(ctx, 'Reveal every post the OP hid in this thread?');
	});
});
