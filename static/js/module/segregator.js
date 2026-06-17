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

	// Cookie-backed, so override how the value is read and persisted.
	kkSetting.add({
		key: 'segregatorContent',
		label: 'Enable all content',
		checked: hasCookie,
		store: function () {},
		onChange: function (v) { window._segregatorContentToggle(v); }
	}, 'Browsing');
})();
