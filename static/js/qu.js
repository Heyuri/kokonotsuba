/* LOL HEYURI
 */

function getSelectTxt() {
	var selectStr = "", selection;
	if (document.selection) { //for IE8
		// selectStr = document.selection.createRange().text;
	} else {
		selection = window.getSelection();
		if (true || window.navigator.userAgent.toLowerCase().match(/trident.*rv:11\./)) {
			if (selection.rangeCount < 1) { return ""; }
			var els = selection.getRangeAt(0).cloneContents().childNodes;
			for (var i = 0; i < els.length; i++) {
				if (els[i].nodeType == 1) {
					selectStr += els[i].outerHTML;
				}
				if (els[i].nodeType == 3) {
					selectStr += els[i].nodeValue;
				}
			}
			selectStr = selectStr.replace(/<br>|<blockquote>/ig, "\n");
			selectStr = selectStr.replace(/<\/?font("[^"]*"|'[^']*'|[^'">])*>/ig, '');
			selectStr = selectStr.replace(/(<("[^"]*"|'[^']*'|[^'">])*>)+/g, ' ');
			selectStr = selectStr.replace(/(^|\n) +/gm, '$1');
			selectStr = selectStr.replace(/ +(\n|$)/gm, "$1");
			selectStr = selectStr.replace(/\n+/gm, '\n');
			selectStr = selectStr.replace(/^\n/gm, '');
		} else {
			selectStr = selection.toString().replace(/^ */gm, '').replace(/^\n\n/gm, '');
		}
	}
	return selectStr;
}

/* Module */
const kkqu = {
	name: "KK Quote",
	startup: function () {
		const com = $id("com");
		if (!com) return true;
		var q = window.location.hash.match(/^#q(\d+)/);
		if (q) {
			console.log(q);
			kkqu.quote(q[1]);
		}
		kkqu.qu = $class("qu");
		for (var i=0; i<kkqu.qu.length; i++) {
			kkqu.qu[i].addEventListener("click", kkqu._evquote);
		}
		com.addEventListener("input", kkqu._evinput);
		$doc.addEventListener("mouseup", kkqu._evselpop);
		kkqu.hlquotes();
		return true;
	},
	reset: function () {
		const com = $id("com");
		kkqu.resetquotes();
		$doc.removeEventListener("mouseup", kkqu._evselpop);
		com.removeEventListener("input", kkqu._evinput);
		if (!kkqu.qu) {
			console.log("ERROR: Reset quote not initialized!");
			return;
		}
		for (var i=0; i<kkqu.qu.length; i++) {
			kkqu.qu[i].removeEventListener("click", kkqu._evquote);
		}
	},
	/* - */
	qu: null,
	hl: Array(),
	/* Events */
	_evquote: function (event) {
		event.preventDefault();
		if (!kkqu.quote(this.textContent)) {
			console.log("ERROR: Quote failed!");
		}
	},
	_evinput: function (event) {
		kkqu.hlquotes();
	},

	/* Settings */
	sett: function (tab, div) {
		if (tab != "general") return;
		div.innerHTML += '<label><input type="checkbox" onchange="localStorage.setItem(\'quotetooltip\',this.checked);document.body.classList.toggle(\'quotetooltip-enabled\', this.checked);" ' + (localStorage.getItem("quotetooltip") == "true" ? 'checked="checked"' : '') + '>Quote tooltip</label>';
	},
	/* Function */
	quote: function (no) {
		kkjs.com_insert(">>"+no+"\n");
		return no;
	},
	resetquotes: function () {
		for (var i=0; i<kkqu.hl.length; i++) {
			kkqu.hl[i].classList.remove("replyhl");
		}
		kkqu.hl = Array();
	},
	hlquotes: function () {
		const com = $id("com");
		var m=com.value.match(/((?:>)+)(?:No\.)?(\d+)/ig);
		if (!m) return;
		kkqu.resetquotes();
		for (var i=0; i<m.length; i++) {
			var m2 = m[i].match(/((?:>)+)(?:No\.)?(\d+)/i);
			var p = $id("p"+m2[2]);
			if (!p || p.classList.contains("op")) continue;
			p.classList.add("replyhl");
			kkqu.hl.push(p);
		}
	},
	/* Select Quote */
	_evselpop: function (event) {
		setTimeout(function(){
			const selpop = $id("slp");
			if (selpop) $del(selpop);
			kkqu.selopen(event.pageX, event.pageY);
		}, 50);
	},
	selopen: function (x, y) {
		if (localStorage.getItem("quotetooltip") == "false") return true;
		var txt = getSelectTxt();
		if (!txt) return;
		var selpop = $doc.createElement("div");
		selpop.id = "slp";
		if(window.screen.width<=799){
			selpop.style.left = x+"px";
			selpop.style.top = (y-15)+"px";
		} else {
			selpop.style.left = x+"px";
			selpop.style.top = (y-25)+"px";
		}
		selpop.innerHTML = '<a class="linkjs" href="javascript:kkqu.selwrite();">Quote</a>'+
			'<div id="slptmp">'+txt+'</div>';
		$doc.body.appendChild(selpop);
	},
	selwrite: function () {
		var selpop = $id("slp");
		if (!selpop) return;
		var txt = $id("slptmp").innerText.replace(/[\r\n]+/g, "\n").trim().replace(/\n/g, "\n>").replace(/\t/g, "");
		$del(selpop);
		kkjs.com_insert(">"+txt+"\n");
	},
};

/* Register */
if(typeof(KOKOJS)!="undefined"){kkjs.modules.push(kkqu);}else{console.log("ERROR: KOKOJS not loaded!\nPlease load 'koko.js' before this script.");}
