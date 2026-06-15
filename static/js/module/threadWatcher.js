/* Thread watcher module
 * Watches threads for new replies using the postApi module.
 * Stores watched threads in localStorage.
 * Uses browser notifications for unread replies.
 */

const kktwch = { name: "KK Thread watcher",
	STORAGE_KEY: 'threadWatcher',
	POLL_INTERVAL: 15000,
	_pollTimer: null,
	_win: null,
	_originalTitle: null,

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

		// Keep tabs that didn't poll in sync: when another tab updates the watched
		// threads, refresh this tab's list/title/top-link without re-notifying.
		window.addEventListener('storage', function (e) {
			if (e.key !== kktwch.STORAGE_KEY) return;
			kktwch.renderWatchList();
			kktwch.updatePageTitle();
		});

		// Start polling
		kktwch.startPolling();

		return true;
	},

	reset: function () {
		kktwch.stopPolling();
		if (kktwch._viewportObserver) {
			kktwch._viewportObserver.disconnect();
			kktwch._viewportObserver = null;
		}
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
		localStorage.setItem(kktwch.STORAGE_KEY, JSON.stringify(threads));
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
			boardTitle: '',
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
		var info = { postCount: 0, subject: '', threadNo: '', boardUrl: '', boardId: '', url: '' };

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
		if (sessionStorage.getItem('twAutoWatch')) {
			sessionStorage.removeItem('twAutoWatch');
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

		form.addEventListener('submit', function () {
			var restoInput = form.querySelector('input[name="resto"]');

			if (!restoInput || !restoInput.value) {
				// New thread: set flag so we auto-watch after redirect
				sessionStorage.setItem('twAutoWatch', '1');
				return;
			}

			// Reply: watch immediately
			var opPost = document.querySelector('.post.op');
			if (!opPost) return;

			var threadUid = opPost.getAttribute('data-thread-uid');
			if (!threadUid) return;

			var watched = kktwch.getWatchedThreads();
			if (!watched[threadUid]) {
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

		// Track which posts have been seen using an IntersectionObserver
		var seenPosts = new Set();

		kktwch._viewportObserver = new IntersectionObserver(function (entries) {
			var changed = false;
			entries.forEach(function (entry) {
				if (!entry.isIntersecting) return;
				var postEl = entry.target;
				if (seenPosts.has(postEl)) return;
				seenPosts.add(postEl);
				changed = true;
				// Stop observing this post
				kktwch._viewportObserver.unobserve(postEl);
			});

			if (changed) {
				// Update lastSeenCount to the number of posts seen so far
				var w = kktwch.getWatchedThreads();
				var e = w[threadUid];
				if (!e) return;

				// Not yet seeded: let the poll set the real count instead of seeding from
				// the visible-post count (which undercounts on paged / last-X-replies views).
				if (e.lastSeenCount === null || e.lastSeenCount === undefined) return;

				var totalSeen = seenPosts.size;
				if (totalSeen > e.lastSeenCount) {
					e.lastSeenCount = totalSeen;
					kktwch.saveWatchedThreads(w);
					kktwch.renderWatchList();
				}
			}
		}, { threshold: 0.5 });

		// Observe all posts in the thread
		var threadEl = opPost.closest('.thread') || opPost.parentElement;
		if (threadEl) {
			var posts = threadEl.querySelectorAll('.post');
			posts.forEach(function (post) {
				kktwch._viewportObserver.observe(post);
			});
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

	checkAllThreads: function () {
		var watched = kktwch.getWatchedThreads();
		var threadUids = Object.keys(watched);
		var wantNewThreads = _kkSetting('threadWatcherNewThreads');

		// Nothing to do if not watching anything and new-thread alerts are off.
		if (!threadUids.length && !wantNewThreads) return;

		// Cross-tab coordination: only one tab actually polls per interval. Whichever
		// tab fires first claims the shared lock; the others skip the fetch (and so the
		// notifications) and instead refresh their UI from the `storage` event. This
		// stops every open tab from notifying for the same new post.
		var now = Date.now();
		var lastPoll = parseInt(localStorage.getItem('kktwch_lastPoll') || '0', 10);
		if (now - lastPoll < kktwch.POLL_INTERVAL - 1000) return;
		localStorage.setItem('kktwch_lastPoll', now);

		var apiUrl = document.querySelector('meta[name="threadWatcherApiUrl"]')?.content || null;
		if (!apiUrl) return;

		var params = [];
		if (threadUids.length) {
			params.push('thread_uids=' + threadUids.map(encodeURIComponent).join(','));
			// Send the user's own posts so the server can flag threads that quote them.
			var ownTokens = kktwch.getOwnPostTokens();
			if (ownTokens.length) {
				params.push('you=' + ownTokens.map(encodeURIComponent).join(','));
			}
		}
		if (wantNewThreads) {
			params.push('newthreads=1');
			var sinceMark = localStorage.getItem('kktwch_lastThreadSeen') || '';
			if (sinceMark) params.push('since=' + encodeURIComponent(sinceMark));
		}

		var separator = apiUrl.includes('?') ? '&' : '?';
		var url = apiUrl + separator + params.join('&');

		fetch(url)
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
			})
			.catch(function () {
				// Silently fail on network errors
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
			if (nt.latest) localStorage.setItem('kktwch_lastThreadSeen', nt.latest);
			return;
		}

		var items = Array.isArray(nt.items) ? nt.items : [];
		// Cap per cycle so a burst of new threads can't spam a wall of notifications.
		items.slice(0, 5).forEach(function (item) {
			kktwch.notifyNewThread(item);
		});

		if (nt.latest) localStorage.setItem('kktwch_lastThreadSeen', nt.latest);
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
		count = count || 1;

		// Cross-tab dedup: if another tab played the ding within the last 3 seconds, skip.
		var now = Date.now();
		var lastDing = parseInt(localStorage.getItem('kktwch_lastDing') || '0', 10);
		if (now - lastDing < 3000) return;
		localStorage.setItem('kktwch_lastDing', now);

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
		};

		// Clone the content wrapper template
		var contentTpl = document.getElementById('threadWatcherContentTpl');
		if (contentTpl) {
			var clone = contentTpl.content.cloneNode(true);
			kktwch._win.div.appendChild(clone);
		}

		kktwch.renderWatchList();
	},

	renderWatchList: function () {
		var content = $id('threadWatcherContent');
		if (!content) return;

		var watched = kktwch.getWatchedThreads();
		var keys = Object.keys(watched);

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

			// Fill in the link
			var link = row.querySelector('.threadWatcherLink');
			link.href = entry.url || '#';
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

			// Wire up mark-as-read button
			var markReadBtn = row.querySelector('.threadWatcherMarkRead');
			if (hasUnread) {
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

		if (kktwch._originalTitle === null) {
			kktwch._originalTitle = document.title;
		}

		if (total > 0) {
			document.title = '(' + total + ') ' + kktwch._originalTitle;
		} else {
			document.title = kktwch._originalTitle;
		}

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

	/* Settings */
	sett: function (tab, div) {
		if (tab !== 'general') return;
		div.innerHTML += '<label><input type="checkbox" onchange="localStorage.setItem(\'threadWatcherNotifs\',this.checked);if(this.checked)kktwch.requestNotificationPermission();"' +
			(_kkSetting('threadWatcherNotifs') ? ' checked="checked"' : '') +
			'>Thread watcher notifications</label>';
		div.innerHTML += '<label><input type="checkbox" onchange="localStorage.setItem(\'threadWatcherQuotePush\',this.checked);if(this.checked)kktwch.requestNotificationPermission();"' +
			(_kkSetting('threadWatcherQuotePush') ? ' checked="checked"' : '') +
			'>Push notification when quoted</label>';
		div.innerHTML += '<label><input type="checkbox" onchange="localStorage.setItem(\'threadWatcherNewThreads\',this.checked);if(this.checked)kktwch.requestNotificationPermission();"' +
			(_kkSetting('threadWatcherNewThreads') ? ' checked="checked"' : '') +
			'>New thread notifications</label>';
	}
};

if (typeof(KOKOJS) != "undefined") { kkjs.modules.push(kktwch); } else { console.log("ERROR: KOKOJS not loaded!\nPlease load 'koko.js' before this script."); }