/* LOL HEYURI
 */

const KOKOJS = true;
const STATIC_URL = document.currentScript.src.split('?')[0].replace(/js\/[^/]+\.js$/, ''); // Get the script URL, and remove 'js/{filename}.js'

/* - */
if (typeof(FONTSIZE) === "undefined") { var FONTSIZE = 12; }

const $doc = document;
const $win = window;

const $id = id => $doc.getElementById(id);
const $class = c => $doc.getElementsByClassName(c);
const $tag = tag => $doc.getElementsByTagName(tag);
const $q = q => $doc.querySelectorAll(q);

const $del = del => del.remove();

const $mkcheck = b => (b?' checked="checked"':'');
const $bool = str => str=="true";
const $int = str => parseInt(str);
const $float = str => parseFloat(str);

const $p_class = function (el, c) {
	var p = el.parentNode;
	while (p = p.parentNode){
		if (p==document.body) break;
		if (!p.classList.length) continue;
		if (p.classList.contains(c)) return p;
	}
	return false;
}

/* Window Manager */
const kkwm = {
	windows: Array(),
	w_index: 1,
	w_spawnoffx: 0,
	w_spawnoffy: 0,
	w_spawnoffbouncex: false,
	w_spawnoffbouncey: false,
	_focused: true,
	startup: function () {
		$doc.addEventListener("mouseup", kkwm._evdrag_end);
		$doc.addEventListener("mousemove", kkwm._evmove);
		$doc.addEventListener("mousedown", function (e) {
			if (!e.target.closest(".window")) {
				var wintop = $id("wintop");
				if (wintop) wintop.id = "";
			}
		});
		//kkwm.demo();
	},
	reset: function () {
		kkwm.drag_end();
		$doc.removeEventListener("mouseup", kkwm._evdrag_end);
		$doc.removeEventListener("mousemove", kkwm._evmove);
	},
	/* Function */
	get_win_by_name: function (name) {
		for (var i=0; i<kkwm.windows.length; i++) {
			if (kkwm.windows[i].name == name)
				return kkwm.windows[i];
		}
		return null;
	},
	top: function (name) {
		var win = $kkwm_name(name);
		if (!win) return;

		var wintop = $id("wintop");
		if (wintop) wintop.id = "";

		win.div.id = "wintop";
		win.div.style.zIndex = 100 + kkwm.w_index++;
	},
	demo_index: 1,
	demo: function () {
		if (localStorage.getItem('wm_nodemo')) return;
		var demowin = new kkwmWindow("KKJS Window Manager Demo", {x: 20, y: 20, w: 400, h: 200});
		demowin.div.insertAdjacentHTML("beforeend", `
Demonstrating the new KKJS Window Manager!<br>
Feature list:
<ul>
	<li>Window snapping (can be adjusted in the settings)</li>
	<li>Window clamping</li>
	<li>Window dragging</li>
	<li>Minimize windows</li>
	<li>Pretty CSS animations</li>
</ul>
<button onclick="new kkwmWindow('Test Window '+(kkwm.demo_index++));">Create new window</button>
<button onclick="localStorage.setItem('wm_nodemo', true);$kkwm_name('KKJS Window Manager Demo').remove();">Stop showing this</button>
`);
	},
	/* Dragging */
	wm_drag: null,
	dx: 0, dy: 0,
	drag_start: function (name, cx, cy) {
 		event.preventDefault();
		kkwm.wm_drag = $kkwm_name(name);
		if (!kkwm.wm_drag) return;
		offs = kkwm.wm_drag.div.getBoundingClientRect();
		kkwm.dx = cx - offs.left;
		kkwm.dy = cy - offs.top;
		$doc.body.style.cursor = "move";
	},
	drag_end: function () {
		if (!kkwm.wm_drag) return;
		let xPct = kkwm.wm_drag.rect.x / window.innerWidth;
		let yPct = kkwm.wm_drag.rect.y / window.innerHeight;
		localStorage.setItem("kkwm_pos_" + kkwm.wm_drag.name, JSON.stringify({
			xPct: xPct,
			yPct: yPct
		}));
		kkwm.wm_drag = null;
		kkwm.dx = 0; kkwm.dy = 0;
		$doc.body.style.cursor = "";
	},
	drag_move: function (x, y) {
		if (!kkwm.wm_drag) return;
		x-= kkwm.dx; y-= kkwm.dy;
		kkwm.wm_drag.move(x, y);
	},
	/* Event */
	_evdrag_end: function (event) {
		kkwm.drag_end();
	},
	_evmove: function (event) {
		kkwm.drag_move(event.clientX, event.clientY);
	},
};
const $kkwm_name = name => kkwm.get_win_by_name(name);

