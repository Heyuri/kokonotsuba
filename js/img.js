/* LOL HEYURI
 */

document.write(`<style>
#hoverimg {
	position: fixed;
	top: 0;
	right: 0;
	max-height: calc( 100% - 2px );
	max-width: calc( 100% - 2px );
	z-index: 495;
	pointer-events: none;
	display: none;
	background-color: inherit;
}
/* Gallery */
#galframe {
	position: fixed;
	top: 0;
	left: 0;
	width: 100%;
	height: 100%;
	z-index: 490;
	background-color: #000A;
	color: #FFF;
	display: none;
	flex-direction: row;
}
#galmain {
	width: calc(100% - 150px - 1em);
}
#galimg {
	max-width: 100%;
	max-height: 100%;
}
#galctrl {
	background-color: #111E;
	height: 2em;
}
#galctrl .filesize {
	margin-top: 0.25em;
	display: block;
}
#galctrl * {
	color: #FFF!important;
	text-decoration: none;
}
#galctrl a, #galctrl a:hover {
	font-weight: bold;
}
#galctrl a:hover {
	text-decoration: underline;
}
#galctrl2 {
	float: right;
	font-family: Tahoma, Verdana;
	font-size: larger;
}
#galside {
	border-style: inset;
	background-color: #111;
	padding: 0 5px;
	overflow-x: hidden;
	overflow-y: scroll;
	max-width: 150px;
}
#galside img {
	border-style: solid;
	border-width: 1px;
	border-color: #008;
	opacity: 0.8;
}
#galside img:hover {
	border-color: #00F;
}
#galside img.activethumb {
	border-color: #FFF;
	opacity: 1;
}
#galimgcontainer {
	position: relative;
}
#galimgprev {
	position: absolute;
	left: 0;
	top: 0;
	width: 50%;
	height: 100%;
}
#galimgnext {
	position: absolute;
	left: 50%;
	top: 0;
	width: 50%;
	height: 100%;
}
</style>`);

