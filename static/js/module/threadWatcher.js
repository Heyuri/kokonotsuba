/* Thread watcher module
 * Watches threads for new replies using the postApi module.
 * Stores watched threads in localStorage.
 * Uses browser notifications for unread replies.
 */

const kktwch = { name: "KK Thread watcher",
	STORAGE_KEY: 'threadWatcher',
	POLL_INTERVAL: 15000,
	// How long to wait before retrying a manual refresh that failed to reach the server.
	REFRESH_RETRY_DELAY: 3000,
	_pollTimer: null,
	_win: null,
	// True while a poll's fetch is in flight in this tab (locks the refresh button).
	_pollInProgress: false,
	// track refresh pending state and retry timer
	_refreshPending: false,
	_refreshRetryTimer: null,
	// Ticks the "last updated" label while the window is open.
	_updatedTimer: null,

	startup: function () {
		// Hook the admin-bar top-link that opens the watcher window
		var toplink = $id('threadWatcherToplink');
		if (toplink) {
			toplink.addEventListener('click', function (e) {
				e.preventDefault();
				kktwch.toggleWindow();
			});
		}

		// Wire up the pre-rendered watch stars on opening posts (delegated so it also
		// covers threads inserted later, e.g. by the overboard/live updates), and reflect
		// each thread's current watched state onto its star.
		kktwch.initStars();

		// Auto-watch on reply submission
		kktwch.hookFormSubmit();

		// Track posts scrolling into view on watched thread pages
		kktwch.initViewportTracking();

		// Track which watched threads scroll into view on index/overboard pages, seeding
		// the initially-visible ones so the first poll's notifications are correct.
		kktwch.initIndexViewportTracking();

		// Threads already scrolled into view on this index/overboard page count as seen —
		// mark them read before reflecting unread counts in the title.
		kktwch.markVisibleThreadsAsRead();

		// Reflect any existing unread counts in the title before the first poll lands
		kktwch.updatePageTitle();

		// Keep tabs/subdomains that didn't poll in sync: when another tab updates the
		// watched threads (locally or via the shared hub), refresh this tab's
		// list/title/top-link without re-notifying.
		kkStore.onChange(function (key) {
			// Another tab polled: reflect the new "last updated" time here too.
			if (key === 'kktwch_lastUpdated') {
				kktwch.updateUpdatedLabel();
				return;
			}
			if (key !== kktwch.STORAGE_KEY) return;
			kktwch.renderWatchList();
			kktwch.updatePageTitle();
			// The watch list changed elsewhere (another tab/subdomain): re-sync stars.
			kktwch.syncStars();
		});

		// The shared store loads asynchronously: once its snapshot has been merged
		// in (it may carry threads watched on another subdomain), refresh the UI.
		kkStore.onReady(function () {
			kktwch.renderWatchList();
			kktwch.updatePageTitle();
			kktwch.syncStars();
			// Pick up counts for any threads adopted from another subdomain
			// (respects the cross-tab poll lock inside checkAllThreads).
			kktwch.checkAllThreads();
		});

		// Start polling
		kktwch.startPolling();

		return true;
	},

	reset: function () {
		kktwch.stopPolling();
		kktwch.stopUpdatedTicker();
		kktwch.clearRefreshPending();
		if (kktwch._viewportObserver) {
			kktwch._viewportObserver.disconnect();
			kktwch._viewportObserver = null;
		}
		if (kktwch._viewportMutObserver) {
			kktwch._viewportMutObserver.disconnect();
			kktwch._viewportMutObserver = null;
		}
		if (kktwch._bottomScrollHandler) {
			window.removeEventListener('scroll', kktwch._bottomScrollHandler);
			kktwch._bottomScrollHandler = null;
		}
		if (kktwch._indexViewportObserver) {
			kktwch._indexViewportObserver.disconnect();
			kktwch._indexViewportObserver = null;
		}
		kktwch._indexObservedEls = null;
		kktwch._indexSeenThreads = {};
		kktwch._viewportThreadUid = null;
		kktwch._viewportThreadEl = null;
		kktwch._maxSeenIndex = -1;
		if (kktwch._win) {
			kktwch._win.remove();
			kktwch._win = null;
		}
	},

	/* --- Storage --- */

	getWatchedThreads: function () {
		try {
			var data = localStorage.getItem(kktwch.STORAGE_KEY);
			return data ? JSON.parse(data) : {};
		} catch (e) {
			return {};
		}
	},

	saveWatchedThreads: function (threads) {
		kkStore.set(kktwch.STORAGE_KEY, JSON.stringify(threads));
	},

	// The user's own posts, recorded at post time as a set of "board_no" keys (see posting.js).
	// Returned as "board:no" tokens for the counts endpoint's `you` parameter.
	getOwnPostTokens: function () {
		try {
			var data = JSON.parse(localStorage.getItem('kkOwnPosts') || '{}');
			return Object.keys(data).map(function (key) {
				return key.replace('_', ':');
			});
		} catch (e) {
			return [];
		}
	},

	// True if the given board UID + post number is one of the user's own posts.
	// Used to suppress new-thread notifications for threads the user just created.
	isOwnPost: function (boardUid, postNo) {
		if (!boardUid || !postNo) return false;
		try {
			var data = JSON.parse(localStorage.getItem('kkOwnPosts') || '{}');
			return data.hasOwnProperty(boardUid + '_' + postNo);
		} catch (e) {
			return false;
		}
	},

	getMyNewestOwnPostOp: function () {
		var ownPosts;
		try {
			ownPosts = JSON.parse(localStorage.getItem('kkOwnPosts') || '{}');
		} catch (e) {
			return null;
		}
		var newestKey = null, newestTime = -1;
		Object.keys(ownPosts).forEach(function (key) {
			var t = ownPosts[key];
			if (typeof t === 'number' && t > newestTime) {
				newestTime = t;
				newestKey = key;
			}
		});
		if (!newestKey) return null;
		// kkOwnPosts keys are "board_no"; the OP element id is "p{board}_{no}".
		var op = document.getElementById('p' + newestKey);
		return (op && op.classList.contains('op')) ? op : null;
	},

	/* --- Watch/Unwatch --- */

	watchCurrentThread: function (threadUid) {
		if (!threadUid) return;

		var watched = kktwch.getWatchedThreads();
		if (watched[threadUid]) return; // already watching

		// Gather thread info from the page
		var info = kktwch.getThreadInfoFromPage(threadUid);

		// Count current posts on page (only a preview is shown on the index, so this
		// undercounts). lastSeenCount stays null and is seeded from the API's real count
		// on the first poll, so the whole thread isn't treated as unread when watched.
		var currentPostCount = info.postCount || 0;

		watched[threadUid] = {
			threadUid: threadUid,
			subject: info.subject || 'No.' + (info.threadNo || threadUid),
			boardTitle: info.boardTitle || '',
			label: '',
			threadNo: info.threadNo || '',
			boardUrl: info.boardUrl || '',
			boardId: info.boardId || '',
			postCount: currentPostCount,
			// null until the first poll, when it's seeded to the real post count
			// (everything that exists at watch time counts as already read).
			lastSeenCount: null,
			quoteCount: 0,
			// null until the first poll, so pre-existing quotes aren't flagged as new.
			seenQuoteCount: null,
			lastChecked: Date.now(),
			// When the thread was first watched; used to order the watch list
			// most-recent-first (Object.keys order is unreliable for numeric uids).
			watchedAt: Date.now(),
			url: info.url || ''
		};

		kktwch.saveWatchedThreads(watched);
		kktwch.requestNotificationPermission();
	},

	unwatchThread: function (threadUid) {
		var watched = kktwch.getWatchedThreads();
		delete watched[threadUid];
		kktwch.saveWatchedThreads(watched);
	},

	/* --- Watch stars (pre-rendered toggles on opening posts) --- */

	// Toggle watch state when a star is clicked. Delegated on the document so stars in
	// threads inserted after load (overboard, live updates) work without re-binding.
	initStars: function () {
		document.addEventListener('click', function (e) {
			var star = e.target.closest ? e.target.closest('.threadWatchStar') : null;
			if (!star) return;
			e.preventDefault();

			var threadUid = star.getAttribute('data-thread-uid');
			if (!threadUid) return;

			var watched = kktwch.getWatchedThreads();
			if (watched.hasOwnProperty(threadUid)) {
				kktwch.unwatchThread(threadUid);
				kktwch.showWatchMessage(false);
			} else {
				kktwch.watchCurrentThread(threadUid);
				kktwch.showWatchMessage(true);
			}
			kktwch.syncStars();
			kktwch.renderWatchList();
		});

		// Reflect current state onto whatever stars are already on the page.
		kktwch.syncStars();
	},

	// Toast feedback for an explicit watch/unwatch action (guarded in case message.js
	// isn't loaded on this page).
	showWatchMessage: function (isWatched) {
		if (typeof showMessage === 'function') {
			showMessage(isWatched ? 'Thread watched' : 'Thread unwatched', true);
		}
	},

	// Fill in / hollow out every star on the page to match the watch list, and keep its
	// label in sync ("Watch thread" vs "Unwatch thread").
	syncStars: function () {
		var stars = document.querySelectorAll('.threadWatchStar');
		if (!stars.length) return;

		var watched = kktwch.getWatchedThreads();
		var watchLabel = document.querySelector('meta[name="threadWatcherWatchLabel"]')?.content || 'Watch thread';
		var unwatchLabel = document.querySelector('meta[name="threadWatcherUnwatchLabel"]')?.content || 'Unwatch thread';

		stars.forEach(function (star) {
			var uid = star.getAttribute('data-thread-uid');
			if (!uid) return;
			var isWatched = watched.hasOwnProperty(uid);
			var label = isWatched ? unwatchLabel : watchLabel;

			star.classList.toggle('twStarWatched', isWatched);
			star.setAttribute('aria-pressed', isWatched ? 'true' : 'false');
			star.setAttribute('title', label);
			star.setAttribute('aria-label', label);
		});
	},

	/* --- Thread Info Extraction --- */

	getThreadInfoFromPage: function (threadUid) {
		var info = { postCount: 0, subject: '', threadNo: '', boardUrl: '', boardId: '', url: '', boardTitle: '' };

		// Find the thread container
		var threadEl = document.querySelector('.thread[data-thread-uid="' + threadUid + '"]') ||
		               document.querySelector('.post.op[data-thread-uid="' + threadUid + '"]')?.closest('.thread');

		if (!threadEl) {
			// We might be inside the thread page itself
			var opPost = document.querySelector('.post.op[data-thread-uid="' + threadUid + '"]');
			if (opPost) {
				threadEl = opPost.closest('.thread') || opPost.parentElement;
			}
		}

		if (threadEl) {
			// Get post count (OP + replies visible)
			var posts = threadEl.querySelectorAll('.post');
			info.postCount = posts.length;

			// On the overboard each thread is labelled with its board title; grab it so
			// the watch list shows "Board - Subject" immediately (the poll refines it later).
			var boardTitleEl = threadEl.querySelector('.overboardThreadBoardTitle');
			if (boardTitleEl && boardTitleEl.textContent.trim()) {
				info.boardTitle = boardTitleEl.textContent.trim();
			}
		}

		// On a single-board page (thread page or board index) there's no per-thread board
		// label; the watcher emits the current board's title as a meta tag instead. Used as
		// a fallback so watching from a thread page also shows "Board - Subject" right away.
		if (!info.boardTitle) {
			var boardTitleMeta = document.querySelector('meta[name="boardTitle"]');
			if (boardTitleMeta && boardTitleMeta.content.trim()) {
				info.boardTitle = boardTitleMeta.content.trim();
			}
		}

		// Get subject from OP
		var opPost = document.querySelector('.post.op[data-thread-uid="' + threadUid + '"]');
		if (opPost) {
			var subEl = opPost.querySelector('.title');
			if (subEl && subEl.textContent.trim()) {
				info.subject = subEl.textContent.trim();
			}

			// Get thread number from element ID (format: p{boardUid}_{no})
			var postId = opPost.id;
			if (postId) {
				var match = postId.match(/^p(\d+)_(\d+)$/);
				if (match) {
					info.threadNo = match[2];
					info.boardId = match[1];
				}
			}

			// Get the reply link for the URL
			var replyLink = opPost.querySelector('.replyButton a');
			if (replyLink) {
				info.url = replyLink.href;
			}

			// Try the post number link
			if (!info.url) {
				var postNumLink = opPost.querySelector('.postnum a.no');
				if (postNumLink) {
					info.url = postNumLink.href;
				}
			}
		}

		// Get board URL from page
		var boardUrlMeta = document.querySelector('meta[name="boardUrl"]');
		if (boardUrlMeta) {
			info.boardUrl = boardUrlMeta.content;
		}

		return info;
	},

	/* --- Form Submit Hook (auto-watch on reply and new thread) --- */

	hookFormSubmit: function () {
		var form = $id('postform');
		if (!form) return;

		// On page load, check if we just created a new thread and should auto-watch it
		// (governed by its own setting, enabled by default). Always clear the flag.
		if (sessionStorage.getItem('twAutoWatch')) {
			sessionStorage.removeItem('twAutoWatch');
			if (_kkSetting('threadWatcherAutoWatchOwnThreads')) {
				// Pin to the thread we actually made (see getMyNewestOwnPostOp). Only
				// fall back to the sole OP when we're on a single-thread page, where
				// ".post.op" is unambiguous - never on the index, where it would watch
				// whatever thread happens to be on top instead of ours.
				var opPost = kktwch.getMyNewestOwnPostOp();
				if (!opPost && document.querySelector('#postform input[name="resto"]')) {
					opPost = document.querySelector('.post.op');
				}
				if (opPost) {
					var threadUid = kktwch.threadUidOfElement(opPost);
					if (threadUid) {
						var watched = kktwch.getWatchedThreads();
						if (!watched[threadUid]) {
							kktwch.watchCurrentThread(threadUid);
							kktwch.renderWatchList();
							// Fill the new thread's star now that it's watched.
							kktwch.syncStars();
						}
					}
				}
			}
		}

		form.addEventListener('submit', function () {
			var restoInput = form.querySelector('input[name="resto"]');

			if (!restoInput || !restoInput.value) {
				// New thread: set flag so we auto-watch after redirect (own-threads setting).
				if (_kkSetting('threadWatcherAutoWatchOwnThreads')) sessionStorage.setItem('twAutoWatch', '1');
				return;
			}

			// Reply: watch immediately
			var threadUid = kktwch.currentThreadUid();
			if (!threadUid) return;

			var watched = kktwch.getWatchedThreads();
			// Auto-watch on reply is optional (enabled by default). When off, an
			// unwatched thread stays unwatched; an already-watched one still gets
			// marked read below.
			if (!watched[threadUid] && _kkSetting('threadWatcherAutoWatch')) {
				kktwch.watchCurrentThread(threadUid);
				watched = kktwch.getWatchedThreads();
			}

			// Replying means you've engaged with the thread, so treat it as fully read.
			// Reset to the unseeded state so the next poll seeds lastSeenCount to the real
			// post count — this works regardless of the current view (full thread, a later
			// page, or "view last X replies", which only render a subset of posts) and also
			// stops our own pending reply from being counted as unread.
			var entry = watched[threadUid];
			if (entry) {
				entry.lastSeenCount = null;
				kktwch.saveWatchedThreads(watched);
			}
		});
	},

	/* --- Viewport Read Tracking --- */

	_viewportObserver: null,
	_viewportMutObserver: null,
	_viewportThreadUid: null,
	_viewportThreadEl: null,
	_maxSeenIndex: -1,
	// Scroll listener (+ its rAF throttle flag) used to detect reaching the page bottom.
	_bottomScrollHandler: null,
	_bottomScrollScheduled: false,

	// Index/overboard read tracking. A watched thread rendered on the index only counts as
	// "seen" once it has actually scrolled into the viewport — so threads sitting below the
	// fold still notify for new replies instead of being silently marked read.
	_indexViewportObserver: null,
	_indexObservedEls: null,
	// Set (as a plain object) of thread UIDs that have entered the viewport this page load.
	_indexSeenThreads: {},

	initViewportTracking: function () {
		// Only run on thread pages
		var threadUid = kktwch.currentThreadUid();
		if (!threadUid) return;

		var opPost = document.querySelector('.post.op');
		if (!opPost) return;

		var watched = kktwch.getWatchedThreads();
		if (!watched[threadUid]) return;

		var threadEl = opPost.closest('.thread') || opPost.parentElement;
		if (!threadEl) return;

		kktwch._viewportThreadUid = threadUid;
		kktwch._viewportThreadEl = threadEl;
		// Track read progress by the furthest-read post's DOM position rather than a raw
		// count of posts that scrolled by. The thread page renders every post in order, so
		// "seen up to position k (0-based)" means k+1 posts have been read. Using the max
		// position keeps read tracking correct even when the user enters partway down the
		// thread (e.g. via the first-unread anchor) and only scrolls the lower portion.
		kktwch._maxSeenIndex = -1;

		kktwch._viewportObserver = new IntersectionObserver(function (entries) {
			var advanced = false;
			entries.forEach(function (entry) {
				if (!entry.isIntersecting) return;
				var idx = kktwch.postIndexInThread(entry.target);
				if (idx > kktwch._maxSeenIndex) {
					kktwch._maxSeenIndex = idx;
					advanced = true;
				}
				// A post counts as read once; stop observing it.
				kktwch._viewportObserver.unobserve(entry.target);
			});
			if (advanced) kktwch.commitViewportProgress();
		}, {
			// threshold 0 + a bottom margin means a post is "seen" once it's scrolled up
			// past the bottom fifth of the viewport. threshold 0 (rather than 0.5) also
			// handles posts taller than the viewport, which can never be 50% visible.
			threshold: 0,
			rootMargin: '0px 0px -20% 0px'
		});

		kktwch.observeThreadPosts();

		// Live threads grow (the user's own reply, fetched replies). Observe any posts
		// inserted after load so reading them still advances the read marker.
		kktwch._viewportMutObserver = new MutationObserver(function () {
			kktwch.observeThreadPosts();
		});
		kktwch._viewportMutObserver.observe(threadEl, { childList: true });

		// The IntersectionObserver's bottom margin means the very last reply may never
		// scroll above the "seen" line (there's nothing below it to push it up), so it
		// would never be marked read. Detect reaching the bottom of the page and snap the
		// read marker to the last post. Throttled to once per frame.
		kktwch._bottomScrollHandler = function () {
			if (kktwch._bottomScrollScheduled) return;
			kktwch._bottomScrollScheduled = true;
			requestAnimationFrame(function () {
				kktwch._bottomScrollScheduled = false;
				kktwch.checkScrolledToBottom();
			});
		};
		window.addEventListener('scroll', kktwch._bottomScrollHandler, { passive: true });

		// A short thread may already be fully visible with nothing to scroll — treat it as
		// read. Wait for full load first so image heights are settled; otherwise an
		// image-heavy thread can measure short mid-load and be marked read prematurely.
		if (document.readyState === 'complete') {
			kktwch.checkScrolledToBottom();
		} else {
			window.addEventListener('load', function () {
				kktwch.checkScrolledToBottom();
			}, { once: true });
		}
	},

	// When the page is scrolled to (near) the bottom, every rendered post has been passed,
	// so mark the last one seen. This reliably clears the tail of the thread, which the
	// IntersectionObserver's bottom margin can otherwise leave perpetually unread.
	checkScrolledToBottom: function () {
		if (!kktwch._viewportThreadEl) return;

		var docHeight = document.documentElement.scrollHeight;
		var viewportBottom = window.innerHeight + window.scrollY;
		if (viewportBottom < docHeight - 100) return;

		var lastIdx = kktwch._viewportThreadEl.querySelectorAll('.post').length - 1;
		if (lastIdx > kktwch._maxSeenIndex) kktwch._maxSeenIndex = lastIdx;
		kktwch.commitViewportProgress();
	},

	/* --- Index/overboard viewport read tracking --- */

	// On index/overboard pages, watch which rendered threads actually scroll into view.
	// Only those count as "seen" (see markVisibleThreadsAsRead), so a watched thread below
	// the fold keeps notifying for new replies until the user scrolls to it.
	initIndexViewportTracking: function () {
		// Thread pages track individual posts instead (see initViewportTracking).
		if (kktwch.onThreadPage()) return;

		kktwch._indexSeenThreads = {};
		kktwch._indexObservedEls = ('WeakSet' in window) ? new WeakSet() : null;

		if (!('IntersectionObserver' in window)) {
			// No observer support: fall back to the old behavior (rendered == seen) so
			// threads still get marked read rather than notifying forever.
			var w = kktwch.getWatchedThreads();
			Object.keys(w).forEach(function (uid) { kktwch._indexSeenThreads[uid] = true; });
			return;
		}

		kktwch._indexViewportObserver = new IntersectionObserver(function (entries) {
			var changed = false;
			entries.forEach(function (entry) {
				if (!entry.isIntersecting) return;
				var uid = kktwch.threadUidOfElement(entry.target);
				if (uid && !kktwch._indexSeenThreads[uid]) {
					kktwch._indexSeenThreads[uid] = true;
					changed = true;
				}
				kktwch._indexViewportObserver.unobserve(entry.target);
			});
			if (!changed) return;
			// A thread just became visible — mark it (and any others now qualifying) read.
			if (kktwch.markVisibleThreadsAsRead()) kktwch.renderWatchList();
			kktwch.updatePageTitle();
		}, {
			// Match the thread-page reader: a thread only counts once it's up past the
			// bottom fifth of the viewport, not the instant it peeks in from the bottom.
			threshold: 0,
			rootMargin: '0px 0px -20% 0px'
		});

		kktwch.observeIndexThreads();
	},

	// Observe each watched thread element on the page that isn't observed yet. The
	// IntersectionObserver decides what's actually visible (accounting for layout as images
	// load), rather than a getBoundingClientRect snapshot that can misjudge an unlaid-out
	// page at startup and wrongly mark a below-the-fold thread as seen.
	observeIndexThreads: function () {
		if (!kktwch._indexViewportObserver) return;
		var watched = kktwch.getWatchedThreads();
		Object.keys(watched).forEach(function (uid) {
			var el = kktwch.indexThreadElement(uid);
			if (!el) return;
			if (kktwch._indexObservedEls && kktwch._indexObservedEls.has(el)) return;
			if (kktwch._indexObservedEls) kktwch._indexObservedEls.add(el);
			kktwch._indexViewportObserver.observe(el);
		});
	},

	// The .thread wrapper (preferred) or OP element for a watched thread on this page.
	indexThreadElement: function (uid) {
		var el = document.querySelector('.thread[data-thread-uid="' + uid + '"]');
		if (el) return el;
		var op = document.querySelector('.post.op[data-thread-uid="' + uid + '"]');
		return op ? (op.closest('.thread') || op) : null;
	},

	threadUidOfElement: function (el) {
		if (!el) return null;
		return el.getAttribute('data-thread-uid') ||
			(el.querySelector('.post.op[data-thread-uid]')?.getAttribute('data-thread-uid')) ||
			(el.closest('.thread')?.getAttribute('data-thread-uid')) || null;
	},

	// Observe every not-yet-observed post in the tracked thread. Idempotent: posts are
	// flagged so repeated calls (e.g. after new replies are inserted) don't re-observe.
	observeThreadPosts: function () {
		if (!kktwch._viewportObserver || !kktwch._viewportThreadEl) return;
		var posts = kktwch._viewportThreadEl.querySelectorAll('.post');
		posts.forEach(function (post) {
			if (post.dataset.twObserved) return;
			post.dataset.twObserved = '1';
			kktwch._viewportObserver.observe(post);
		});
	},

	// DOM position (0-based) of a post among all posts in the tracked thread.
	postIndexInThread: function (postEl) {
		if (!kktwch._viewportThreadEl) return -1;
		var posts = kktwch._viewportThreadEl.querySelectorAll('.post');
		return Array.prototype.indexOf.call(posts, postEl);
	},

	// Persist read progress from the viewport: lastSeenCount becomes the number of posts
	// read from the top (furthest-seen position + 1), but only ever increases.
	commitViewportProgress: function () {
		var threadUid = kktwch._viewportThreadUid;
		if (!threadUid) return;

		var w = kktwch.getWatchedThreads();
		var e = w[threadUid];
		if (!e) return;

		// Not yet seeded by a poll: let the poll set the real count first, so we don't
		// lock in a position before the true post count is known.
		if (e.lastSeenCount === null || e.lastSeenCount === undefined) return;

		var seenCount = kktwch.seenCountFromMaxIndex(e);
		if (seenCount > e.lastSeenCount) {
			e.lastSeenCount = seenCount;
			// Reading through to the end of the thread means every quoting reply has been
			// seen too, so clear the unread-quote flag the same way the unread count clears.
			// Otherwise the red "quoted you" state would linger until the mark-read button.
			if (e.lastSeenCount >= (e.postCount || 0)) {
				e.seenQuoteCount = e.quoteCount || 0;
			}
			kktwch.saveWatchedThreads(w);
			kktwch.renderWatchList();
		}
	},

	// Translate the furthest-seen DOM position (_maxSeenIndex) into "posts read from the
	// top of the thread". The page renders the OP followed by the *newest* replies: the
	// whole thread when nothing is omitted, or just the last N in an abbreviated
	// ("last X replies") view. So a reply at DOM index i sits at thread position
	// (postCount - domCount + 1 + i), and the OP is always position 1. On a full thread
	// page domCount === postCount and this reduces to i + 1. Returns 0 when it can't
	// safely advance.
	seenCountFromMaxIndex: function (entry) {
		var idx = kktwch._maxSeenIndex;
		if (idx < 0 || !kktwch._viewportThreadEl) return 0;

		var postCount = entry.postCount || 0;
		if (!postCount) return 0;

		var domCount = kktwch._viewportThreadEl.querySelectorAll('.post').length;
		if (!domCount) return 0;

		// The OP (index 0) is always thread position 1.
		if (idx === 0) return 1;

		// The rendered replies cover thread positions [postCount - domCount + 2 .. postCount].
		// If what's already read stops before that block begins, there are unread posts
		// above the visible replies (an abbreviated view with a large backlog). Advancing
		// would silently skip them, so leave those to a fuller view / the mark-read button.
		if (entry.lastSeenCount < postCount - (domCount - 1)) return 0;

		var pos = postCount - domCount + 1 + idx;
		if (pos < 1) pos = 1;
		if (pos > postCount) pos = postCount;
		return pos;
	},

	/* --- Polling --- */

	startPolling: function () {
		kktwch.stopPolling();
		kktwch.checkAllThreads();
		kktwch._pollTimer = setInterval(function () {
			kktwch.checkAllThreads();
		}, kktwch.POLL_INTERVAL);
	},

	stopPolling: function () {
		if (kktwch._pollTimer) {
			clearInterval(kktwch._pollTimer);
			kktwch._pollTimer = null;
		}
	},

	// `force` (manual refresh) bypasses the cross-tab poll lock. Returns the fetch
	// promise so callers can react when the refresh completes (or undefined when there's
	// nothing to fetch).
	checkAllThreads: function (force) {
		var watched = kktwch.getWatchedThreads();
		var threadUids = Object.keys(watched);
		var wantNewThreads = _kkSetting('threadWatcherNewThreads');

		// Nothing to do if not watching anything and new-thread alerts are off.
		if (!threadUids.length && !wantNewThreads) return;

		// The shared store loads asynchronously, so until its snapshot has merged in, this
		// subdomain's localStorage still holds whatever it last saw — on a board opened after
		// browsing a different subdomain that can be days out of date. Polling on it would
		// replay every thread posted since as a fresh notification, and save the stale watch
		// list straight back over the shared one. Wait: startup's onReady handler fires the
		// first real poll the moment the snapshot lands.
		if (!kkStore.isReady()) return;

		// Offline (e.g. the connection dropped overnight): the fetch can only fail, so
		// don't fire it. Polling resumes as soon as the browser reports the network back.
		if (navigator.onLine === false) return;

		// Never run two polls at once in this tab; the in-flight one will finish first.
		if (kktwch._pollInProgress) return;

		// Cross-tab coordination: only one tab actually polls per interval. Whichever
		// tab fires first claims the shared lock; the others skip the fetch (and so the
		// notifications) and instead refresh their UI from the `storage` event. This
		// stops every open tab from notifying for the same new post. A manual refresh
		// (force) is user-initiated, so it ignores the lock.
		var now = Date.now();
		var lastPoll = parseInt(localStorage.getItem('kktwch_lastPoll') || '0', 10);
		if (!force && now - lastPoll < kktwch.POLL_INTERVAL - 1000) return;
		kkStore.set('kktwch_lastPoll', now);

		var apiUrl = document.querySelector('meta[name="threadWatcherApiUrl"]')?.content || null;
		if (!apiUrl) return;

		// Mark the poll in flight and reflect it on the refresh button (spin + lock).
		kktwch._pollInProgress = true;
		kktwch.updateRefreshUi();

		var params = [];
		if (threadUids.length) {
			params.push('thread_uids=' + threadUids.map(encodeURIComponent).join(','));
			// Send the user's own posts so the server can flag threads that quote them.
			var ownTokens = kktwch.getOwnPostTokens();
			if (ownTokens.length) {
				params.push('you=' + ownTokens.map(encodeURIComponent).join(','));
			}
			// Send per-thread seen counts so the server can resolve each thread's
			// first-unread post number (used to anchor the watch-list link). Only
			// seeded entries have a meaningful count.
			var seenPairs = [];
			threadUids.forEach(function (uid) {
				var e = watched[uid];
				if (e && e.lastSeenCount !== null && e.lastSeenCount !== undefined) {
					seenPairs.push(encodeURIComponent(uid) + ':' + (e.lastSeenCount || 0));
				}
			});
			if (seenPairs.length) {
				params.push('seen=' + seenPairs.join(','));
			}
		}
		if (wantNewThreads) {
			params.push('newthreads=1');
			var sinceMark = localStorage.getItem('kktwch_lastThreadSeen') || '';
			if (sinceMark) params.push('since=' + encodeURIComponent(sinceMark));
		}

		var separator = apiUrl.includes('?') ? '&' : '?';
		var url = apiUrl + separator + params.join('&');

		// Tracks whether this poll actually reached the server and parsed a response, so a
		// manual refresh knows whether to stop spinning or keep retrying.
		var succeeded = false;

		return fetch(url)
			.then(function (res) {
				if (!res.ok) return null;
				return res.json();
			})
			.then(function (data) {
				if (!data) return;

				var watched = kktwch.getWatchedThreads();
				var changed = false;

				// Handle deleted threads
				if (Array.isArray(data.deleted)) {
					data.deleted.forEach(function (threadUid) {
						if (watched[threadUid]) {
							delete watched[threadUid];
							changed = true;
						}
					});
				}

				// Track which threads grew this poll so we can decide
				// notifications AFTER markVisibleThreadsAsRead runs.
				// quoteGrowth marks threads whose new posts quote the user.
				var pollGrowth = {};
				var quoteGrowth = {};

				// Update post counts and subjects
				if (data.threads && typeof data.threads === 'object') {
					Object.keys(data.threads).forEach(function (threadUid) {
						var entry = watched[threadUid];
						if (!entry) return;

						var info = data.threads[threadUid];
						var newPostCount = info.post_count;
						var prevPostCount = entry.postCount || 0;

						entry.postCount = newPostCount;
						entry.lastChecked = Date.now();

						// Seed lastSeenCount on the first poll: everything present when the
						// thread was watched counts as already read (the page only showed a
						// preview, so we couldn't know the real count at watch time).
						var postWasSeeded = entry.lastSeenCount !== null && entry.lastSeenCount !== undefined;
						if (!postWasSeeded) {
							entry.lastSeenCount = newPostCount;
						}

						// Post number of the first unread reply, so the watch-list link can
						// jump straight to it. Null/absent once the thread is fully read.
						entry.firstUnreadNo = (typeof info.first_unread_no === 'number')
							? info.first_unread_no
							: null;

						if (typeof info.board_title === 'string') {
							entry.boardTitle = info.board_title;
						}
						// The server builds each thread's link from its own board, so this
						// corrects an entry watched where the link on the page was relative
						// (e.g. a cross-subdomain thread on the overboard, whose href the
						// browser resolved against the board being read at the time).
						if (typeof info.url === 'string' && info.url !== '') {
							entry.url = info.url;
						}
						if (typeof info.label === 'string' && info.label !== '') {
							entry.label = info.label;
						}

						// Track quote-replies to the user's own posts. Seed the seen
						// count on the first poll so pre-existing quotes aren't flagged.
						var newQuoteCount = info.quote_count || 0;
						var prevQuoteCount = entry.quoteCount || 0;
						var quoteWasSeeded = entry.seenQuoteCount !== null && entry.seenQuoteCount !== undefined;
						if (!quoteWasSeeded) {
							entry.seenQuoteCount = newQuoteCount;
						}
						entry.quoteCount = newQuoteCount;

						// Only count growth once seeded, so first watching a thread never
						// notifies for posts that already existed.
						if (postWasSeeded && newPostCount > prevPostCount) {
							pollGrowth[threadUid] = true;
						}
						// A genuinely new quote this poll (not the seeding poll).
						if (quoteWasSeeded && newQuoteCount > prevQuoteCount) {
							quoteGrowth[threadUid] = true;
						}

						changed = true;
					});
				}

				// New-thread alerts (independent of watched threads).
				if (data.newThreads && typeof data.newThreads === 'object') {
					kktwch.handleNewThreads(data.newThreads);
				}

				if (changed) {
					kktwch.saveWatchedThreads(watched);
					// Pick up any watched threads newly rendered on the page (e.g. an
					// overboard reload) so their visibility is tracked too.
					kktwch.observeIndexThreads();
					// On index/overboard, a watched thread the user has scrolled into
					// view is considered seen — bump lastSeenCount when the visible DOM
					// actually contains the unread range. Run this BEFORE the
					// notification check so a reply already read on the index doesn't ding.
					kktwch.markVisibleThreadsAsRead();

					// Send notifications for threads that grew this poll AND
					// still have unread replies after the visibility check.
					var watchedAfter = kktwch.getWatchedThreads();
					Object.keys(pollGrowth).forEach(function (threadUid) {
						var entry = watchedAfter[threadUid];
						if (!entry) return;
						var unseenCount = (entry.postCount || 0) - (entry.lastSeenCount || 0);
						if (unseenCount > 0) {
							kktwch.sendNotification(entry, unseenCount, !!quoteGrowth[threadUid]);
						}
					});

					kktwch.renderWatchList();
					// A poll may have unwatched deleted threads — refresh their stars.
					kktwch.syncStars();
				}

				// Refresh the title and top-link class on every poll cycle, even when
				// nothing changed, so the button's color always reflects current state.
				kktwch.updatePageTitle();

				// Record when the watch data was last refreshed (shared across tabs).
				kkStore.set('kktwch_lastUpdated', Date.now());
				kktwch.updateUpdatedLabel();

				succeeded = true;
			})
			.catch(function () {
				// Silently fail on network errors
			})
			.finally(function () {
				// Poll finished: release the in-tab lock. A pending manual refresh keeps the
				// button spinning until a poll succeeds — clear it on success, retry on failure.
				kktwch._pollInProgress = false;
				if (succeeded) kktwch.clearRefreshPending();
				else kktwch.scheduleRefreshRetry();
				kktwch.updateRefreshUi();
			});
	},

	/* --- Mark as Read --- */

	markAsRead: function (threadUid) {
		var watched = kktwch.getWatchedThreads();
		var entry = watched[threadUid];
		if (!entry) return;

		entry.lastSeenCount = entry.postCount;
		entry.seenQuoteCount = entry.quoteCount || 0;
		kktwch.saveWatchedThreads(watched);
		kktwch.renderWatchList();
	},

	// Mark every watched thread as fully read: clears both unread replies and unread
	// quotes across the whole list. Only touches threads that actually have something
	// unread, so unseeded (just-watched, not-yet-polled) entries are left alone.
	markAllAsRead: function () {
		var watched = kktwch.getWatchedThreads();
		var changed = false;
		Object.keys(watched).forEach(function (threadUid) {
			var entry = watched[threadUid];
			if (!entry) return;
			if (kktwch.getUnreadCount(entry) === 0 && !kktwch.hasUnreadQuote(entry)) return;
			entry.lastSeenCount = entry.postCount;
			entry.seenQuoteCount = entry.quoteCount || 0;
			changed = true;
		});
		if (changed) kktwch.saveWatchedThreads(watched);
		kktwch.renderWatchList();
	},

	// Remove every watched thread from the list. Confirms first, since unwatching the
	// whole list can't be undone.
	clearAllWatched: function () {
		var watched = kktwch.getWatchedThreads();
		if (!Object.keys(watched).length) return;
		if (!window.confirm('Remove all watched threads?')) return;

		kktwch.saveWatchedThreads({});
		kktwch.renderWatchList();
		kktwch.updatePageTitle();
		// Hollow out every star on the page now that nothing is watched.
		kktwch.syncStars();
	},

	/**
	 * On index/overboard pages, mark a watched thread read only when BOTH hold:
	 *  - the user has actually scrolled it into view this page load (_indexSeenThreads), and
	 *  - its unread posts are genuinely rendered in the current index snapshot (checked via
	 *    the first-unread post element existing in the DOM).
	 * This means a thread below the fold, or one whose new replies haven't been loaded yet
	 * (stale index the user hasn't refreshed), stays unread and keeps notifying.
	 *
	 * No-op on thread pages (those use the per-post viewport IntersectionObserver).
	 * Returns true if anything changed.
	 */
	markVisibleThreadsAsRead: function () {
		// Only run on index/overboard pages
		if (kktwch.onThreadPage()) return false;

		var watched = kktwch.getWatchedThreads();
		var keys = Object.keys(watched);
		if (!keys.length) return false;

		var changed = false;
		keys.forEach(function (threadUid) {
			var entry = watched[threadUid];
			if (!entry) return;

			// Only mark threads the user has actually scrolled into view on this page.
			// Threads still below the fold aren't "seen", so their new replies keep
			// notifying rather than being silently cleared just for being on the page.
			if (!kktwch._indexSeenThreads[threadUid]) return;

			// Not yet seeded by a poll: leave it for the poll to seed to the real count,
			// so we don't lock in the page's preview count as "seen".
			if (entry.lastSeenCount === null || entry.lastSeenCount === undefined) return;

			var postCount = entry.postCount || 0;
			var lastSeen = entry.lastSeenCount || 0;
			// Nothing new to mark
			if (lastSeen >= postCount) return;

			// Only mark read if the unread posts are actually rendered on the index right
			// now. The index HTML is a snapshot: when new replies arrive but the user hasn't
			// reloaded the page, those posts aren't in the DOM — the API just reports a
			// higher count — so they haven't really been seen. The server gives us the first
			// unread post's number; if its element is on the page then the unread range (a
			// contiguous block of the newest replies) is visible, so it's safe to mark read.
			// Otherwise (stale index, or an abbreviated block that omits older unread posts)
			// leave it unread.
			var firstUnreadNo = entry.firstUnreadNo;
			if (!firstUnreadNo || !entry.boardId) return;
			if (!document.getElementById('p' + entry.boardId + '_' + firstUnreadNo)) return;

			entry.lastSeenCount = postCount;
			// The whole unread range is rendered and the thread has been scrolled into view,
			// so any quoting replies in it have been seen — clear the unread-quote flag too,
			// matching how the unread count clears (see commitViewportProgress).
			entry.seenQuoteCount = entry.quoteCount || 0;
			changed = true;
		});

		if (changed) {
			kktwch.saveWatchedThreads(watched);
		}
		return changed;
	},

	getUnreadCount: function (entry) {
		// Not yet seeded (just watched): nothing is unread until the first poll.
		if (entry.lastSeenCount === null || entry.lastSeenCount === undefined) return 0;
		return Math.max(0, (entry.postCount || 0) - entry.lastSeenCount);
	},

	// True while viewing a single thread's page (the reply form carries a filled-in resto input).
	onThreadPage: function () {
		var restoInput = document.querySelector('input[name="resto"]');
		return !!(restoInput && restoInput.value);
	},

	// UID of the thread whose page we're currently viewing, or null on the index/overboard.
	currentThreadUid: function () {
		if (!kktwch.onThreadPage()) return null;
		return kktwch.threadUidOfElement(document.querySelector('.post.op'));
	},

	// Unread count as shown in the UI. Unread badges only appear on the index/overboard, so
	// while viewing a thread there's nothing to show.
	getDisplayUnreadCount: function (entry) {
		if (kktwch.onThreadPage()) return 0;
		return kktwch.getUnreadCount(entry);
	},

	// Build the watch-list link target. When the thread has unread replies and the
	// server has told us the first unread post's number, anchor the link to that post
	// (post elements have id "p{boardId}_{no}") so the page jumps to it. Otherwise link
	// to the thread as captured at watch time.
	buildThreadUrl: function (entry, hasUnread) {
		var base = entry.url || '#';
		if (hasUnread && entry.firstUnreadNo && entry.boardId && base !== '#') {
			// Drop any existing fragment before appending our own.
			var hashIdx = base.indexOf('#');
			if (hashIdx !== -1) base = base.slice(0, hashIdx);
			return base + '#p' + entry.boardId + '_' + entry.firstUnreadNo;
		}
		return base;
	},

	// Max characters for a client-side label fallback; read from the server-emitted meta
	// tag so it mirrors the server's LABEL_MAX_LENGTH, keeping a freshly-watched thread
	// (before the first poll supplies the server-truncated label) from showing an
	// over-long, un-truncated subject. Falls back to 25 if the meta tag is absent.
	getLabelMaxLength: function () {
		var meta = document.querySelector('meta[name="threadWatcherLabelMaxLength"]');
		var n = meta ? parseInt(meta.content, 10) : NaN;
		return (n > 0) ? n : 25;
	},

	truncateLabel: function (text) {
		var max = kktwch.getLabelMaxLength();
		if (text.length <= max) return text;
		return text.slice(0, max - 1) + '…';
	},

	// "Board Title - Subject/preview/filename" (label computed server-side; the client
	// truncates its own subject fallback to match until the first poll lands).
	getDisplayName: function (entry) {
		var label = entry.label || kktwch.truncateLabel(entry.subject || 'No.' + (entry.threadNo || entry.threadUid));
		return entry.boardTitle ? (entry.boardTitle + ' - ' + label) : label;
	},

	// True when an unread post quotes one of the user's own posts.
	hasUnreadQuote: function (entry) {
		var seen = (entry.seenQuoteCount === null || entry.seenQuoteCount === undefined)
			? (entry.quoteCount || 0)
			: entry.seenQuoteCount;
		return (entry.quoteCount || 0) > seen;
	},

	/* --- Notifications --- */

	requestNotificationPermission: function () {
		if (!('Notification' in window) || Notification.permission !== 'default') return;

		// only ever prompt once
		try {
			if (localStorage.getItem('kktwch_notifAsked')) return;
			localStorage.setItem('kktwch_notifAsked', '1');
		} catch (e) {}

		Notification.requestPermission();
	},

	/* --- New thread alerts --- */

	// Process the server's new-threads payload. Seeds silently on first run, then pushes
	// a notification for each new thread on a non-blacklisted board. The high-water marker
	// lives in shared localStorage, so only the polling tab advances it and notifies.
	handleNewThreads: function (nt) {
		if (!_kkSetting('threadWatcherNewThreads')) return;

		var prev = localStorage.getItem('kktwch_lastThreadSeen');

		// First run: record the marker without notifying for everything that already exists.
		if (prev === null || prev === '') {
			if (nt.latest) kkStore.set('kktwch_lastThreadSeen', nt.latest);
			return;
		}

		var items = Array.isArray(nt.items) ? nt.items : [];
		// Don't notify for threads the user created themselves.
		items = items.filter(function (item) {
			return !kktwch.isOwnPost(item.board_uid, item.post_op_number);
		});

		if (items.length === 1) {
			// A single new thread: notify with its board/label.
			kktwch.notifyNewThread(items[0]);
		} else if (items.length > 1) {
			// A burst of new threads (e.g. a backlog that piled up while every tab was
			// closed) is coalesced into one notification instead of a wall of dings.
			kktwch.notifyNewThreadsBatch(items);
		}

		if (nt.latest) kkStore.set('kktwch_lastThreadSeen', nt.latest);
	},

	// New-thread alerts are push-only: they're enabled by default and cover every
	// non-blacklisted board, so we don't fall back to an audible ping that could fire
	// constantly on a busy instance.
	notifyNewThread: function (item) {
		if (document.hasFocus() || !('Notification' in window) || Notification.permission !== 'granted') {
			return;
		}

		var title = 'New thread' + (item.board_title ? ' — ' + item.board_title : '');
		try {
			var notif = new Notification(title, {
				body: item.label || '',
				tag: 'twnt_' + item.thread_uid,
				icon: STATIC_URL + 'image/favicon.ico'
			});
			notif.onclick = function () {
				window.focus();
				if (item.url) window.location.href = item.url;
				notif.close();
			};
		} catch (e) {}
	},

	// Coalesced alert for several new threads arriving in one poll (e.g. a backlog that
	// accumulated while every tab was closed). Fires a single notification — "<N> new
	// threads posted" — that links to the most recent thread. The server returns items
	// newest-first, so items[0] is the most recent.
	notifyNewThreadsBatch: function (items) {
		if (document.hasFocus() || !('Notification' in window) || Notification.permission !== 'granted') {
			return;
		}

		var latest = items[0];
		var latestLabel = latest.label || ('No.' + latest.post_op_number);
		var subtitle = latest.board_title ? (latest.board_title + ' — ' + latestLabel) : latestLabel;

		try {
			var notif = new Notification(items.length + ' new threads posted', {
				body: 'Latest: ' + subtitle,
				// Constant tag so a later batch replaces an earlier one rather than stacking.
				tag: 'twnt_multi',
				icon: STATIC_URL + 'image/favicon.ico'
			});
			notif.onclick = function () {
				window.focus();
				if (latest.url) window.location.href = latest.url;
				notif.close();
			};
		} catch (e) {}
	},

	sendNotification: function (entry, unreadCount, isQuote) {
		// Check if notifications are enabled in settings
		if (!_kkSetting('threadWatcherNotifs')) return;

		var replyWord = unreadCount === 1 ? 'reply' : 'replies';

		// Quote-replies: prefer a push notification when the user allows them. 
		// otherwise fall back to a distinct double ping.
		// When quote-push is disabled, fall through and treat them as a regular ping.
		if (isQuote && _kkSetting('threadWatcherQuotePush')) {
			if ('Notification' in window && Notification.permission === 'granted') {
				try {
					var notif = new Notification(kktwch.getDisplayName(entry), {
						body: 'Quoted you (' + unreadCount + ' new ' + replyWord + ')',
						tag: 'tw_' + entry.threadUid,
						icon: STATIC_URL + 'image/favicon.ico'
					});

					notif.onclick = function () {
						window.focus();
						if (entry.url) {
							window.location.href = entry.url;
						}
						notif.close();
					};
					return;
				} catch (e) {}
			}

			kktwch.playDing(2);
			return;
		}

		// Regular replies (no quote to you): a single audio ping.
		kktwch.playDing(1);
	},

	// Play the notification sound `count` times in quick succession.
	playDing: function (count) {
		// Audio pings are optional (enabled by default).
		if (!_kkSetting('threadWatcherSound')) return;
		count = count || 1;

		// Cross-tab dedup: if another tab played the ding within the last 3 seconds, skip.
		var now = Date.now();
		var lastDing = parseInt(localStorage.getItem('kktwch_lastDing') || '0', 10);
		if (now - lastDing < 3000) return;
		kkStore.set('kktwch_lastDing', now);

		kktwch._playDingOnce();
		for (var i = 1; i < count; i++) {
			setTimeout(kktwch._playDingOnce, i * 300);
		}
	},

	_playDingOnce: function () {
		if (!kktwch._dingAudio) {
			kktwch._dingAudio = new Audio(STATIC_URL + 'audio/postNotif.mp3');
		}
		kktwch._dingAudio.currentTime = 0;
		kktwch._dingAudio.play().catch(function () {});
	},

	/* --- Window UI --- */

	toggleWindow: function () {
		if (kktwch._win) {
			kktwch._win.remove();
			kktwch._win = null;
			kktwch.stopUpdatedTicker();
			// No button to spin anymore; stop any in-progress manual-refresh retries.
			kktwch.clearRefreshPending();
			return;
		}
		kktwch.openWindow();
	},

	openWindow: function () {
		var title = 'Thread watcher';
		var exist = $kkwm_name(title);
		if (exist) {
			exist.flash();
			kkwm.top(title);
			return;
		}

		var d = $doc.documentElement;
		var pw = Math.min(400, Math.max(300, d.clientWidth / 4));
		kktwch._win = new kkwmWindow(title, { w: pw, h: 300 });
		kktwch._win.onclose = function () {
			kktwch._win = null;
			kktwch.stopUpdatedTicker();
			// No button to spin anymore; stop any in-progress manual-refresh retries.
			kktwch.clearRefreshPending();
		};

		// Clone the content wrapper template
		var contentTpl = document.getElementById('threadWatcherContentTpl');
		if (contentTpl) {
			var clone = contentTpl.content.cloneNode(true);
			kktwch._win.div.appendChild(clone);
		}

		// Wire the manual-refresh button in the (non-scrolling) header.
		var refreshBtn = kktwch._win.div.querySelector('.threadWatcherRefresh');
		if (refreshBtn) {
			refreshBtn.addEventListener('click', function (e) {
				e.preventDefault();
				kktwch.manualRefresh();
			});
		}

		// Wire the "mark all as read" button next to it.
		var markAllBtn = kktwch._win.div.querySelector('.threadWatcherMarkAllRead');
		if (markAllBtn) {
			markAllBtn.addEventListener('click', function (e) {
				e.preventDefault();
				kktwch.markAllAsRead();
			});
		}

		// Wire the "clear all" button.
		var clearAllBtn = kktwch._win.div.querySelector('.threadWatcherClearAll');
		if (clearAllBtn) {
			clearAllBtn.addEventListener('click', function (e) {
				e.preventDefault();
				kktwch.clearAllWatched();
			});
		}

		kktwch.renderWatchList();

		// Initialize the refresh button state and the "last updated" label, then keep the
		// relative time fresh while the window stays open.
		kktwch.updateRefreshUi();
		kktwch.updateUpdatedLabel();
		kktwch.startUpdatedTicker();
	},

	startUpdatedTicker: function () {
		kktwch.stopUpdatedTicker();
		kktwch._updatedTimer = setInterval(function () {
			kktwch.updateUpdatedLabel();
		}, 10000);
	},

	stopUpdatedTicker: function () {
		if (kktwch._updatedTimer) {
			clearInterval(kktwch._updatedTimer);
			kktwch._updatedTimer = null;
		}
	},

	// User-initiated refresh: force a poll now (ignoring the cross-tab interval lock).
	// Ignored while a poll is already running or during the post-refresh cooldown, so
	// the button can't be spammed.
	manualRefresh: function () {
		// Ignore clicks while a poll is already running (or a manual refresh is still
		// retrying): that's what stops the button being spammed.
		if (kktwch.isRefreshLocked()) return;

		// Keep the button spinning and locked until a poll actually succeeds.
		kktwch._refreshPending = true;
		kktwch.updateRefreshUi();

		var started = kktwch.checkAllThreads(true);
		// No fetch was started (nothing watched and new-thread alerts off): don't spin forever.
		if (!started) {
			kktwch.clearRefreshPending();
			kktwch.updateRefreshUi();
		}
	},

	// Retry a failed manual refresh after a short delay, for as long as one is pending.
	scheduleRefreshRetry: function () {
		if (!kktwch._refreshPending || kktwch._refreshRetryTimer) return;
		kktwch._refreshRetryTimer = setTimeout(function () {
			kktwch._refreshRetryTimer = null;
			if (!kktwch._refreshPending) return;
			var started = kktwch.checkAllThreads(true);
			// Genuinely nothing left to fetch (not merely a poll already running): stop.
			if (!started && !kktwch._pollInProgress) {
				kktwch.clearRefreshPending();
				kktwch.updateRefreshUi();
			}
		}, kktwch.REFRESH_RETRY_DELAY);
	},

	// Stop the manual-refresh spinner: a poll succeeded, or there's nothing left to do.
	clearRefreshPending: function () {
		kktwch._refreshPending = false;
		if (kktwch._refreshRetryTimer) {
			clearTimeout(kktwch._refreshRetryTimer);
			kktwch._refreshRetryTimer = null;
		}
	},

	// True while the refresh button must stay locked: a poll is in flight, or a manual
	// refresh is still retrying. Unlocks as soon as the ongoing poll finishes.
	isRefreshLocked: function () {
		return kktwch._pollInProgress || kktwch._refreshPending;
	},

	// Reflect poll state on the refresh button: spin while polling, locked (dimmed,
	// non-clickable) while a poll/refresh is ongoing so it can't be spammed.
	updateRefreshUi: function () {
		var btn = document.querySelector('.threadWatcherRefresh');
		if (!btn) return;
		// Spin while a fetch is in flight or a manual refresh is still retrying.
		btn.classList.toggle('twSpinning', kktwch._pollInProgress || kktwch._refreshPending);
		btn.classList.toggle('twLocked', kktwch.isRefreshLocked());
	},

	/* --- Last-updated label --- */

	// Human-readable "time since last update", or 'Never' if we've never polled.
	formatUpdatedTime: function (ts) {
		if (!ts) return 'Never';
		var diff = Date.now() - ts;
		if (diff < 0) diff = 0;
		if (diff < 5000) return 'just now';
		if (diff < 60000) return Math.floor(diff / 1000) + 's ago';
		if (diff < 3600000) return Math.floor(diff / 60000) + 'm ago';
		if (diff < 86400000) return Math.floor(diff / 3600000) + 'h ago';
		return new Date(ts).toLocaleString();
	},

	updateUpdatedLabel: function () {
		var el = $id('threadWatcherUpdated');
		if (!el) return;
		var ts = parseInt(localStorage.getItem('kktwch_lastUpdated') || '0', 10);
		el.textContent = kktwch.formatUpdatedTime(ts || 0);
	},

	renderWatchList: function () {
		var content = $id('threadWatcherContent');
		if (!content) return;

		var watched = kktwch.getWatchedThreads();
		// Most recently watched first. Entries from before watchedAt existed
		// sort last (treated as oldest) but keep a stable relative order.
		var keys = Object.keys(watched).sort(function (a, b) {
			return (watched[b].watchedAt || 0) - (watched[a].watchedAt || 0);
		});

		var list = content.querySelector('.threadWatcherList');

		if (!keys.length) {
			// Show empty state from template
			list.hidden = true;
			var existing = content.querySelector('.threadWatcherEmpty');
			if (!existing) {
				var emptyTpl = document.getElementById('threadWatcherEmptyTpl');
				if (emptyTpl) {
					content.appendChild(emptyTpl.content.cloneNode(true));
				}
			}
			kktwch.updatePageTitle();
			return;
		}

		// Remove empty state if present
		var emptyEl = content.querySelector('.threadWatcherEmpty');
		if (emptyEl) emptyEl.remove();
		list.hidden = false;

		// Clear existing rows
		list.innerHTML = '';

		var rowTpl = document.getElementById('threadWatcherRowTpl');
		if (!rowTpl) return;

		keys.forEach(function (threadUid) {
			var entry = watched[threadUid];
			var unread = kktwch.getDisplayUnreadCount(entry);
			var hasUnread = unread > 0;
			var hasQuote = kktwch.hasUnreadQuote(entry);

			var displayName = kktwch.getDisplayName(entry);

			var row = rowTpl.content.cloneNode(true);

			// Fill in the link. When there are unread replies and we know the first one,
			// anchor the link directly to it so clicking jumps to where reading resumes.
			var link = row.querySelector('.threadWatcherLink');
			link.href = kktwch.buildThreadUrl(entry, hasUnread);
			link.textContent = displayName;
			link.title = displayName;
			link.setAttribute('data-thread-uid', threadUid);
			// Red when an unread reply quotes you, otherwise green when there are unread posts.
			if (hasQuote) link.classList.add('twQuoted');
			else if (hasUnread) link.classList.add('twUnread');
			link.addEventListener('click', function () {
				kktwch.markAsRead(threadUid);
			});

			// Fill in unread count
			var unreadSpan = row.querySelector('.threadWatcherUnread');
			if (hasUnread) {
				unreadSpan.textContent = '(' + unread + ')';
				unreadSpan.hidden = false;
			}

			// Wire up remove button
			var removeBtn = row.querySelector('.threadWatcherRemove');
			removeBtn.addEventListener('click', function (e) {
				e.preventDefault();
				kktwch.unwatchThread(threadUid);
				kktwch.renderWatchList();
				// Hollow out this thread's on-page star, if it's visible.
				kktwch.syncStars();
			});

			// Wire up mark-as-read button. Show it whenever there's anything to clear —
			// either unread replies or an unread quote-to-you. These can diverge: viewing
			// the posts (viewport / index visibility) clears the unread count but leaves
			// the quote unseen, which would otherwise leave a red entry with no way to
			// dismiss it.
			var markReadBtn = row.querySelector('.threadWatcherMarkRead');
			if (hasUnread || hasQuote) {
				markReadBtn.hidden = false;
				markReadBtn.addEventListener('click', function (e) {
					e.preventDefault();
					kktwch.markAsRead(threadUid);
				});
			}

			list.appendChild(row);
		});

		kktwch.updatePageTitle();
	},

	/* --- Page Title --- */

	updatePageTitle: function () {
		var watched = kktwch.getWatchedThreads();
		var total = 0;
		var anyQuote = false;
		Object.keys(watched).forEach(function (uid) {
			total += kktwch.getDisplayUnreadCount(watched[uid]);
			if (kktwch.hasUnreadQuote(watched[uid])) anyQuote = true;
		});

		// Contribute our unread total to the shared title controller instead of writing
		// document.title directly, so we don't fight the thread updater over the prefix.
		kkTitle.set('threadWatcher', total);

		kktwch.updateToplink(total > 0, anyQuote);
	},

	// Color the admin-bar top-link: red when any watched thread has an unread quote-reply,
	// green when there are unread posts, default otherwise.
	updateToplink: function (hasUnread, hasQuote) {
		var toplink = $id('threadWatcherToplink');
		if (!toplink) return;
		toplink.classList.toggle('twQuoted', !!hasQuote);
		toplink.classList.toggle('twUnread', !hasQuote && !!hasUnread);
	},

};