class kkwmWindow {
	constructor (name='', rect={w: 300, h: 400}) {
		var exist = $kkwm_name(name);
		if (exist) {
			exist.flash();
			kkwm.top(name);
			delete this;
			return;
		}

		this.name = name;
		this.minimized = false;
		this.div = $doc.createElement("div");
		this.div.classList.add("window");
		this.rect = rect;
		var d = $doc.documentElement;
		// this.div.style.width = rect.w+"px"; // commented out to fix windows not fitting to contents on resize
		// this.div.style.height = rect.h+"px";
		var fontpointfive = FONTSIZE*3.5;
		var storedPos = localStorage.getItem("kkwm_pos_" + name);
		if (storedPos) {
			var pos = JSON.parse(storedPos);
			if (typeof pos.xPct === 'number' && typeof pos.yPct === 'number') {
				rect.x = pos.xPct * d.clientWidth;
				rect.y = pos.yPct * d.clientHeight;
			}
		}
		if (!rect.x) {
			rect.x = Math.floor(d.clientWidth/2-rect.w/2)+kkwm.w_spawnoffx;
			if (rect.x+rect.w+fontpointfive>d.clientWidth) kkwm.w_spawnoffbouncex = true;
			else if (rect.x<fontpointfive) kkwm.w_spawnoffbouncex = false;
			if (kkwm.w_spawnoffbouncex) kkwm.w_spawnoffx-= 40;
			else kkwm.w_spawnoffx+= 40;
		}
		if (!rect.y) {
			rect.y = Math.floor(d.clientHeight/2-rect.h/2)+kkwm.w_spawnoffy;
			if (rect.y+rect.h+fontpointfive>d.clientHeight) kkwm.w_spawnoffbouncey = true;
			else if (rect.y<fontpointfive) kkwm.w_spawnoffbouncey = false;
			if (kkwm.w_spawnoffbouncey) kkwm.w_spawnoffy-= 40;
			else kkwm.w_spawnoffy+= 40;
		}
		this.move(rect.x, rect.y);
		this.div.onmousedown = function () { kkwm.top(name); };
		this.div.innerHTML = '<div class="winbar" data-name="'+name+'" onmousedown="if (!(event.target.closest(\'.winctrl\'))) kkwm.drag_start(\''+name+'\',event.clientX,event.clientY);">'+
		'<div class="winname">'+name+'</div><div class="winctrl">'+
			'<button onclick="$kkwm_name(\''+name+'\').minimize();" class="winmin" title="Minimize/maximize"><img alt="-" width="16" height="16" src="'+STATIC_URL+'image/btn-min.svg"></button>'+
			'<button onclick="$kkwm_name(\''+name+'\').remove();" class="winclose" title="Close"><img alt="X" width="16" height="16" src="'+STATIC_URL+'image/btn-close.svg"></button>'+
		'</div></div>';
		$doc.body.appendChild(this.div);
		kkwm.windows.push(this);
		kkwm.top(name);
	}
	minimize() {
		this.minimized = !this.minimized;
		if (typeof(this.onminimize)=='function') this.onclose(this.minimized);
		var winmin = this.div.getElementsByClassName("winmin")[0];
		if (this.minimized) {
			this.div.classList.add("minimized");
		} else {
			this.div.classList.remove("minimized");
		}
	}
	remove() {
		// kkwm.windows.pop(this);
		for (var i=0; i<kkwm.windows.length; i++) {
			if (kkwm.windows[i].name == this.name) {
				kkwm.windows.splice(i, 1);
				break;
			}
		}
		this.div.classList.add("wclosing");
		var that = this;
		setTimeout( function () {
			if (typeof(that.onclose)=='function') that.onclose();
			$del(that.div);
		}, 99);
	}
	move(x, y) {
		var offs = this.div.getBoundingClientRect();
		// clamp to other windows
		var wm_innerclamp = localStorage.getItem("wm_innerclamp"); if (!wm_innerclamp) wm_innerclamp=10;
		var wm_outerclamp = localStorage.getItem("wm_outerclamp"); if (!wm_outerclamp) wm_outerclamp=20;
		for (var i=0; i<kkwm.windows.length; i++) {
			var win = kkwm.windows[i];
			if (win==this) continue;
			var winoffs = win.div.getBoundingClientRect();
			// inside (left,top)
			if (!Math.round((win.rect.x-x)/wm_innerclamp))
				x = win.rect.x;
			if (!Math.round((win.rect.y-y)/wm_innerclamp))
				y = win.rect.y;
			// inside (right,bottom)
			if (!Math.round(((win.rect.x+winoffs.width)-(x+offs.width))/wm_innerclamp))
				x = win.rect.x+winoffs.width-offs.width;
			if (!Math.round(((win.rect.y+winoffs.height)-(y+offs.height))/wm_innerclamp))
				y = win.rect.y+winoffs.height-offs.height;
			// outside (left,top)
			if (!Math.round((win.rect.x-(x+offs.width))/wm_outerclamp))
				x = win.rect.x-offs.width;
			if (!Math.round((win.rect.y-(y+offs.height))/wm_outerclamp))
				y = win.rect.y-offs.height;
			// outside (right,bottom)
			if (!Math.round(((win.rect.x+winoffs.width)-x)/wm_outerclamp))
				x = win.rect.x+winoffs.width;
			if (!Math.round(((win.rect.y+winoffs.height)-y)/wm_outerclamp))
				y = win.rect.y+winoffs.height;
		}
		// clamp to screen boundries
		var d = $doc.documentElement;
		if (x+offs.width>d.clientWidth) x = d.clientWidth-offs.width;
		if (y+offs.height>d.clientHeight) y = d.clientHeight-offs.height;
		if (x<0) x = 0;
		if (y<0) y = 0;
		this.rect.x = Math.floor(x);
		this.rect.y = Math.floor(y);
		var style = this.div.style;
		style.left = (this.rect.x/d.clientWidth*100)+"%";
		style.top = (this.rect.y/d.clientHeight*100)+"%";
	}
	flash(time=2) {
		var _this = this;
		for (var i=0; i<time; i++) {
			setTimeout(function () { _this.div.style.filter = "invert(1)"; }, i*100);
			setTimeout(function () { _this.div.style.filter = "invert(0)"; }, i*100+50);
		}
	}
};