/* Gallery (submodule) */
const kkgal = {
	startup: function () {
		var df = $id("delform");
		if (!df) return;
		if (document.querySelector("#galfuncs")) return;
		df.insertAdjacentHTML("beforebegin", '<div align="RIGHT" id="galfuncs"><label><input type="checkbox" onchange="localStorage.setItem(\'galmode\',this.checked);"'+( localStorage.getItem("galmode")=="true" ? ' checked="checked"' : '')+' />Gallery mode</label></div>');
		$doc.body.insertAdjacentHTML("beforeend", `
<div id="galframe">
	<table id="galmain" cellspacing="0" cellpadding="5" height="100%"><tbody>
		<tr><td align="CENTER" valign="CENTER" id="galimgcontainer">
			<a href="" id="galimgprev"></a>
			<a href="" id="galimgnext"></a>
			<img src="" alt="Gallery Image" id="galimg" />
		</td></tr>
		<tr><td id="galctrl" valign="CENTER">
			<nobr id="galctrl2"><a href="javascript:kkgal.contract();" title="close">&times;</a></nobr>
		</td></tr>
	</tbody></table>
	<div id="galside">
	</div>
</div>
		`);
		kkgal.gframe = $id("galframe");
		kkgal.gimg = $id("galimg");
		kkgal.gctrl = $id("galctrl");
		var side = $id("galside");
		for (var i=0; i<kkimg.postimg.length; i++) {
			var a = kkimg.postimg[i].parentNode;
			var pno = a.parentNode.id.substr(1);

			side.innerHTML+= '<a href="javascript:kkgal.expand(\''+pno+'\');"><img id="galthumb'+pno+'" class="" src="'+kkimg.postimg[i].src+'" alt="" border="1" width="100%" /></a>';
			kkgal.imgindex[i] = pno;
		}
		kkgal.getfit();
		window.addEventListener("resize", kkgal._evresize);
	},
	reset: function () {
		var df = $id("delform");
		if (!df) return;
		kkgal.contract();
		$del($id("galfuncs"));
		$del(kkgal.gframe);
		window.removeEventListener("resize", kkgal._evresize);
	},
	/* - */
	gframe: null,
	gimg: null,
	gctrl: null,
	imgindex: Array(),
	/* Event */
	_evresize: function (event) {
		kkgal.getfit();
	},
	_evkeypress: function (event) {
		switch (event.key) {
			case "ArrowLeft": case "ArrowUp": case "PageUp": // normal person keys (:^|)
			case "h": case "j": // autist keys (hjkl)
			case "<": case "[": // chad keys (*pounds keyboard*)
			case "w": case "a": // gamer keys (wasd)
				$id("galimgprev").click();
				event.preventDefault();
				break;
			case "ArrowRight": case "ArrowDown": case "PageDown":
			case "l": case "k":
			case ">": case "]":
			case "s": case "d":
				$id("galimgnext").click();
				event.preventDefault();
				break;
			case "Escape":
			case "q":
			case "x":
				kkgal.contract();
				event.preventDefault();
				break;
			case "Home":
			case "^":
				kkgal.expand(kkgal.imgindex[0]);
				event.preventDefault();
				break;
			case "End":
			case "$":
				kkgal.expand(kkgal.imgindex[kkgal.imgindex.length-1]);
				event.preventDefault();
				break;
			default:
				if (event.key.match(/^\d$/i)) {
					var i = parseInt(event.key)-1;
					if (typeof(kkgal.imgindex[i])!="undefined")
						kkgal.expand(kkgal.imgindex[i]);
					event.preventDefault();
				}
				break;
		}
	},
	/* Function */
	getfit: function () {
		var d = $doc.documentElement;
		kkgal.gimg.style.maxWidth = (d.clientWidth-200)+"px";
		kkgal.gimg.style.maxHeight = (d.clientHeight-FONTSIZE*2.5)+"px";
	},
	expand: function (no=0) {
		$doc.addEventListener("keypress", kkgal._evkeypress);
		var _a = $class("activethumb");
		if (_a.length) _a[0].classList.remove("activethumb");
		if (!no) no = ($class("postimg")[0].parentNode).parentNode.id.substr(1);
		for (var i=0; i<kkimg.postimg.length; i++) {
			var a = kkimg.postimg[i].parentNode;
			var pno = a.parentNode.id.substr(1);

			if (pno == no) {
				var thumb = $id("galthumb"+no);
				thumb.classList.add("activethumb");
				thumb.scrollIntoView({behavior:"smooth",block:"center"});
				var fs = $q("#p"+pno+" .filesize")[0].cloneNode(true);
				var prev = typeof(kkgal.imgindex[i-1])!="undefined" ? kkgal.imgindex[i-1] : kkgal.imgindex[kkgal.imgindex.length-1];
				var next = typeof(kkgal.imgindex[i+1])!="undefined" ? kkgal.imgindex[i+1] : kkgal.imgindex[0];
				kkgal.gimg.src = a.href;
				$id("galimgprev").href = "javascript:kkgal.expand('"+prev+"');";
				$id("galimgnext").href = "javascript:kkgal.expand('"+next+"');";
				kkgal.gctrl.innerHTML = '<nobr id="galctrl2"><a href="javascript:kkgal.expand(\''+prev+'\');" title="Previous">&#9664;</a> <a href="javascript:kkgal.expand(\''+next+'\');" title="Next">&#9654;</a> <a href="javascript:kkgal.contract();" title="Close">&times;</a></nobr>';
				kkgal.gctrl.appendChild(fs);
			}
		}
		kkgal.gframe.style.display = "flex";
		$doc.body.style.overflow = "hidden";
		$id("hoverimg").style.display = "none";
	},
	contract: function (no=0) {
		$doc.removeEventListener("keypress", kkgal._evkeypress);
		kkgal.gframe.style.display = "none";
		$doc.body.style.overflow = "";
	},
};