if (typeof(KOKOJS) != "undefined") {
	kkjs.modules.push(kktwch);

	// Declare which localStorage keys the watcher wants mirrored across subdomains.
	// koko.js's kkStore is key-agnostic; each feature opts its own keys in here.
	kkStore.registerShared([
		'threadWatcher',          // the watch list
		'kkOwnPosts',             // the user's own posts (for quote detection)
		'kktwch_lastPoll',        // cross-tab poll lock
		'kktwch_lastUpdated',     // last successful refresh time (for the "updated" label)
		'kktwch_lastThreadSeen',  // new-thread high-water mark
		'kktwch_lastDing',        // cross-tab audio-ding dedup
		'threadWatcherNotifs',    // settings
		'threadWatcherQuotePush',
		'threadWatcherNewThreads',
		'threadWatcherSound',
		'threadWatcherAutoWatch',
		'threadWatcherAutoWatchOwnThreads'
	]);

	// Thread-watcher settings live in kkStore (shared across subdomains), not plain
	// localStorage, so each writes through kkStore.set instead of the default.
	var twStore = function (key, value) { kkStore.set(key, value); };
	var twPermission = function (v) { if (v) kktwch.requestNotificationPermission(); };
	kkSetting.add({ key: "threadWatcherNotifs", label: "Thread watcher notifications", store: twStore, onChange: twPermission }, "Thread Watcher");
	kkSetting.add({ key: "threadWatcherQuotePush", label: "Push notification when quoted", store: twStore, onChange: twPermission }, "Thread Watcher");
	kkSetting.add({ key: "threadWatcherNewThreads", label: "New thread notifications", store: twStore, onChange: twPermission }, "Thread Watcher");
	kkSetting.add({ key: "threadWatcherSound", label: "Play notification sound", store: twStore }, "Thread Watcher");
	kkSetting.add({ key: "threadWatcherAutoWatch", label: "Auto-watch threads you reply to", store: twStore }, "Thread Watcher");
	kkSetting.add({ key: "threadWatcherAutoWatchOwnThreads", label: "Auto-watch threads you make", store: twStore }, "Thread Watcher");
} else { console.log("ERROR: KOKOJS not loaded!\nPlease load 'koko.js' before this script."); }