/* Koko JS */
const kkjs = {
	modules: Array(),
	posts: null,
	startup: function () {
		kkjs.posts = $class("post");
		// Load stored name, email, and password
    kkjs.l();

    // Always Noko setting
    if (!localStorage.getItem("alwaysnoko")) {
        localStorage.setItem("alwaysnoko", "true");
    }

		// Initialize email behavior and file upload reset
    kkjs.ee();
    kkjs.fup();

		// Initialization logic for centerthreads, persistpager, persistnav
		const body = document.body;
		if (localStorage.getItem("centerthreads") === "true") {
			body.classList.add("centerthreads");
		} else {
			body.classList.remove("centerthreads");
		}

		if (localStorage.getItem("persistpager") === "true") {
			body.classList.add("persistpager");
		} else {
			body.classList.remove("persistpager");
		}

		if (localStorage.getItem("persistnav") === "true") {
			body.classList.add("persistnav");
			// body.insertAdjacentHTML("afterbegin", '<br>');
		} else {
			body.classList.remove("persistnav");
		}

		if (localStorage.getItem("neomenu") === "true") {
			body.classList.add("neomenuEnabled");
			// body.insertAdjacentHTML("afterbegin", '<br>');
		} else {
			body.classList.remove("neomenuEnabled");
		}

		if (localStorage.getItem("tripkeys")=="true") {
			kkjs.applyTripKeys();
		}

		kkjs.modules.forEach(function (mod) {
			try {
				if (!mod.startup()) {
						console.log("ERROR: Fatal error in module '" + mod.name + "'.");
						kkjs.modules.pop(mod);
				}
			} catch (error) {
				console.log("ERROR: Fatal error in module '" + mod.name + "'");
				kkjs.modules.pop(mod);
			}
		});

		
		kkjs.setInitialMenuState();

		kkwm.startup();
		kkjs.sett_init();

		// Initialize tooltips if necessary
    //kkjs.wztt();
	},
	reset: function () {
		kkjs.modules.forEach( function(mod) {
			mod.reset();
		} );
		$del($id("formfuncs"));
		kkjs.wztt($doc.body, true);
		kkjs.make_quote_index();
	},
	/* Function */
	get_cookie: function (key) {
		var m=$doc.cookie.match("(^|;\\s+)"+key+"=(.*?)(;|$)");
		if (m&&m.length>2) return unescape(m[2]);
		else return '';
	},
	set_cookie: function (key, value, time=0) {
		var expires = "";
		if(time) {
			var date = new Date();
			date.setTime(date.getTime() + time);
			var expires="; expires="+date.toGMTString();
		}
		$doc.cookie = key+"="+value+expires+"; path=/";
	},
	l: function () {
		var P=kkjs.get_cookie("pwdc"), N=kkjs.get_cookie("namec"), E=kkjs.get_cookie("emailc"), i;
		for (i=0; i<$doc.forms.length; i++) {
			with ($doc.forms[i]) {
				if (typeof(pwd)!='undefined') { pwd.value=P; }
				if (typeof(name)!='undefined') { name.value=decodeURIComponent(escape(N)); }
				if (typeof(email)!='undefined') { email.value=E; }
			}
		}
	},
	com_insert: function (str) {
		var com = $id("com"), qrcom =  $id("qrcom");
		if (!com) return false;
		if (qrcom) com = qrcom;

		// Check if kkgal exists and has the necessary properties
		if (typeof kkgal !== 'undefined' && kkgal.gframe) {
			kkgal.contract();
		}

		com.value+= str;
		if (com != qrcom) // Don't scroll to the QR form
			com.scrollIntoView({behavior:"smooth",block:"center"});
		com.focus();

		var event = $doc.createEvent("HTMLEvents");
		event.initEvent("input", true, true);
		com.dispatchEvent(event);
		return true;
	},

	//futallaby tripkeys
	applyTripKeys: function() {
		let trips = document.getElementsByClassName("postertrip");
		for(let i=0;i<trips.length;i++){
			trips[i].innerHTML=trips[i].innerHTML.replace(RegExp("^"+"★"),"!!");
			trips[i].innerHTML=trips[i].innerHTML.replace(RegExp("^"+"◆"),"!");
		}
	},

	// email
	ee: function () {
		var email = $id("email");
		if (!email) return;
		// Insert checkboxes after the email field
		email.insertAdjacentHTML("afterend", '<div id="emailjs">'+
			'<label class="nokosagedump" title="Thread will not bump on reply"><input type="checkbox" class="nokosagedump" onclick="kkjs.ee2(this.id, this.checked);" id="sage">sage</label>'+
			'<label class="nokosagedump" title="Stay in thread after replying, jump to your reply"><input type="checkbox" class="nokosagedump" onclick="kkjs.ee2(this.id,this.checked);" id="noko">noko</label>'+
			'<label class="nokosagedump" title="Stay in thread after replying, remain at top of page"><input type="checkbox" class="nokosagedump" onclick="kkjs.ee2(this.id,this.checked);" id="dump">dump</label>'+
		'</div>');

		// If 'alwaysnoko' is set to true in localStorage, and email field is empty, add 'noko' to email value
		if (localStorage.getItem("alwaysnoko")=="true") {
			if (email.value == "") // Only add if blank
			email.value+= 'noko';
			$id('noko').checked = true;
		}
	
		// Check if 'sage' or 'noko' are already in the email value, and check the corresponding checkboxes
		if (email.value.includes("sage")) {
			$id('sage').checked = true;
		}
		if (email.value.includes("noko")) {
			$id('noko').checked = true;
		}
		if (email.value.includes("dump")) {
			$id('dump').checked = true;
		}

		// Add input listener to the email field
		email.addEventListener("input", kkjs.ee3);
	},
	
	ee2: function (value, mode) {
		// When a checkbox is toggled, add/remove the corresponding value ('sage', 'noko', 'dump') to/from the email field
		if (mode) {
			email.value += value;
			// Uncheck the other checkbox if 'noko' or 'dump' is checked
			if (value === "noko") {
				$id("dump").checked = false;
				email.value = email.value.replace("dump", ""); // Remove 'dump' from email field
			} else if (value === "dump") {
				$id("noko").checked = false;
				email.value = email.value.replace("noko", ""); // Remove 'noko' from email field
			}
		} else {
			email.value = email.value.replace(value, "");
		}
		$id(value).checked = mode;
	},
	
	ee3: function (event) {
		$id("dump").checked = this.value.match("dump");
		$id("noko").checked = this.value.match("noko");
		if ($id("dump").checked) {
			$id("noko").checked = false;
		}		
		$id("sage").checked = this.value.match("sage");
	},

	fup: function () {
		var upf = $id("upfile");
		if (upf) upf.insertAdjacentHTML('afterend',
			'<span id="clearFile">[<a href="javascript:void(0);" onclick="$id(\'upfile\').value=\'\';" title="Clear file selection">X</a>]</span>');
	},
	
	// form switch
	form_index: 0,
	previous_position: {
		parent: null,
		nextSibling: null
	},
	form_switch: function () {
		const a = Array($id("postarea"), $id("postarea2"));
		if (!(a[0] && a[1])) return;

		const pform = $id("postform");

		// Store the current position before moving
		if (!kkjs.previous_position.parent) {
			kkjs.previous_position.parent = pform.parentNode;
			kkjs.previous_position.nextSibling = pform.nextSibling;
		}

		// Determine the target container
		const targetContainer = a[kkjs.form_index = kkjs.form_index ? 0 : 1];

		// Move the form to the new location
		targetContainer.appendChild(pform);
		pform.scrollIntoView({ behavior: "smooth", block: "center" });

		// Swap logic: if moved back, restore to the original position
		if (kkjs.form_index === 0 && kkjs.previous_position.parent) {
			if (kkjs.previous_position.nextSibling) {
				kkjs.previous_position.parent.insertBefore(pform, kkjs.previous_position.nextSibling);
			} else {
				kkjs.previous_position.parent.appendChild(pform);
			}

			// Clear the stored position after restoring
			kkjs.previous_position.parent = null;
			kkjs.previous_position.nextSibling = null;
		}
	},

	
	// wz_tooltip
	wztt: function (el=$doc.body, reset=false) {
		var tips = el.querySelectorAll("[title],[data-tip]");
		if (reset) {
			for (var i=0;i<tips.length;i++){
				var t = tips[i].getAttribute("data-tip");
				if (!t) continue;
				tips[i].removeEventListener("mouseover", kkjs.wztt_evmover);
				tips[i].removeEventListener("mouseout", kkjs.wztt_evout);
				tips[i].title = t;
			}
		} else {
			if (!$id("wz_tooltip")) return;
			for (var i=0;i<tips.length;i++){
				if (!tips[i].title) continue;
				if (tips[i].parentNode==$doc.head) continue;
				tips[i].setAttribute("data-tip", tips[i].title);
				tips[i].title = "";
				tips[i].addEventListener("mouseover", kkjs.wztt_evmover);
				tips[i].addEventListener("mouseout", kkjs.wztt_evmout);
			}
		}
	},
	wztt_evmover: function (event) { Tip(this.getAttribute("data-tip")); },
	wztt_evmout: function (event) { UnTip(); },
	// Settings
	sett_init: function () {
		var ab = $class("adminbar");
		if (ab.length) 
			ab[0].insertAdjacentHTML("afterbegin", '[<a href="javascript:void(0);" onclick="kkjs.sett_open(this);">Settings</a>] ');
	},
	sett_open: function (el) {
		var win;
		if (win = $kkwm_name("Settings")) {
			win.remove();
			return;
		}
		var offs = el.getBoundingClientRect();
		var d = $doc.documentElement;
		win = new kkwmWindow("Settings", {
			x: offs.right - 125,
			y: offs.bottom + 10,
			w: 300,
			h: 400,
		});
		win.div.innerHTML+= '<div id="settcontents"><div id="settabs"><a href="javascript:kkjs.sett_tab(\'general\');" id="settab_general">General</a></div>'+
			'<hr class="hrThin">'+
			'<div id="settarea"></div></div>';
		for (var i=0; i<kkjs.modules.length; i++) {
			var mod = kkjs.modules[i];
			if (typeof(mod.sett_tab)=='function')
				mod.sett_tab("settabs");
		}
		kkjs.sett_tab("general");
	},
	sett_tab: function (tab) {
		var tabar = $id("settabs");
		for (var i=0; i<tabar.childNodes.length; i++) {
			var _ta = tabar.childNodes[i];
			if (_ta.nodeName!="A") continue;
			// Remove the 'settab_selected' class from all tabs
			_ta.classList.remove('settab_selected');
	
			// Add the 'settab_selected' class to the selected tab
			if (_ta.id == "settab_" + tab) {
				_ta.classList.add('settab_selected');
			}
		}

		var div = $id("settarea");
		div.innerHTML = '';
		
		if (tab == "general") {
			div.innerHTML += `
				<label><input type="checkbox" onchange="localStorage.setItem('neomenu',this.checked);document.body.classList.toggle('neomenuEnabled', this.checked);kkjs.toggleNeomenu(this.checked);" ${(localStorage.getItem("neomenu") === "true" ? 'checked="checked"' : '')}>Use neomenu</label>
				<label><input type="checkbox" onchange="localStorage.setItem('persistnav',this.checked);document.body.classList.toggle('persistnav', this.checked);" ${(localStorage.getItem("persistnav") === "true" ? 'checked="checked"' : '')}>Persistent navigation</label>
				<label><input type="checkbox" onchange="localStorage.setItem('persistpager',this.checked);document.body.classList.toggle('persistpager', this.checked);" ${(localStorage.getItem("persistpager") === "true" ? 'checked="checked"' : '')}>Persistent pager</label>
				<label><input type="checkbox" onchange="localStorage.setItem('alwaysnoko',this.checked);document.body.classList.toggle('alwaysnoko', this.checked);" ${(localStorage.getItem("alwaysnoko") === "true" ? 'checked="checked"' : '')}>Always noko</label>
				<label><input type="checkbox" onchange="localStorage.setItem('centerthreads',this.checked);document.body.classList.toggle('centerthreads', this.checked);" ${(localStorage.getItem("centerthreads") === "true" ? 'checked="checked"' : '')}>Center threads</label>
				<label><input type="checkbox" onchange="localStorage.setItem('tripkeys', this.checked);" ${(localStorage.getItem("tripkeys") === "true" ? 'checked="checked"' : '')}>Futallaby style tripkeys</label>
			`;
		}
		for (var i=0; i<kkjs.modules.length; i++) {
			var mod = kkjs.modules[i];
			if (typeof(mod.sett)=="function")
				mod.sett(tab, div);
		}
	}
};

