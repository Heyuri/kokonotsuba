/* LOL HEYURI
 */

document.write(`<style>
.oplink:after {
	content: " (OP)";
	font-size: small;
}
</style>`);

/* Module */
const kkqu2 = { name: "KK Quotelink Marking",
	startup: function () {
		if (!localStorage.getItem("markopqu")) localStorage.setItem("markopqu", "true");
		var qu = $class("quotelink");
		for(var i=0; i<qu.length; i++) {
			var opno = $p_class(qu[i], "thread");
			if (opno) opno = opno.id;
			if (opno) opno = opno.substr(1);
			if (localStorage.getItem("markopqu")=="true") {
				if (qu[i].href.split("#p")[1]==opno)
					qu[i].classList.add("oplink");
			}
		}
		
		return true;
	},
	reset: function () {
		var qu = $class("quotelink");
		for(var i=0; i<qu.length; i++) {
			qu[i].classList.remove("oplink");
		}
	},
	sett: function (tab, div) { if (tab!="general") return;
		div.innerHTML+= `
			<label><input type="checkbox" onchange="localStorage.setItem('markopqu',this.checked);kkqu2.reset();kkqu2.startup();"`+(localStorage.getItem("markopqu")=="true"?'checked="checked"':'')+` />Mark OP quotes</label>
			`;
	}
};

/* Register */
if(typeof(KOKOJS)!="undefined"){kkjs.modules.push(kkqu2);}else{console.log("ERROR: KOKOJS not loaded!\nPlease load 'koko.js' before this script.");}
