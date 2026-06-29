/* Incremental thread updater.
 *
 * Instead of re-fetching and re-parsing the entire (ever-growing) thread page on every
 * update, ask the post API for only the replies newer than the newest one already on the
 * page. A thread left open for hours used to balloon to gigabytes because each poll
 * downloaded and DOM-parsed the whole thread; now each poll transfers just the new posts.
 */

// Highest post_uid currently rendered in the thread (the OP or any reply). The API returns
// only replies newer than this, so we never re-request posts we already have.
function _twLastKnownPostUid() {
	var max = 0;
	document.querySelectorAll('.thread .post[data-post-uid]').forEach(function (el) {
		var v = parseInt(el.getAttribute('data-post-uid'), 10);
		if (v > max) max = v;
	});
	return max;
}

// Build a reply-container element from an API post's rendered HTML, or null if empty.
function _twBuildReply(html) {
	var tmp = document.createElement('div');
	tmp.innerHTML = (html || '').trim();
	var node = tmp.firstElementChild;
	if (!node) return null;
	// Strip audio so an update never re-triggers autoplay of sound on an existing post.
	node.querySelectorAll('audio').forEach(function (a) { a.remove(); });
	return node;
}

/**
 * Fetch replies newer than what's on the page (via the post API) and append them.
 *
 * Resolves with the array of newly inserted reply elements (empty when there's nothing
 * new). Rejects with 'pruned' when the thread no longer exists, or with another value on
 * a transient network/parse error, so callers can react differently to each.
 */
function fetchNewReplies() {
	return new Promise(function (resolve, reject) {
		var apiMeta = document.querySelector('meta[name="postApiUrl"]');
		var apiUrl = apiMeta ? apiMeta.content : null;
		var op = document.querySelector('.post.op[data-thread-uid]');
		var threadUid = op ? op.getAttribute('data-thread-uid') : null;

		// Without the API or a thread context there's nothing to do.
		if (!apiUrl || !threadUid) {
			resolve([]);
			return;
		}

		var afterUid = _twLastKnownPostUid();
		var sep = apiUrl.indexOf('?') !== -1 ? '&' : '?';
		var url = apiUrl + sep + 'pageName=thread&thread_uid=' + encodeURIComponent(threadUid) +
			'&after_uid=' + encodeURIComponent(afterUid);

		fetch(url).then(function (res) {
			if (res.status === 404) {
				reject('pruned');
				return null;
			}
			if (!res.ok) {
				reject('error');
				return null;
			}
			return res.json();
		}).then(function (data) {
			if (!data) return; // already rejected above
			var posts = Array.isArray(data.posts) ? data.posts : [];

			var inserted = [];
			var frag = document.createDocumentFragment();
			posts.forEach(function (p) {
				if (!p || !p.html) return;
				var node = _twBuildReply(p.html);
				if (!node) return;
				// Guard against inserting a post that's somehow already on the page.
				if (node.id && document.getElementById(node.id)) return;
				frag.appendChild(node);
				inserted.push(node);
			});

			if (inserted.length) {
				var thread = document.querySelector('.thread');
				if (thread) thread.appendChild(frag);
				initNewPosts(inserted);
			}

			resolve(inserted);
		}).catch(function (err) {
			reject(err);
		});
	});
}
