(function() {
	'use strict';

	var meta = document.querySelector('meta[name="segregatorConfig"]');
	if (!meta) return;

	var cfg = {
		cookieName:   meta.dataset.cookieName,
		cookieDomain: meta.dataset.cookieDomain,
		cookieMaxAge: meta.dataset.cookieMaxAge,
	};

	function hasCookie() {
		return kkjs.get_cookie(cfg.cookieName) !== '';
	}

	function setCookie() {
		var str = cfg.cookieName + '=1; max-age=' + cfg.cookieMaxAge + '; path=/; SameSite=Lax';
		if (cfg.cookieDomain) str += '; domain=' + cfg.cookieDomain;
		document.cookie = str;
	}

	function clearCookie() {
		var str = cfg.cookieName + '=; max-age=0; path=/; SameSite=Lax';
		if (cfg.cookieDomain) str += '; domain=' + cfg.cookieDomain;
		document.cookie = str;
	}

	window._segregatorContentToggle = function(checked) {
		if (checked) setCookie(); else clearCookie();
	};

	window.settingsHooks.push(function(tab, div) {
		if (tab !== 'general') return;
		div.innerHTML += '<label><input type="checkbox" onchange="window._segregatorContentToggle(this.checked)"'
			+ (hasCookie() ? ' checked="checked"' : '')
			+ '>Enable all content</label>';
	});
})();