const kkjs_copycode = {
	name: "copycode",
	startup: function () {
		const copyToClipboard = (text, button) => {
			navigator.clipboard.writeText(text).then(() => {
				button.textContent = 'Copied!';
				setTimeout(() => {
					button.textContent = '';
					button.appendChild(getSVGIcon());
				}, 2000);
			}, (err) => {
				console.error('Failed to copy code: ', err);
			});
		};

		const getSVGIcon = () => {
			const svgIcon = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
			svgIcon.setAttribute('width', '24');
			svgIcon.setAttribute('height', '24');
			svgIcon.setAttribute('fill', 'none');
			svgIcon.setAttribute('viewBox', '0 0 24 24');
			const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
			path.setAttribute('fill', 'currentColor');
			path.setAttribute('fill-rule', 'evenodd');
			path.setAttribute('d', 'M7 5a3 3 0 0 1 3-3h9a3 3 0 0 1 3 3v9a3 3 0 0 1-3 3h-2v2a3 3 0 0 1-3 3H5a3 3 0 0 1-3-3v-9a3 3 0 0 1 3-3h2zm2 2h5a3 3 0 0 1 3 3v5h2a1 1 0 0 0 1-1V5a1 1 0 0 0-1-1h-9a1 1 0 0 0-1 1zM5 9a1 1 0 0 0-1 1v9a1 1 0 0 0 1 1h9a1 1 0 0 0 1-1v-9a1 1 0 0 0-1-1z');
			path.setAttribute('clip-rule', 'evenodd');
			svgIcon.appendChild(path);
			return svgIcon;
		};

		const addButton = (elem) => {
			if (elem.querySelector('.copyButton')) return;

			const button = document.createElement('button');
			button.type = 'button';
			button.className = 'copyButton';
			button.appendChild(getSVGIcon());

			button.addEventListener('click', (e) => {
				e.stopPropagation();
				const codeElem = elem.querySelector('code');
				const text = codeElem ? codeElem.innerText : elem.innerText;
				copyToClipboard(text, button);
			});

			elem.style.position = 'relative';
			elem.appendChild(button);
		};

		const processCodeBlocks = () => {
			const codeBlocks = document.querySelectorAll('pre.code');
			codeBlocks.forEach(addButton);
		};

		const observer = new MutationObserver((mutations) => {
			mutations.forEach((mutation) => {
				mutation.addedNodes.forEach((node) => {
					if (node.nodeType === 1) {
						if (node.matches('pre.code')) {
							addButton(node);
						} else {
							node.querySelectorAll('pre.code').forEach(addButton);
						}
					}
				});
			});
		});

		processCodeBlocks();
		observer.observe(document.body, { childList: true, subtree: true });

		return true;
	},
	reset: function () {
		// No-op
	}
};

