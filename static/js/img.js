/* LOL HEYURI
 */

/* Gallery (submodule) */
const kkgal = {
	startup: function () {
		var df = $id("delform");
		if (!df) return;
		if (document.querySelector("#galfuncs")) return;
		//df.insertAdjacentHTML("beforebegin", '<div id="galfuncs"><label><input type="checkbox" onchange="localStorage.setItem(\'galmode\',this.checked);"'+( localStorage.getItem("galmode")=="true" ? ' checked="checked"' : '')+'>Gallery mode</label></div>'); // disable "Gallery mode" checkbox in thread
		$doc.body.insertAdjacentHTML("beforeend", `
<div id="galframe">
	<table id="galmain" cellspacing="0" cellpadding="5" height="100%"><tbody>
		<tr><td id="galimgcontainer" style="text-align: center">
			<a href="" id="galimgprev"></a>
			<a href="" id="galimgnext"></a>
			<img src="" alt="Gallery Image" id="galimg">
		</td></tr>
		<tr><td id="galctrl">
			<div id="galctrl2"><a href="javascript:kkgal.contract();" title="close">&times;</a></div>
		</td></tr>
	</tbody></table>
	<div id="galside">
	</div>
</div>
		`);
		kkgal.gframe = $id("galframe");
		kkgal.gimg   = $id("galimg");
		kkgal.gctrl  = $id("galctrl");
		var side = $id("galside");
		var sideInnerHTML = "";
		for (var i = 0; i < kkimg.postimg.length; i++) {
			var a   = kkimg.postimg[i].parentNode;
			var pno = a.parentNode.id.substr(1);

			sideInnerHTML += '<a href="javascript:kkgal.expand(\''+pno+'\');"><img id="galthumb'+pno+'" class="" src="'+kkimg.postimg[i].src+'" alt="'+kkimg.postimg[i].src+'"></a>';
			kkgal.imgindex[i] = pno;
		}
		side.insertAdjacentHTML("beforeend", sideInnerHTML);
		kkgal.getfit();
		window.addEventListener("resize", kkgal._evresize);
	},
	reset: function () {
		var df = $id("delform");
		if (!df) return;
		kkgal.contract();
		var galfuncs = $id("galfuncs");
		if (galfuncs) $del(galfuncs);
		if (kkgal.gframe) $del(kkgal.gframe);
		window.removeEventListener("resize", kkgal._evresize);
	},
	/* - */
	gframe:	null,
	gimg:	null,
	gctrl:	null,
	imgindex: Array(),
	/* Event */
	_evresize: function (event) {
		kkgal.getfit();
	},
	_evkeydown: function (event) {
		switch (event.key) {
			case "ArrowLeft": case "ArrowUp": case "PageUp": // normal person keys (:^|)
			case "h": case "k": // autist keys (hjkl)
			case "<": case "[": // chad keys (*pounds keyboard*)
			case "w": case "a": // gamer keys (wasd)
				$id("galimgprev").click();
				event.preventDefault();
				break;
			case "ArrowRight": case "ArrowDown": case "PageDown":
			case "l": case "j":
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
					var i = parseInt(event.key) - 1;
					if (typeof(kkgal.imgindex[i]) != "undefined")
						kkgal.expand(kkgal.imgindex[i]);
					event.preventDefault();
				}
				break;
		}
	},
	/* Function */
	getfit: function () {
		var d = $doc.documentElement;
		kkgal.gimg.style.maxWidth  = (d.clientWidth  - 300) + "px";
		kkgal.gimg.style.maxHeight = (d.clientHeight - FONTSIZE*2.5) + "px";
	},
	expand: function (no = 0) {
		$doc.addEventListener("keydown", kkgal._evkeydown);
		var _a = $class("activethumb");
		if (_a.length) _a[0].classList.remove("activethumb");
		if (!no) no = ($class("postimg")[0].parentNode).parentNode.id.substr(1);
		for (var i = 0; i < kkimg.postimg.length; i++) {
			var a   = kkimg.postimg[i].parentNode;
			var pno = a.parentNode.id.substr(1);

			if (pno == no) {
				var thumb = $id("galthumb" + no);
				thumb.classList.add("activethumb");
				thumb.scrollIntoView({ behavior: "smooth", block: "center" });
				var fs   = $q("#p" + pno + " .filesize")[0].cloneNode(true);
				var prev = typeof(kkgal.imgindex[i-1]) != "undefined"
				         ? kkgal.imgindex[i-1]
				         : kkgal.imgindex[kkgal.imgindex.length-1];
				var next = typeof(kkgal.imgindex[i+1]) != "undefined"
				         ? kkgal.imgindex[i+1]
				         : kkgal.imgindex[0];
				kkgal.gimg.src               = a.href;
				$id("galimgprev").href       = "javascript:kkgal.expand('" + prev + "');";
				$id("galimgnext").href       = "javascript:kkgal.expand('" + next + "');";
				kkgal.gctrl.innerHTML        =
					'<div id="galctrl2"><a href="javascript:kkgal.expand(\''+prev+'\');" title="Previous">&#9664;</a> '
				  + '<a href="javascript:kkgal.expand(\''+next+'\');" title="Next">&#9654;</a> '
				  + '<a href="javascript:kkgal.contract();" title="Close">&times;</a></div>';
				kkgal.gctrl.appendChild(fs);
			}
		}
		kkgal.gframe.style.display    = "flex";
		$doc.body.style.overflow      = "hidden";
		$id("hoverimg").style.display = "none";
	},
	contract: function (no = 0) {
		$doc.removeEventListener("keydown", kkgal._evkeydown);
		kkgal.gframe.style.display = "none";
		$doc.body.style.overflow   = "";
	},
};

