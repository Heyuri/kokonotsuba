/* Gallery (submodule) */
const kkgal = {
	name: "KK Gallery",
	
	startup: function () {
		//get post images
		kkgal.postImages = $class("postimg");

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
		for (var i = 0; i < kkgal.postImages.length; i++) {
			var a   = kkgal.postImages[i].parentNode;

			var postEl = a.closest('.post[id^="p"]');
			if (!postEl) continue;
			var pno = postEl.id.substr(1);

			sideInnerHTML += '<a href="javascript:kkgal.expand(\''+pno+'\');"><img id="galthumb'+pno+'" class="" src="'+kkgal.postImages[i].src+'" alt="'+kkgal.postImages[i].src+'"></a>';
			kkgal.imgindex[i] = pno;
		}
		side.insertAdjacentHTML("beforeend", sideInnerHTML);
		kkgal.getfit();
		window.addEventListener("resize", kkgal._evresize);

		return true;
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
	sett: function(tab, div) {
		if (tab!="general") return;
		div.innerHTML+= `<label><input type="checkbox" onchange="localStorage.setItem('galmode',this.checked);kkgal.reset();kkgal.startup();"`+(localStorage.getItem("galmode")=="true"?' checked="checked"':'')+`>Gallery mode</label>`;
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
		
		if (!no) {
		   var firstImg = $class("postimg")[0];
		   if (firstImg) {
			   var postEl0 = firstImg.closest('.post[id^="p"]');
			   if (postEl0) no = postEl0.id.substr(1);
		   }
		}

		for (var i = 0; i < kkgal.postImages.length; i++) {
			var a   = kkgal.postImages[i].parentNode;
			
			var postEl = a.closest('.post[id^="p"]');
			if (!postEl) continue;
			var pno = postEl.id.substr(1);

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

if (typeof(KOKOJS)!="undefined") {
	kkjs.modules.push(kkgal);
} else {
	console.log("ERROR: KOKOJS not loaded!\nPlease load 'koko.js' before this script.");
}