/* LOL HEYURI
 */

/* Module */
const kkqu2 = { name: "KK Quotelink Marking",
	startup: function () {
		var qu = $class("quotelink");
		for(var i=0; i<qu.length; i++) {
			var opno = $p_class(qu[i], "thread");
			if (opno) opno = opno.id;
			if (opno) opno = opno.substr(1);
			if (_kkSetting("markopqu")) {
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
};

/* Register */
if(typeof(KOKOJS)!="undefined"){
	kkjs.modules.push(kkqu2);
	kkSetting.add({ key: "markopqu", label: "Mark OP quotes", onChange: function () {
		kkqu2.reset();
		kkqu2.startup();
	} }, "Quotes & Replies");
}else{console.log("ERROR: KOKOJS not loaded!\nPlease load 'koko.js' before this script.");}