/* Module */
const kkimg = { name: "KK Image Features",
	startup: function () {
		if (!localStorage.getItem("imgexpand"))
			localStorage.setItem("imgexpand", "true");
		kkimg.postimg = $class("postimg");
		for (var i = 0; i < kkimg.postimg.length; i++) {
			var a = kkimg.postimg[i].parentNode;
			a.addEventListener("click",    kkimg._evexpand);
			a.addEventListener("mouseover",kkimg._evhover1);
			a.addEventListener("mouseout", kkimg._evhover2);
		}
		$doc.body.insertAdjacentHTML("beforeend",
			'<img id="hoverimg" src="" alt="Full Image" '
			+ 'onerror="this.style.display=\'\';" '
			+ 'onload="this.style.display=\'inline-block\';" border="1">'
		);
		kkgal.startup();
		return true;
	},
	reset: function () {
		if (!kkimg.postimg) {
			console.log("ERROR: Reset expand not initialized!");
			return;
		}
		kkgal.reset();
		for (var i = 0; i < kkimg.postimg.length; i++) {
			var a  = kkimg.postimg[i].parentNode;
			var no = a.parentNode.id.substr(1);
			kkimg.contract(no);
			a.removeEventListener("click",    kkimg._evexpand);
			a.removeEventListener("mouseover",kkimg._evhover1);
			a.removeEventListener("mouseout", kkimg._evhover2);
		}
		$del($id("hoverimg"));
	},
	sett: function(tab, div) { if (tab!="general") return;
		div.innerHTML+= `
			<label><input type="checkbox" onchange="localStorage.setItem('imgexpand',this.checked);kkimg.reset();kkimg.startup();"`+(localStorage.getItem("imgexpand")=="true"?' checked="checked"':'')+`>Inline image expansion</label>
			<label><input type="checkbox" onchange="localStorage.setItem('imghover',this.checked);$id('hoverimg').src='';"`+(localStorage.getItem("imghover")=="true"?' checked="checked"':'')+`>Image hover</label>
			<label><input type="checkbox" onchange="localStorage.setItem('galmode',this.checked);kkgal.reset();kkgal.startup();"`+(localStorage.getItem("galmode")=="true"?' checked="checked"':'')+`>Gallery mode</label>`;
	},
	/* - */
	postimg: null,
	imgext: Array("png","jpg","jpeg","gif","giff","bmp","jfif"),
	vidext: Array("webm","mp4"),
	swfext: Array("swf"),
	/* event */
	_evexpand: function (event) {
		var p  = this.parentNode;
		var no = p.id.substr(1);
		if (localStorage.getItem("galmode")=="true") {
			kkgal.expand(no);
			event.preventDefault();
			return;
		}
		if (localStorage.getItem("imgexpand")!="true") return;
		if (kkimg.expand(no)) event.preventDefault();
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
		var p    = $id("p"+no),
			thumb= p.getElementsByClassName("postimg")[0];
		if (!thumb) return;
		var a    = thumb.parentNode;
		if (!a||a.tagName!=="A") return;
		var ext  = a.href.split(".").pop().toLowerCase();

		a.style.display = "none";
		if (p.getBoundingClientRect().top < 0) p.scrollIntoView();

		if (kkimg.imgext.includes(ext)) {
			a.insertAdjacentHTML("afterend", '<div class="expand">'+
				'<a href="'+a.href+'" onclick="event.preventDefault();kkimg.contract(\''+no+'\');">'+
				'<img src="'+a.href+'" alt="Full image" onerror="kkimg.error(\''+no+'\');" class="expandimg" title="Click to contract" border="0">'+
				'</a></div>');
			return true;
		} else if (kkimg.vidext.includes(ext)) {
			a.insertAdjacentHTML("afterend", '<div class="expand">'+
				'<div>[<a href="javascript:kkimg.contract(\''+no+'\');">Close</a>]</div>'+
				'<video class="expandimg" controls="controls" loop="loop" autoplay="autoplay" src="'+a.href+'"></video>'+
				'</div>');
			return true;
		} else if (kkimg.swfext.includes(ext)) {
			// pull native dims from the existing filesize text (leave it visible)
			var fsNode = $q("#p"+no+" .filesize")[0],
			    m      = fsNode.textContent.match(/(\d+)\s*x\s*(\d+)/i),
			    realW  = m ? parseInt(m[1],10) : 550,
			    realH  = m ? parseInt(m[2],10) : 400;

			// cap at 95%
			var vw    = document.documentElement.clientWidth,
			    vh    = document.documentElement.clientHeight,
			    maxW  = vw * 0.95,
			    maxH  = vh * 0.95,
			    ratio = realW / realH,
			    dispW = Math.min(realW, maxW),
			    dispH = dispW / ratio;

			if (dispH > maxH) {
				dispH = maxH;
				dispW = dispH * ratio;
			}

			// build container
			var hdr       = 20,
			    container = document.createElement("div"),
			    header    = document.createElement("div");

			container.className      = "expand swf-expand";
			container.style.width    = dispW + "px";
			container.style.height   = (dispH + hdr) + "px";
			container.style.resize   = "both";
			container.style.overflow = "hidden";
			// ensure children absolute‐fill works
			container.style.position = "relative";

			// close header
			header.className    = "swf-expand-header";
			header.style.height = hdr + "px";
			header.innerHTML    = '[<a href="javascript:kkimg.contract(\''+no+'\');">Close</a>]';
			container.appendChild(header);

			// insert & hide original link
			a.insertAdjacentElement("afterend", container);

			// build a host that fills the container and holds either native Flash or Ruffle
			// using CSS absolute fill ensures resizing always works
			var host      = document.createElement("div"),
			    useNative = !!(navigator.mimeTypes['application/x-shockwave-flash'] ||
			                   navigator.plugins['Shockwave Flash']);

			host.className      = "ruffleContainer";
			host.style.position = "absolute";
			host.style.top      = hdr + "px";
			host.style.left     = "0";
			host.style.right    = "0";
			host.style.bottom   = "0";
			container.appendChild(host);

			if (useNative) {
				// native Flash via <object>, fill host
				var obj = document.createElement("object");
				obj.data   = a.href;
				obj.type   = "application/x-shockwave-flash";
				obj.style.width  = "100%";
				obj.style.height = "100%";
				host.appendChild(obj);
			} else {
				// ruffle emulator, fill host
				var ruffle = window.RufflePlayer.newest(),
				    player = ruffle.createPlayer();
				player.style.width  = "100%";
				player.style.height = "100%";
				host.appendChild(player);
				player.load(a.href);
			}

			return true;
		}
		return false;
	},
	contract: function (no) {
		var p   = $id("p"+no),
			exp = p.getElementsByClassName("expand")[0];
		if (!exp) return;
		var scroll = p.getBoundingClientRect().top < 0;
		var rp = exp.querySelector(".ruffleContainer>*");
		if (rp && rp._resizeObserver) rp._resizeObserver.disconnect();
		exp.remove();
		var a = p.getElementsByClassName("postimg")[0].parentNode;
		a.style.display = "";
		if (scroll) p.scrollIntoView();
	},
	error: function (no) {
		var p   = $id("p"+no),
			exp = p.getElementsByClassName("expand")[0];
		exp.innerHTML = '<span class="error">Error loading file!</span> [<a href="javascript:kkimg.contract(\''+no+'\');">Close</a>]';
	}
};

/* Register */
if (typeof(KOKOJS)!="undefined") {
	kkjs.modules.push(kkimg);
} else {
	console.log("ERROR: KOKOJS not loaded!\nPlease load 'koko.js' before this script.");
}