kkjs.modules.push(kkjs_copycode);


window.addEventListener("DOMContentLoaded", kkjs.startup);

// Function to set initial menu state based on localStorage
kkjs.setInitialMenuState = function() {
  // Check if 'neomenu' exists in localStorage
  let isNeoMenuEnabled = localStorage.getItem("neomenu");

  // Only proceed if 'neomenu' is explicitly set
  if (isNeoMenuEnabled !== null) {
    let neoMenuEnabled = isNeoMenuEnabled === "true";
    
    // Ensure the classic and neo menus exist before toggling
    const classicMenu = document.querySelector('.classicmenu');
    const neoMenu = document.querySelector('.neomenu');
    
    if (classicMenu && neoMenu) {
      // Toggle visibility based on 'neomenu' setting
      classicMenu.hidden = neoMenuEnabled;
			neoMenu.hidden = !neoMenuEnabled;
			classicMenu.classList.toggle('hidden', neoMenuEnabled);
			neoMenu.classList.toggle('hidden', !neoMenuEnabled);
    } else {
      console.warn('Menu elements not found. Skipping menu state initialization.');
    }
	}
};

// Function to toggle the Neo menu on demand
kkjs.toggleNeomenu = function(enabled) {
  // Update the 'neomenu' setting in localStorage
  localStorage.setItem('neomenu', enabled);
  // Re-run to update menu display based on new setting
  kkjs.setInitialMenuState();
};
