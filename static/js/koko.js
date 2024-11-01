/* LOL HEYURI
 */

const KOKOJS = true;
const STATIC_URL = './static/';

document.write(`<style>
#formfuncs a, a.linkjs {
	text-decoration: underline;
	color: inherit;
}
/* Window Manager */
@keyframes fade {
	0% { filter: opacity(0); transform: scale(0.95); }
	100% { filter: opacity(1); transform: scale(1); }
}
@keyframes fadeout {
	0% { filter: opacity(1); transform: scale(1); }
	100% { filter: opacity(0); transform: scale(0.95); }
}
.window {
	position: fixed;
	z-index: 100;
	padding: 1.5em 0.5em 0.5em;
	background-color: inherit;
	border-style: solid;
	border-width: 1px;
	opacity: 0.9;
	overflow: hidden;
	resize: both;
	animation: fade 0.1s;
}
.wclosing {
	animation: fadeout 0.1s;
}
#wintop, .window:hover {
	opacity: 1;
}
.window.minimized {
	width: 12em!important;
	height: 0!important;
	padding-bottom: 0;
	resize: none;
}
.winbar {
	position: absolute;
	top: 0;
	left: 0;
	width: 100%;
	cursor: move;
}
.winctrl {
	background-image: inherit;
	display: inline-block;
	float: right;
	position: absolute;
	line-height: 1.15em;
	right: 0;
	top: 0;
}
.winname {
	display: inline-block;
	margin: 0.2em 0.4em;
	font-family: Trebuchet MS, Tohama, Verdana;
}
.winctrl>* {
	display: inline-block;
	text-decoration: none;
	transition: all 0.1s;
}
.winmin {
	border-right-style: solid;
	border-width: 1px;
	padding: 0 0.3em;
}


/* Settings */
#settarea {
	display: flex;
	flex-direction: column;
	padding-bottom: 1em;
}
#settarea>* {
	display: flex;
	flex-direction: row;
	transition: all 0.1s;
}
#settarea [type=number] {
	width: 5em;
}
</style>
<style id="jscenterthreads">
.thread {
	width: 75%;
	margin: auto;
}
</style>
<style id="jspersistpager">
#pager {
	position: fixed;
	left: 8px;
	bottom: 8px;
}
</style>
<style id="jspersistnav">
.boardlist {
	position: fixed;
	left: 0px;
	top: 0px;
	width: calc( 100% - 0.4em);
	padding: 0.2em;
	border-bottom-style: solid;
	border-width: 1px;
}
.toplinks, .adminbar {
	height: auto;
}

.hooklinks {
padding-top: 0px;
}

</style>`);


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
	w_spawnoffx: 0, w_spawnoffy: 0,
	w_spawnoffbouncex: false, w_spawnoffbouncey: false,
	startup: function () {
		$doc.addEventListener("mouseup", kkwm._evdrag_end);
		$doc.addEventListener("mousemove", kkwm._evmove);
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
		if (wintop=$id("wintop")) wintop.id = "";
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
		this.div.style.width = rect.w+"px";
		this.div.style.height = rect.h+"px";
		var fontpointfive = FONTSIZE*3.5;
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
		this.div.innerHTML = '<div class="winbar" data-name="'+name+'" onmousedown="kkwm.drag_start(\''+name+'\',event.clientX,event.clientY);">'+
		'<span class="winname">'+name+'</span><span class="winctrl" onmousedown="event.stopPropagation();">'+
			'<img onclick="$kkwm_name(\''+name+'\').remove();" class="winclose" onmouseover="Tip(\'Close\');" onmouseout="UnTip();" src="'+STATIC_URL+'/image/closebtn.png">'+
		'</span></div>';
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
			winmin.innerHTML = "&plus;";
		} else {
			this.div.classList.remove("minimized");
			winmin.innerHTML = '&mdash;';
		}
	}
	remove() {
		kkwm.windows.pop(this);
		for (var i=0; i<kkwm.windows.length; i++) {
			if (kkwm.windows[i].name == this.name) {
				kkwm.windows.splice(i);
			}
		}
		UnTip();
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
		if (!(new URLSearchParams(window.location.search)).get("q") && document.querySelector("#com")) document.querySelector("#com").value="";
		kkjs.posts = $class("post");
		kkjs.l();
		if (!localStorage.getItem("alwaysnoko"))
			localStorage.setItem("alwaysnoko", "true");
		kkjs.ee();
		kkjs.fup();
		kkwm.startup();
		kkjs.sett_init();
		$doc.postform &&
			$id("rules").insertAdjacentHTML("beforeend",
			'<span id="formfuncs"></span>');
		kkjs.modules.forEach( function(mod) {
			try {
				if (!mod.startup()) {
					console.log("ERROR: Fatal error in module '"+mod.name+"'.");
					kkjs.modules.pop(mod);
				}
			} catch (error) {
				console.log("ERROR: Fatal error in module '"+mod.name+"'");
				kkjs.modules.pop(mod);
			}
		} );
		
		if (localStorage.getItem("tripkeys")=="true") {
			kkjs.applyTripKeys();
		}
		
		//kkjs.wztt();
		$id("jscenterthreads").disabled = localStorage.getItem("centerthreads")!="true";
		if (localStorage.getItem("persistnav")=="true") {
			$id("jspersistnav").disabled = false;
			$doc.body.insertAdjacentHTML("afterbegin", '<br>');
		} else {
			$id("jspersistnav").disabled = true;
		}
		$id("jspersistpager").disabled = localStorage.getItem("persistpager")!="true";
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

		if (kkgal && kkgal.gframe)
			kkgal.contract();
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
		email.insertAdjacentHTML("afterend", '<nobr class="emailjs">'+
			//'<label class="nokosagenoko2"><input type="checkbox" class="nokosagenoko2" onclick="kkjs.ee2(this.id, this.checked);" id="sage"> sage</label>'+
			//'<label class="nokosagenoko2"><input type="checkbox" class="nokosagenoko2" onclick="kkjs.ee2(\'noko2\',false);kkjs.ee2(this.id,this.checked);" id="noko"> noko</label>'+
		'</nobr>');
		if (localStorage.getItem("alwaysnoko")=="true") {
			if (email.value == "") // Only add if blank
				email.value+= 'noko';
			//$id('noko').checked = true;
		}
		//email.addEventListener("input", kkjs.ee3);
	},
	ee2: function (value, mode) {
		if (mode) email.value+= value;
		else email.value = email.value.replace(value, "");
		$id(value).checked = mode;
	},
	ee3: function (event) {
		$id("noko2").checked = this.value.match("noko2");
		$id("noko").checked = this.value.match("noko");
		if ($id("noko2").checked) $id("noko").checked = false;
		$id("sage").checked = this.value.match("sage");
	},
	fup: function () {
		var upf = $id("upfile");
		if (upf) upf.insertAdjacentHTML('afterend',
			'<small>[<a href="javascript:void(0);" onclick="$id(\'upfile\').value=\'\';">X</a>]</small> ');
	},
	
	// form switch
	form_index: 0,
	form_switch: function () {
		const a = Array($id("postarea"), $id("postarea2"));
		if (!(a[0]&&a[1])) return;
 		const pform = $id("postform");
		a[kkjs.form_index=kkjs.form_index?0:1].appendChild(pform);
		pform.scrollIntoView({behavior:"smooth",block:"center"});
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
			x: offs.right - 300,
			y: offs.bottom + 5,
			w: 300,
			h: 400,
		});
		win.div.innerHTML+= '<span id="settabs"><a href="javascript:kkjs.sett_tab(\'general\');" id="settab_general">General</a></span>'+
			'<hr size="1">'+
			'<div id="settarea"></div>';
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
			_ta.style.fontWeight = (_ta.id==("settab_"+tab) ? 'bold' : '');
		}
		var div = $id("settarea");
		div.innerHTML = '';
		if (tab=="general") {
			var _fso = localStorage.getItem("fontsizeoverride");
			if (!_fso) _fso = parseInt(getComputedStyle($doc.body).fontSize);
			div.innerHTML+= `<label><input type="checkbox" onchange="localStorage.setItem('alwaysnoko',this.checked);"`+(localStorage.getItem("alwaysnoko")=="true"?'checked="checked"':'')+`>Always noko</label>
				<label><input type="checkbox" onchange="localStorage.setItem('centerthreads',this.checked);$id('jscenterthreads').disabled=!this.checked;"`+(localStorage.getItem("centerthreads")=="true"?'checked="checked"':'')+`>Center threads</label>
				<label><input type="checkbox" onchange="localStorage.setItem('persistpager',this.checked);$id('jspersistpager').disabled=!this.checked;"`+(localStorage.getItem("persistpager")=="true"?'checked="checked"':'')+`>Persistent pager</label>
				<label><input type="checkbox" onchange="localStorage.setItem('persistnav',this.checked);location.reload();"`+(localStorage.getItem("persistnav")=="true"?'checked="checked"':'')+`>Persistent navigation</label>
				<label><input type="checkbox" onchange="localStorage.setItem('tripkeys', this.checked);location.reload();"`+(localStorage.getItem("tripkeys")=="true"?'checked="checked"':'')+`>Futallaby style tripkeys</label>
				<label><input type="checkbox" onchange="localStorage.setItem('neomenu',this.checked);location.reload();"`+(localStorage.getItem("neomenu")=="true"?'checked="checked"':'')+`>Use Neomenu</label>
			`;
		}
		for (var i=0; i<kkjs.modules.length; i++) {
			var mod = kkjs.modules[i];
			if (typeof(mod.sett)=="function")
				mod.sett(tab, div);
		}
	}
};

window.addEventListener("DOMContentLoaded", kkjs.startup);
