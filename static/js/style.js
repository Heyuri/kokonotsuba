/* LOL HEYURI */

/** kokoimg and kokotxt have different styles, so each set keeps its own keys in the storage hub. */
var KKSTYLE_SET = (function() {
	var l = document.querySelector('link.linkstyle[data-style-set]');
	return l ? l.getAttribute('data-style-set') : 'kokoimg';
})();
var KKSTYLE_KEY = 'stylestyle_' + KKSTYLE_SET;
var KKUSERCSS_KEY = 'usercss_' + KKSTYLE_SET;

/** Migrate the old per-subdomain keys into the local mirror; the hub snapshot adopts or uploads them. */
(function() {
	try {
		if (localStorage.getItem(KKSTYLE_KEY) === null) {
			var old = localStorage.getItem('stylestyle');
			if (old && document.querySelector('link.linkstyle[title="' + CSS.escape(old) + '"]')) {
				localStorage.setItem(KKSTYLE_KEY, old);
				localStorage.removeItem('stylestyle');
			}
		}
		if (localStorage.getItem(KKUSERCSS_KEY) === null) {
			var oldCss = localStorage.getItem('usercss');
			if (oldCss) localStorage.setItem(KKUSERCSS_KEY, oldCss);
		}
	} catch (e) {}
})();
kkStore.registerShared([KKSTYLE_KEY, KKUSERCSS_KEY]);

/** The pick is also a parent-domain cookie, readable on every subdomain before first paint. */
var KKSTYLE_COOKIE = 'kkstyle_' + KKSTYLE_SET;
function kkStyleCookieGet() {
	var m = document.cookie.match(new RegExp('(?:^|; )' + KKSTYLE_COOKIE + '=([^;]*)'));
	if (!m) return null;
	try { return decodeURIComponent(m[1]); } catch (e) { return null; }
}
function kkStyleCookieSet(title) {
	var host = location.hostname;
	var parts = host.split('.');
	var domain = (parts.length > 2 && !/^[\d.]+$/.test(host)) ? '; domain=.' + parts.slice(1).join('.') : '';
	document.cookie = KKSTYLE_COOKIE + '=' + encodeURIComponent(title) + '; path=/; max-age=31536000; SameSite=Lax'
		+ domain + (location.protocol === 'https:' ? '; Secure' : '');
}
/** Remember a pick everywhere: hub (and its local mirror) plus the cookie. */
function kkStyleSave(title) {
	if (kkStore.get(KKSTYLE_KEY) !== title) kkStore.set(KKSTYLE_KEY, title);
	if (kkStyleCookieGet() !== title) kkStyleCookieSet(title);
}

/** Remove the load-time copy of the saved style once a different one is active. */
function kkDropBootStyle(title) {
	var boot = document.getElementById("kkstyleboot");
	if (boot && boot.getAttribute("data-title") !== title) boot.remove();
}

/** Enable the link titled `title`; false when this set has no such style. */
function kkApplyStyle(title) {
	var link = document.getElementsByClassName("linkstyle");
	var found = false;
	for (var i = 0; i < link.length; i++) {
		if (link[i].title === title) found = true;
	}
	if (!found) return false;
	kkDropBootStyle(title);
	for (var i = 0; i < link.length; i++) {
		link[i].disabled = link[i].title !== title;
	}
	return true;
}

