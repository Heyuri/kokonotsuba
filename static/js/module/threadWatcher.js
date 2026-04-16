/* Thread Watcher module
 * Watches threads for new replies using the postApi module.
 * Stores watched threads in localStorage.
 * Uses browser notifications for unread replies.
 */

const kktwch = { name: "KK Thread Watcher",
	STORAGE_KEY: 'threadWatcher',
	POLL_INTERVAL: 60000,
	_pollTimer: null,
	_win: null,

	startup: function () {
		// Hook the form func link
		var links = $q('.postformOption');
		for (var i = 0; i < links.length; i++) {
			if (links[i].textContent.trim() === (document.querySelector('meta[name="threadWatcherLinkText"]')?.content || 'Thread Watcher')) {
				links[i].addEventListener('click', function (e) {
					e.preventDefault();
					kktwch.toggleWindow();
				});
				break;
			}
		}

		// Add "Watch thread" to post widget dropdown for OP posts
		if (window.postWidget && typeof window.postWidget.registerMenuAugmenter === 'function') {
			window.postWidget.registerMenuAugmenter(function (ctx) {
				var post = ctx.post;
				if (!post || !post.classList.contains('op')) return [];

				var threadUid = post.getAttribute('data-thread-uid');
				if (!threadUid) return [];

				var watched = kktwch.getWatchedThreads();
				var isWatched = watched.hasOwnProperty(threadUid);

				return [{
					href: 'javascript:void(0)',
					action: 'watchThread',
					label: isWatched ? 'Unwatch thread' : 'Watch thread',
					params: { threadUid: threadUid }
				}];
			});

			window.postWidget.registerActionHandler('watchThread', function (ctx) {
				var threadUid = ctx.params?.threadUid || ctx.el?.getAttribute('data-param-threadUid') || '';
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

	/* --- Form Submit Hook (auto-watch on reply) --- */

	hookFormSubmit: function () {
		var form = $id('postform');
		if (!form) return;

		form.addEventListener('submit', function () {
			var restoInput = form.querySelector('input[name="resto"]');
			if (!restoInput || !restoInput.value) return; // not a reply

			// Find the thread UID from the page
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
		if (!('Notification' in window) || Notification.permission !== 'granted') return;

		// Don't notify if tab is focused and the watcher window is open
		if (document.hasFocus() && kktwch._win) return;

		var title = entry.subject || 'Thread No.' + (entry.threadNo || entry.threadUid);
		var body = unreadCount + ' new ' + (unreadCount === 1 ? 'reply' : 'replies');

		try {
			var notif = new Notification(title, {
				body: body,
				tag: 'tw_' + entry.threadUid, // Replace previous notif for same thread
				icon: STATIC_URL + 'image/favicon.ico'
			});

			notif.onclick = function () {
				window.focus();
				if (entry.url) {
					window.location.href = entry.url;
				}
				notif.close();
			};
		} catch (e) {
			// Notification api may not be available
		}
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
		var title = 'Thread Watcher';
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

		// Build the content container
		var content = document.createElement('div');
		content.id = 'threadWatcherContent';
		content.style.padding = '6px';
		content.style.overflowY = 'auto';
		content.style.maxHeight = '400px';
		kktwch._win.div.appendChild(content);

		kktwch.renderWatchList();
	},

	renderWatchList: function () {
		var content = $id('threadWatcherContent');
		if (!content) return;

		var watched = kktwch.getWatchedThreads();
		var keys = Object.keys(watched);

		if (!keys.length) {
			content.innerHTML = '<div style="padding:4px;color:#888;">No watched threads.</div>';
			return;
		}

		var html = '<table style="width:100%;border-collapse:collapse;">';
		html += '<tbody>';

		keys.forEach(function (threadUid) {
			var entry = watched[threadUid];
			var unread = kktwch.getUnreadCount(entry);
			var hasUnread = unread > 0;

			var displayName = entry.subject || 'No.' + (entry.threadNo || threadUid);
			// Truncate long subjects
			if (displayName.length > 40) {
				displayName = displayName.substring(0, 37) + '...';
			}

			var linkClass = hasUnread ? 'warning' : '';

			html += '<tr>';
			html += '<td style="padding:2px 4px;">';
			html += '<a href="' + (entry.url || '#') + '" class="' + linkClass + '" onclick="kktwch.markAsRead(\'' + threadUid + '\')">';
			html += kktwch._escHtml(displayName);
			html += '</a>';
			if (hasUnread) {
				html += ' <span class="warning">(' + unread + ')</span>';
			}
			html += '</td>';
			html += '<td style="padding:2px 4px;text-align:right;white-space:nowrap;">';
			html += '<a href="javascript:void(0)" onclick="kktwch.unwatchThread(\'' + threadUid + '\');kktwch.renderWatchList();" title="Unwatch">\u2716</a>';
			html += '</td>';
			html += '</tr>';
		});

		html += '</tbody></table>';
		content.innerHTML = html;
	},

	_escHtml: function (s) {
		return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
	},

	/* Settings */
	sett: function (tab, div) {
		if (tab !== 'general') return;
		div.innerHTML += '<label><input type="checkbox" onchange="localStorage.setItem(\'threadWatcherNotifs\',this.checked);if(this.checked)kktwch.requestNotificationPermission();"' +
			(_kkSetting('threadWatcherNotifs') ? ' checked="checked"' : '') +
			'>Thread Watcher notifications</label>';
	}
};

if (typeof(KOKOJS) != "undefined") { kkjs.modules.push(kktwch); } else { console.log("ERROR: KOKOJS not loaded!\nPlease load 'koko.js' before this script."); }