/* Module */
const kkimg = { name: "KK Image Features",
	startup: function () {
		if (!localStorage.getItem("imgexpand"))
			localStorage.setItem("imgexpand", "true");
		kkimg.postimg = $class("postimg");
		for (var i=0; i<kkimg.postimg.length; i++) {
			var a = kkimg.postimg[i].parentNode;
			a.addEventListener("click", kkimg._evexpand);
			a.addEventListener("mouseover", kkimg._evhover1);
			a.addEventListener("mouseout", kkimg._evhover2);
		}
		$doc.body.insertAdjacentHTML('beforeend', '<img id="hoverimg" src="" alt="Full Image" onerror="this.style.display=\'\';" onload="this.style.display=\'inline-block\';" border="1" />');
		kkgal.startup();
		return true;
	},
	reset: function () {
		if (!kkimg.postimg) {
			console.log("ERROR: Reset expand not initialized!");
			return;
		}
		kkgal.reset();
		for (var i=0; i<kkimg.postimg.length; i++) {
			var a = kkimg.postimg[i].parentNode;
			var no = a.parentNode.id.substr(1);
			kkimg.contract(no);
			a.removeEventListener("click", kkimg._evexpand);
			a.removeEventListener("mouseover", kkimg._evhover1);
			a.removeEventListener("mouseout", kkimg._evhover2);
		}
		$del($id("hoverimg"));
	},
	sett: function(tab, div) { if (tab!="general") return;
		div.innerHTML+= `
			<label><input type="checkbox" onchange="localStorage.setItem('imgexpand',this.checked);kkimg.reset();kkimg.startup();"`+(localStorage.getItem("imgexpand")=="true"?'checked="checked"':'')+` />Inline image expansion</label>
			<label><input type="checkbox" onchange="localStorage.setItem('imghover',this.checked);$id('hoverimg').src='';"`+(localStorage.getItem("imghover")=="true"?'checked="checked"':'')+` />Image hover</label>
			<label><input type="checkbox" onchange="localStorage.setItem('galmode',this.checked);kkgal.reset();kkgal.startup();"`+(localStorage.getItem("galmode")=="true"?'checked="checked"':'')+` />Gallery mode</label>`;
	},
	/* - */
	postimg: null,
	imgext: Array("png","jpg","jpeg","gif","giff","bmp","jfif"),
	vidext: Array("webm","mp4"),
	swfext: Array("swf"),
	/* event */
	_evexpand: function (event) {
		if (localStorage.getItem("imgexpand")!="true") return;
		var p = this.parentNode;
		var no = p.id.substr(1);
		if (localStorage.getItem("galmode")=="true") {
			kkgal.expand(no);
			event.preventDefault();
			return;
		}
		if ( kkimg.expand(no) ) event.preventDefault();
	},
	_evhover1: function (event) {
		if (localStorage.getItem("imghover")!="true") return;
		$id("hoverimg").src = this.href;
	},
	_evhover2: function (event) {
		var hi = $id("hoverimg");
		hi.style.display = "";
		hi.src = "";
	},
	/* function */
	expand: function (no) {
		var p = $id("p"+no);
		var thumb = p.getElementsByClassName("postimg")[0]; if (!thumb) return;
		var a = thumb.parentNode; if (!a || a.tagName != "A") return;
		var ext; _split = a.href.split("."); ext = _split[_split.length-1];

		a.style.display = "none"
		if (kkimg.imgext.includes(ext)) {
			a.insertAdjacentHTML("afterend", '<div class="expand">'+
			'<a href="'+a.href+'" onclick="event.preventDefault();kkimg.contract(\''+no+'\');">'+
				'<img src="'+a.href+'" alt="Full image" onerror="kkimg.error(\''+no+'\');" class="expandimg" title="Click to contract" border="0" />'+
			'</a></div>');
			return true;
		} else if (kkimg.vidext.includes(ext)) {
			a.insertAdjacentHTML("afterend", '<div class="expand">'+
				'<div>[<a href="javascript:kkimg.contract(\''+no+'\');">Close</a>]</div>'+
				'<video controls="controls" loop="loop" autoplay="autoplay" src="'+a.href+'"></video>'+
			'</div>');
			return true;
		} else if (kkimg.swfext.includes(ext)) {
			a.insertAdjacentHTML("afterend", '<div class="expand">'+
				'<div>[<a href="javascript:kkimg.contract(\''+no+'\');">Close</a>]</div>'+
				'<object width="640" height="480">'+
				'<param name="movie" value="'+a.href+'">'+
				'<embed src="'+a.href+'" width="640" height="480">'+
				'</embed></object>'+
			'</div>');
			return true;
		}
		return false;
	},
	contract: function (no) {
		var p = $id("p"+no);
		var exp = p.getElementsByClassName("expand")[0];
		if (!exp) return;
		exp.remove();

		var a = p.getElementsByClassName("postimg")[0].parentNode;
		a.style.display = "";
	},
	error: function (no) {
		var p = $id("p"+no);
		var exp = p.getElementsByClassName("expand")[0];
		exp.innerHTML = '<span class="error">Error loading file!</span> [<a href="javascript:kkimg.contract(\''+no+'\');">Close</a>]';
	}
};

/* Register */
if(typeof(KOKOJS)!="undefined"){kkjs.modules.push(kkimg);}else{console.log("ERROR: KOKOJS not loaded!\nPlease load 'koko.js' before this script.");}