// Immediately apply the selected style
(function() {
	// Every pick writes the cookie, so it is the newest copy; push it to the hub if the mirror lags
	var savedStyle = kkStyleCookieGet() || kkStore.get(KKSTYLE_KEY);
	if (!savedStyle || !kkApplyStyle(savedStyle)) return;
	kkStyleSave(savedStyle);
	// A sheet enabled from script does not block rendering; a written one does, so no unstyled paint
	var link = document.getElementsByClassName("linkstyle");
	for (var i = 0; i < link.length; i++) {
		if (link[i].title !== savedStyle || link[i].rel.indexOf("alt") === -1) continue;
		var esc = function(v) { return v.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;'); };
		document.write('<link id="kkstyleboot" rel="stylesheet" href="' + esc(link[i].href) + '" data-title="' + esc(savedStyle) + '">');
		break;
	}
})();

document.write('<style id="usercss"></style>');
document.getElementById("usercss").textContent = kkStore.get(KKUSERCSS_KEY) || '';

/* Module */
const kkstyle = {
	name: "KK Styles Switcher",
	startup: function() {
		kkstyle.stylessel = document.getElementById("styleSwitcherSelect");
		if (!kkstyle.stylessel) return false;

		// Options are pre-populated by inline script in FOOTER.tpl
		// Just populate if empty (fallback)
		if (!kkstyle.stylessel.options.length) {
			var link = document.getElementsByClassName("linkstyle");

			var opt = document.createElement("option");
			opt.value = opt.innerText = "Random";
			kkstyle.stylessel.appendChild(opt);

			for (var i = 0; i < link.length; i++) {
				opt = document.createElement("option");
				opt.value = opt.innerText = link[i].title;
				kkstyle.stylessel.appendChild(opt);
			}
		}

		kkstyle.stylessel.onchange = function(event) {
			kkstyle.set_stylesheet(this.value);
		};

		// Set the initial stylesheet
		kkstyle.set_stylesheet(kkStyleCookieGet() || kkStore.get(KKSTYLE_KEY), false);

		// Picks made on other subdomains arrive through the hub
		kkStore.onReady(kkstyle.sync_from_store);
		kkStore.onChange(function(key) {
			if (key === KKSTYLE_KEY || key === KKUSERCSS_KEY) kkstyle.sync_from_store();
		});
		return true;
	},
	/** Re-apply the stored style and user CSS without writing them back. */
	sync_from_store: function() {
		var saved = kkStore.get(KKSTYLE_KEY);
		if (saved && saved !== kkstyle.get_active_stylesheet() && kkApplyStyle(saved)) {
			// Through set_stylesheet so wrappers (sideNav) update their selects
			kkstyle.set_stylesheet(saved, false);
		} else if (saved && kkStyleCookieGet() !== saved) {
			kkStyleCookieSet(saved);
		}
		var css = kkStore.get(KKUSERCSS_KEY) || '';
		var el = document.getElementById("usercss");
		if (el && el.textContent !== css) el.textContent = css;
		var textarea = document.getElementById("settusercss");
		if (textarea && document.activeElement !== textarea) textarea.value = css;
	},
	reset: function() {
		if (kkstyle.stylessel) kkstyle.stylessel.innerHTML = '';
	},
	sett_tab: function(id) {
		var tabElement = document.getElementById(id);
		if (tabElement) {
			tabElement.innerHTML += ' | <a href="javascript:kkjs.sett_tab(\'style\');" id="settab_style">Style</a>';
		}
	},
	sett: function(tab, div) {
		if (tab != "style") return;
	
		var latestUserCSS = kkStore.get(KKUSERCSS_KEY) || '';
	
		// Create and insert textarea
		div.innerHTML += '<textarea id="settusercss" class="inputtext" oninput="kkstyle.update_usercss(this.value);" placeholder="Enter your own CSS here"></textarea>';
		
		var textarea = document.getElementById("settusercss");
		if (textarea) {
			textarea.value = latestUserCSS;
		}
	
		// Clone and insert style selector
		var stylesel = kkstyle.stylessel.cloneNode(true);
		stylesel.onchange = function(event) {
			kkstyle.set_stylesheet(this.value, false);
			stylesel.value = kkstyle.get_active_stylesheet();
		};
		stylesel.value = kkstyle.get_active_stylesheet();
		div.appendChild(stylesel);
	},
	update_usercss: function(value) {
		var usercssElement = document.getElementById("usercss");
		if (usercssElement) {
			usercssElement.textContent = value;
			// Save once typing pauses, so keystrokes never trip kkStore's flood guard
			kkstyle._usercssPending = value;
			clearTimeout(kkstyle._usercssTimer);
			kkstyle._usercssTimer = setTimeout(kkstyle.flush_usercss, 500);
		}
	},
	flush_usercss: function() {
		clearTimeout(kkstyle._usercssTimer);
		if (kkstyle._usercssPending === null) return;
		kkStore.set(KKUSERCSS_KEY, kkstyle._usercssPending);
		kkstyle._usercssPending = null;
	},
	_usercssPending: null,
	_usercssTimer: null,
	set_stylesheet: function(styletitle, _scroll = true) {
		var active = null;
		if (styletitle) {
			var find = false;
			var link = document.getElementsByClassName("linkstyle");

			for (var i = 0; i < link.length; i++) {
				link[i].disabled = true;
			}

			if (styletitle !== "Random") {
				for (var i = 0; i < link.length; i++) {
					if (link[i].title == styletitle) {
						find = true;
						link[i].disabled = false;
					}
				}
			} else {
				find = true;
				var rnd_i = Math.floor(Math.random() * link.length);
				link[rnd_i].disabled = false;
			}

			if (!find) {
				console.error("ERROR: Invalid style.");
				kkstyle.set_preferred_stylesheet();
				return;
			} else {
				active = kkstyle.get_active_stylesheet();
				kkDropBootStyle(active);
				kkStyleSave(active);
			}
		} else {
			active = kkstyle.get_active_stylesheet();
		}

		kkstyle.stylessel.value = active;
		if (_scroll) kkstyle.stylessel.scrollIntoView({ behavior: "smooth", block: "center" });
	},
	get_active_stylesheet: function() {
		var link = document.getElementsByClassName("linkstyle");
		for (var i = 0; i < link.length; i++) {
			if (!link[i].disabled) return link[i].title;
		}
		return "";
	},
	get_preferred_stylesheet: function() {
		var link = document.getElementsByClassName("linkstyle");
		for (var i = 0; i < link.length; i++) {
			if (link[i].rel.indexOf("alt") == -1) {
				return link[i].title;
			}
		}
		return "";
	},
	set_preferred_stylesheet: function() {
		kkstyle.set_stylesheet(kkstyle.get_preferred_stylesheet(), false);
	},
};

/** Prefetch the inactive styles once idle, so a switch does not wait on the network. */
window.addEventListener('load', function() {
	var idle = window.requestIdleCallback || function(cb) { setTimeout(cb, 200); };
	idle(function() {
		var link = document.getElementsByClassName("linkstyle");
		for (var i = 0; i < link.length; i++) {
			if (!link[i].disabled) continue;
			var p = document.createElement('link');
			p.rel = 'prefetch';
			p.as = 'style';
			p.href = link[i].href;
			document.head.appendChild(p);
		}
	});
});

// Leaving the page mid-pause still saves the last edit
window.addEventListener('pagehide', function() { kkstyle.flush_usercss(); });

/* Register */
if (typeof(KOKOJS) != "undefined") {
	kkjs.modules.push(kkstyle);
} else {
	console.error("ERROR: KOKOJS not loaded!\nPlease load 'koko.js' before this script.");
}
