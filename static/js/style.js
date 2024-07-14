/* LOL HEYURI
 */

var _usercss = localStorage.getItem("usercss");
if (!_usercss) _usercss = '';
document.write(`<style>
#settusercss {
	height: 300px;
}
</style>`+'<style id="usercss">'+_usercss+'</style>');

/* Module */
const kkstyle = { name: "KK Styles Switcher",
	startup: function () {
		// create style switcher
		var udel = $id("userdelete");
		var styleswitch = udel ?
			udel.insertRow().insertCell() : $doc.body.appendChild($doc.createElement("div"));

		styleswitch.id = "styleswitch";
		styleswitch.align = "RIGHT";
		var lbl = $doc.createElement("label");
		kkstyle.stylessel = $doc.createElement("select");
		var link = $class("linkstyle");
		
		lbl.innerText = "Style: ";
		var opt = $doc.createElement("option");
		opt.value = opt.innerText = "Random";
		kkstyle.stylessel.appendChild(opt);
		for (var i=0; i<link.length; i++) { with (link[i]) {
			opt = $doc.createElement("option");
			opt.value = opt.innerText = title;
			kkstyle.stylessel.appendChild(opt);
		} }
		lbl.appendChild(kkstyle.stylessel);

		kkstyle.stylessel.onchange = function(event) { kkstyle.set_stylesheet(this.value) };
		styleswitch.appendChild(lbl);
		// set stylesheet
		kkstyle.set_stylesheet(localStorage.getItem("stylestyle"), false);

		return true;
	},
	reset: function () {
		$del($id("styleswitch"));
	},
	sett_tab: function (id) {
		$id(id).innerHTML+= ' | <a href="javascript:kkjs.sett_tab(\'style\');" id="settab_style">Style</a>';
	},
	sett: function (tab, div) { if (tab!="style") return;
		div.innerHTML+= '<label for="settusercss">Custom CSS:</label>'+
			'<textarea id="settusercss" oninput="kkstyle.update_usercss(this.value);" cols="48" rows="6" placeholder="Enter your own CSS here">'+_usercss+'</textarea>';
		stylesel = kkstyle.stylessel.cloneNode(true);
		stylesel.onchange = function(event) {
			kkstyle.set_stylesheet(this.value, false)
			stylesel.value = kkstyle.get_active_stylesheet();
		};
		stylesel.value = kkstyle.get_active_stylesheet();
		div.appendChild(stylesel);
	},
	/* usercss */
	update_usercss: function (value) {
		$id("usercss").innerHTML = value;
		localStorage.setItem('usercss', value);
	},
	/* - */
	stylessel: null,
	/* Function */
	set_stylesheet: function(styletitle, _scroll=true) {
		var active = null;
		if (styletitle) {
			var find = false;
			var link = $class("linkstyle");

			for (var i=0; i<link.length; i++) { link[i].disabled = true; }
			if (styletitle!="Random") {
				for (var i=0; i<link.length; i++) {
					if (link[i].title==styletitle) {
						find = true;
						link[i].disabled = false;
					}
				}
			} else {
				// select a random stylesheet
				find = true;
				var rnd_i = Math.floor( Math.random()*link.length );
				link[rnd_i].disabled = false;
			}
			if (!find) {
				console.log("ERROR: Invalid style.");
				kkstyle.set_preferred_stylesheet();
				return;
			} else {
				active = kkstyle.get_active_stylesheet();
				localStorage.setItem("stylestyle", active);
			}
		} else { active = kkstyle.get_active_stylesheet(); }
		kkstyle.stylessel.value = active;
		if (_scroll) kkstyle.stylessel.scrollIntoView({behavior:"smooth",block:"center"});
	},
	get_active_stylesheet: function() {
		var link = $class("linkstyle");
		for (var i=0; i<link.length; i++) { with (link[i]) {
			if (!disabled) return title;
		} }
		return "";
	},
	get_preferred_stylesheet: function() {
		var link = $class("linkstyle");
		for (var i=0; i<link.length; i++) { with (link[i]) {
			if (rel.indexOf("alt")==-1) {
				return title;
			}
		} }
		return "";
	},
	set_preferred_stylesheet: function() {
		kkstyle.set_stylesheet( kkstyle.get_preferred_stylesheet(), false );
	},
};

/* Register */
if(typeof(KOKOJS)!="undefined"){kkjs.modules.push(kkstyle);}else{console.log("ERROR: KOKOJS not loaded!\nPlease load 'koko.js' before this script.");}
