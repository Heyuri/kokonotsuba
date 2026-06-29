/*
 * viewPosts.js — "Show user IPs" toggle for the viewPosts mod tool.
 *
 * Only included on live staff views for users permitted to view raw IP
 * addresses (CAN_VIEW_IP_ADDRESSES), so the setting is exposed to them alone.
 * The viewPosts mod tool renders each post's IP as a [127.0.0.1] control
 * (.ipAddressControl) in the admin functions; when this setting is unticked we
 * add the `hideUserIp` class to <html> and the injected rule hides those
 * controls. Everything is applied while the script runs in <head>, before the
 * posts paint, so toggling is seamless with no flash of IPs.
 */
(function () {
	"use strict";

	var KEY = "showuserip";

	// Stored preference wins; default to showing IPs when unset.
	function showIps() {
		var stored;
		try { stored = localStorage.getItem(KEY); } catch (e) { stored = null; }
		return stored === null ? true : stored === "true";
	}

	function apply(show) {
		document.documentElement.classList.toggle("hideUserIp", !show);
	}

	// Self-contained hide rule so no global stylesheet has to change.
	var style = document.createElement("style");
	style.textContent = "html.hideUserIp .ipAddressControl{display:none}";
	document.head.appendChild(style);

	// Apply before the body renders to avoid a flash of visible IPs.
	apply(showIps());

	// Register the toggle in the Settings window when koko.js is loaded.
	if (typeof kkSetting !== "undefined") {
		kkSetting.add({
			key: KEY,
			label: "Show user IPs",
			checked: showIps,
			onChange: apply
		}, "Moderation");
	}
})();
