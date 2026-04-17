/* Thread watcher module
 * Watches threads for new replies using the postApi module.
 * Stores watched threads in localStorage.
 * Uses browser notifications for unread replies.
 */

const kktwch = { name: "KK Thread watcher",
	STORAGE_KEY: 'threadWatcher',
	POLL_INTERVAL: 60000,
	_pollTimer: null,
	_win: null,

	startup: function () {
		// Hook the form func link
		var links = $q('.postformOption');
		for (var i = 0; i < links.length; i++) {
			if (links[i].textContent.trim() === (document.querySelector('meta[name="threadWatcherLinkText"]')?.content || 'Thread watcher')) {
				links[i].addEventListener('click', function (e) {
					e.preventDefault();
					kktwch.toggleWindow();
				});
				break;
			}
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

	/* --- Watch/Unwatch --- */

	watchCurrentThread: function (threadUid) {
		if (!threadUid) return;

		var watched = kktwch.getWatchedThreads();
		if (watched[threadUid]) return; // already watching

		// Gather thread info from the page
		var info = kktwch.getThreadInfoFromPage(threadUid);

		// Count current posts on page to set as "seen"
		var currentPostCount = info.postCount || 0;

		watched[threadUid] = {
			threadUid: threadUid,
			subject: info.subject || 'No.' + (info.threadNo || threadUid),
			threadNo: info.threadNo || '',
			boardUrl: info.boardUrl || '',
			boardId: info.boardId || '',
			postCount: currentPostCount,
			lastSeenCount: currentPostCount,
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
		if (!threadUids.length) return;

		var apiUrl = getPostApiUrl();
		if (!apiUrl) return;

		threadUids.forEach(function (threadUid) {
			kktwch.checkThread(threadUid, apiUrl);
		});
	},

	checkThread: function (threadUid, apiUrl) {
		var separator = apiUrl.includes('?') ? '&' : '?';
		var url = apiUrl + separator + 'pageName=thread&thread_uid=' + encodeURIComponent(threadUid);

		fetch(url)
			.then(function (res) {
				if (!res.ok) {
					if (res.status === 404) {
						// Thread was deleted, remove from watch list
						kktwch.unwatchThread(threadUid);
						kktwch.renderWatchList();
					}
					return null;
				}
				return res.json();
			})
			.then(function (data) {
				if (!data || !data.posts) return;

				var watched = kktwch.getWatchedThreads();
				var entry = watched[threadUid];
				if (!entry) return;

				var newPostCount = data.post_count;
				var prevPostCount = entry.postCount || 0;

				entry.postCount = newPostCount;
				entry.lastChecked = Date.now();

				// Update subject if we have it from the first post
				if (data.posts.length > 0 && data.posts[0].subject) {
					entry.subject = data.posts[0].subject;
				}

				kktwch.saveWatchedThreads(watched);

				// Send notification if there are new unseen posts
				var unseenCount = newPostCount - (entry.lastSeenCount || 0);
				if (newPostCount > prevPostCount && unseenCount > 0) {
					kktwch.sendNotification(entry, unseenCount);
				}

				kktwch.renderWatchList();
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
		kktwch.saveWatchedThreads(watched);
		kktwch.renderWatchList();
	},

	getUnreadCount: function (entry) {
		return Math.max(0, (entry.postCount || 0) - (entry.lastSeenCount || 0));
	},

	/* --- Notifications --- */

	requestNotificationPermission: function () {
		if ('Notification' in window && Notification.permission === 'default') {
			Notification.requestPermission();
		}
	},

	sendNotification: function (entry, unreadCount) {
		// Check if notifications are enabled in settings
		if (!_kkSetting('threadWatcherNotifs')) return;

		// Don't notify if tab is focused and the watcher window is open
		if (document.hasFocus() && kktwch._win) return;

		var title = entry.subject || 'Thread No.' + (entry.threadNo || entry.threadUid);
		var body = unreadCount + ' new ' + (unreadCount === 1 ? 'reply' : 'replies');

		// Try browser notification first
		if ('Notification' in window && Notification.permission === 'granted') {
			try {
				var notif = new Notification(title, {
					body: body,
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

		// Fallback: play ding sound
		kktwch.playDing();
	},

	playDing: function () {
		if (!kktwch._dingAudio) {
			kktwch._dingAudio = new Audio(STATIC_URL + 'audio/ding.mp3');
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

			var displayName = entry.subject || 'No.' + (entry.threadNo || threadUid);
			if (displayName.length > 40) {
				displayName = displayName.substring(0, 37) + '...';
			}

			var row = rowTpl.content.cloneNode(true);

			// Fill in the link
			var link = row.querySelector('.threadWatcherLink');
			link.href = entry.url || '#';
			link.textContent = displayName;
			link.setAttribute('data-thread-uid', threadUid);
			if (hasUnread) link.classList.add('warning');
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

			list.appendChild(row);
		});
	},

	/* Settings */
	sett: function (tab, div) {
		if (tab !== 'general') return;
		div.innerHTML += '<label><input type="checkbox" onchange="localStorage.setItem(\'threadWatcherNotifs\',this.checked);if(this.checked)kktwch.requestNotificationPermission();"' +
			(_kkSetting('threadWatcherNotifs') ? ' checked="checked"' : '') +
			'>Thread watcher notifications</label>';
	}
};

if (typeof(KOKOJS) != "undefined") { kkjs.modules.push(kktwch); } else { console.log("ERROR: KOKOJS not loaded!\nPlease load 'koko.js' before this script."); }
