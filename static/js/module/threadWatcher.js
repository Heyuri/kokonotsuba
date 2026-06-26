/* Thread watcher module
 * Watches threads for new replies using the postApi module.
 * Stores watched threads in localStorage.
 * Uses browser notifications for unread replies.
 */

const kktwch = { name: "KK Thread watcher",
	STORAGE_KEY: 'threadWatcher',
	POLL_INTERVAL: 15000,
	// Minimum gap between manual refreshes, so the button can't be spammed.
	MANUAL_REFRESH_COOLDOWN: 5000,
	_pollTimer: null,
	_win: null,
	// True while a poll's fetch is in flight in this tab (locks the refresh button).
	_pollInProgress: false,
	// Timestamp until which the manual refresh stays locked (cooldown).
	_refreshLockedUntil: 0,
	_refreshCooldownTimer: null,
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

		// Register action handler for PHP-injected "Watch thread" widget on OP posts
		if (window.postWidget) {
			// Dynamic label: show "Watch" or "Unwatch" based on current state
			window.postWidget.registerLabelProvider('watchThread', function (ctx) {
				var post = ctx.post;
				if (!post) return null;
				var threadUid = post.getAttribute('data-thread-uid') ||
				                (post.querySelector('[data-param-thread_uid]')?.getAttribute('data-param-thread_uid'));
				if (!threadUid) return null;

				var watchLabel = document.querySelector('meta[name="threadWatcherWatchLabel"]')?.content || 'Watch thread';
				var unwatchLabel = document.querySelector('meta[name="threadWatcherUnwatchLabel"]')?.content || 'Unwatch thread';

				var watched = kktwch.getWatchedThreads();
				return watched.hasOwnProperty(threadUid) ? unwatchLabel : watchLabel;
			});

			// Handle the watch/unwatch action
			window.postWidget.registerActionHandler('watchThread', function (ctx) {
				var threadUid = ctx.params?.thread_uid || '';
				if (!threadUid) return;

				var watched = kktwch.getWatchedThreads();
				if (watched.hasOwnProperty(threadUid)) {
					kktwch.unwatchThread(threadUid);
				} else {
					kktwch.watchCurrentThread(threadUid);
				}
				kktwch.renderWatchList();
			});
		}

		// Auto-watch on reply submission
		kktwch.hookFormSubmit();

		// Track posts scrolling into view on watched thread pages
		kktwch.initViewportTracking();

		// Request notification permission proactively
		if ('Notification' in window && Notification.permission === 'default') {
			// Will be requested on first watch action instead
		}

		// If any watched threads are visible on this index/overboard page,
		// treat them as seen before reflecting unread counts in the title.
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
		});

		// The shared store loads asynchronously: once its snapshot has been merged
		// in (it may carry threads watched on another subdomain), refresh the UI.
		kkStore.onReady(function () {
			kktwch.renderWatchList();
			kktwch.updatePageTitle();
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
		if (kktwch._refreshCooldownTimer) {
			clearTimeout(kktwch._refreshCooldownTimer);
			kktwch._refreshCooldownTimer = null;
		}
		if (kktwch._viewportObserver) {
			kktwch._viewportObserver.disconnect();
			kktwch._viewportObserver = null;
		}
		if (kktwch._viewportMutObserver) {
			kktwch._viewportMutObserver.disconnect();
			kktwch._viewportMutObserver = null;
		}
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
		// (auto-watch is optional, enabled by default). Always clear the flag.
		if (sessionStorage.getItem('twAutoWatch')) {
			sessionStorage.removeItem('twAutoWatch');
			if (_kkSetting('threadWatcherAutoWatch')) {
				var opPost = document.querySelector('.post.op');
				if (opPost) {
					var threadUid = opPost.getAttribute('data-thread-uid');
					if (threadUid) {
						var watched = kktwch.getWatchedThreads();
						if (!watched[threadUid]) {
							kktwch.watchCurrentThread(threadUid);
							kktwch.renderWatchList();
						}
					}
				}
			}
		}

		form.addEventListener('submit', function () {
			var restoInput = form.querySelector('input[name="resto"]');

			if (!restoInput || !restoInput.value) {
				// New thread: set flag so we auto-watch after redirect (if enabled).
				if (_kkSetting('threadWatcherAutoWatch')) sessionStorage.setItem('twAutoWatch', '1');
				return;
			}

			// Reply: watch immediately
			var opPost = document.querySelector('.post.op');
			if (!opPost) return;

			var threadUid = opPost.getAttribute('data-thread-uid');
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

	initViewportTracking: function () {
		// Only run on thread pages (where a resto input exists)
		var restoInput = document.querySelector('input[name="resto"]');
		if (!restoInput || !restoInput.value) return;

		var opPost = document.querySelector('.post.op');
		if (!opPost) return;

		var threadUid = opPost.getAttribute('data-thread-uid');
		if (!threadUid) return;

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

		var seenCount = kktwch._maxSeenIndex + 1;
		if (seenCount > e.lastSeenCount) {
			e.lastSeenCount = seenCount;
			kktwch.saveWatchedThreads(w);
			kktwch.renderWatchList();
		}
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
					// On index/overboard, any watched thread that's rendered on
					// the page is considered seen — bump lastSeenCount when the
					// visible DOM actually contains the unread range. Run this
					// BEFORE the notification check so an unread reply that's
					// already visible on the index doesn't trigger a ding.
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
				}

				// Refresh the title and top-link class on every poll cycle, even when
				// nothing changed, so the button's color always reflects current state.
				kktwch.updatePageTitle();

				// Record when the watch data was last refreshed (shared across tabs).
				kkStore.set('kktwch_lastUpdated', Date.now());
				kktwch.updateUpdatedLabel();
			})
			.catch(function () {
				// Silently fail on network errors
			})
			.finally(function () {
				// Poll finished (success or failure): release the in-tab lock and let
				// the cooldown govern when the button becomes clickable again.
				kktwch._pollInProgress = false;
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

	/**
	 * On index/overboard pages, a watched thread that's rendered on the page
	 * is treated as "seen" only when the index's snapshot actually contains
	 * all the user's unread replies. If the index is outdated (the API says
	 * there are more posts than the DOM is showing for the unread range),
	 * leave lastSeenCount alone — those unseen posts aren't actually visible.
	 *
	 * No-op on thread pages (those use the viewport IntersectionObserver).
	 * Returns true if anything changed.
	 */
	markVisibleThreadsAsRead: function () {
		// Only run on index/overboard pages
		var restoInput = document.querySelector('input[name="resto"]');
		if (restoInput && restoInput.value) return false;

		var watched = kktwch.getWatchedThreads();
		var keys = Object.keys(watched);
		if (!keys.length) return false;

		var changed = false;
		keys.forEach(function (threadUid) {
			var entry = watched[threadUid];
			if (!entry) return;

			// Not yet seeded by a poll: leave it for the poll to seed to the real count,
			// so we don't lock in the page's preview count as "seen".
			if (entry.lastSeenCount === null || entry.lastSeenCount === undefined) return;

			var postCount = entry.postCount || 0;
			var lastSeen = entry.lastSeenCount || 0;
			// Nothing new to mark
			if (lastSeen >= postCount) return;

			// Find the thread container on the page. data-thread-uid is on
			// both the .thread wrapper and the OP .post element; prefer the
			// wrapper so we can count posts (OP + visible replies) inside it.
			var threadEl = document.querySelector('.thread[data-thread-uid="' + threadUid + '"]');
			if (!threadEl) {
				var opPost = document.querySelector('.post.op[data-thread-uid="' + threadUid + '"]');
				if (opPost) threadEl = opPost.closest('.thread') || opPost.parentElement;
			}
			if (!threadEl) return;

			// Posts currently in this thread block on the index (OP + last N replies).
			var domCount = threadEl.querySelectorAll('.post').length;
			if (!domCount) return;

			// The visible block shows the OP plus the last (domCount - 1) replies.
			// All unread replies are visible only if the unread range fits inside
			// what's shown — i.e. lastSeen >= postCount - (domCount - 1).
			// If the index snapshot is outdated and missing some new posts,
			// this fails and we don't mark.
			if (lastSeen < postCount - (domCount - 1)) return;

			entry.lastSeenCount = postCount;
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

	// "Board Title - Subject/preview/filename" (label computed server-side).
	getDisplayName: function (entry) {
		var label = entry.label || entry.subject || 'No.' + (entry.threadNo || entry.threadUid);
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
		if ('Notification' in window && Notification.permission === 'default') {
			Notification.requestPermission();
		}
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

		// Quote-replies: prefer a push notification when the user allows them and the
		// tab is in the background; otherwise fall back to a distinct double ping.
		// When quote-push is disabled, fall through and treat them as a regular ping.
		if (isQuote && _kkSetting('threadWatcherQuotePush')) {
			if (!document.hasFocus() && 'Notification' in window && Notification.permission === 'granted') {
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
		if (kktwch.isRefreshLocked()) return;

		// Open a cooldown window now, so even an instant poll can't be re-fired
		// immediately afterwards.
		kktwch._refreshLockedUntil = Date.now() + kktwch.MANUAL_REFRESH_COOLDOWN;
		kktwch.scheduleRefreshUnlock();
		kktwch.updateRefreshUi();

		kktwch.checkAllThreads(true);
	},

	// True while the refresh button must stay locked: a poll is in flight, or we're
	// still inside the manual cooldown.
	isRefreshLocked: function () {
		return kktwch._pollInProgress || Date.now() < kktwch._refreshLockedUntil;
	},

	// Re-evaluate the button UI when the cooldown expires.
	scheduleRefreshUnlock: function () {
		if (kktwch._refreshCooldownTimer) clearTimeout(kktwch._refreshCooldownTimer);
		var delay = Math.max(0, kktwch._refreshLockedUntil - Date.now()) + 50;
		kktwch._refreshCooldownTimer = setTimeout(function () {
			kktwch._refreshCooldownTimer = null;
			kktwch.updateRefreshUi();
		}, delay);
	},

	// Reflect poll/cooldown state on the refresh button: spin while polling, locked
	// (dimmed, non-clickable) while polling or cooling down.
	updateRefreshUi: function () {
		var btn = document.querySelector('.threadWatcherRefresh');
		if (!btn) return;
		btn.classList.toggle('twSpinning', kktwch._pollInProgress);
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
			var unread = kktwch.getUnreadCount(entry);
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
			total += kktwch.getUnreadCount(watched[uid]);
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
		'threadWatcherAutoWatch'
	]);

	// Thread-watcher settings live in kkStore (shared across subdomains), not plain
	// localStorage, so each writes through kkStore.set instead of the default.
	var twStore = function (key, value) { kkStore.set(key, value); };
	var twPermission = function (v) { if (v) kktwch.requestNotificationPermission(); };
	kkSetting.add({ key: "threadWatcherNotifs", label: "Thread watcher notifications", store: twStore, onChange: twPermission }, "Thread Watcher");
	kkSetting.add({ key: "threadWatcherQuotePush", label: "Push notification when quoted", store: twStore, onChange: twPermission }, "Thread Watcher");
	kkSetting.add({ key: "threadWatcherNewThreads", label: "New thread notifications", store: twStore, onChange: twPermission }, "Thread Watcher");
	kkSetting.add({ key: "threadWatcherSound", label: "Play notification sound", store: twStore }, "Thread Watcher");
	kkSetting.add({ key: "threadWatcherAutoWatch", label: "Auto-watch threads you post in", store: twStore }, "Thread Watcher");
} else { console.log("ERROR: KOKOJS not loaded!\nPlease load 'koko.js' before this script."); }