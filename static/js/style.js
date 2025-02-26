/* LOL HEYURI */

// Immediately apply the selected style
(function() {
	var savedStyle = localStorage.getItem("stylestyle");
	if (savedStyle) {
		var link = document.getElementsByClassName("linkstyle");
		for (var i = 0; i < link.length; i++) {
			link[i].disabled = true;
			if (link[i].title === savedStyle) {
				link[i].disabled = false;
				break;
			}
		}
	}
})();

var _usercss = localStorage.getItem("usercss");
if (!_usercss) _usercss = '';
document.write('<style id="usercss">' + _usercss + '</style>');

/* Module */
const kkstyle = {
	name: "KK Styles Switcher",
	startup: function() {
		var udel = document.getElementById("userdelete");
		var footer = document.getElementById("footer");
		var styleswitch;

		// Create style switcher
		try {
			if (udel) {
				// If "userdelete" exists, create the style switcher inside it
				styleswitch = document.createElement("div");
				styleswitch.id = "styleSwitcherContainer";
				udel.appendChild(styleswitch);
			} else if (footer) {
				// If "footer" exists but "userdelete" does not, place it above footer
				styleswitch = document.createElement("div");
				styleswitch.id = "styleSwitcherContainer";
				footer.parentNode.insertBefore(styleswitch, footer);
			} else {
				// If neither exist, create style switcher in the body
				styleswitch = document.createElement("div");
				styleswitch.id = "styleSwitcherContainer";
				document.body.appendChild(styleswitch);
			}
		} catch (error) {
			console.error("ERROR: Could not initialize style switcher container.", error);
			return false;  // Return false if any error occurs to prevent module from loading
		}

		styleswitch.id = "styleswitch";
		var lbl = document.createElement("label");
		kkstyle.stylessel = document.createElement("select");
		var link = document.getElementsByClassName("linkstyle");

		lbl.innerText = "Style: ";
		var opt = document.createElement("option");
		opt.value = opt.innerText = "Random";
		kkstyle.stylessel.appendChild(opt);

		// Add options for each available style
		for (var i = 0; i < link.length; i++) {
			opt = document.createElement("option");
			opt.value = opt.innerText = link[i].title;
			kkstyle.stylessel.appendChild(opt);
		}
		lbl.appendChild(kkstyle.stylessel);

		kkstyle.stylessel.onchange = function(event) {
			kkstyle.set_stylesheet(this.value);
		};
		styleswitch.appendChild(lbl);

		// Set the initial stylesheet
		kkstyle.set_stylesheet(localStorage.getItem("stylestyle"), false);
		return true;
	},
	reset: function() {
		var styleswitch = document.getElementById("styleswitch");
		if (styleswitch) styleswitch.remove();
	},
	sett_tab: function(id) {
		var tabElement = document.getElementById(id);
		if (tabElement) {
			tabElement.innerHTML += ' | <a href="javascript:kkjs.sett_tab(\'style\');" id="settab_style">Style</a>';
		}
	},
	sett: function(tab, div) {
		if (tab != "style") return;

		div.innerHTML += '<textarea id="settusercss" class="inputtext" oninput="kkstyle.update_usercss(this.value);" placeholder="Enter your own CSS here">' + _usercss + '</textarea>';
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
			usercssElement.innerHTML = value;
			localStorage.setItem('usercss', value);
		}
	},
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
				localStorage.setItem("stylestyle", active);
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

/* Register */
if (typeof(KOKOJS) != "undefined") {
	kkjs.modules.push(kkstyle);
} else {
	console.error("ERROR: KOKOJS not loaded!\nPlease load 'koko.js' before this script.");
}
