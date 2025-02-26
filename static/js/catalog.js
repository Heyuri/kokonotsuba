/* Module */
const kkcat = { name: "KK Catalog Functions",
	startup: function () {
		kkcat.cat = $id("catalog");
		if (!kkcat.cat) return true;
		if (!kkjs.get_cookie("sett_sscase")) kkjs.set_cookie("sett_sscase", "true");
		$id("catalogSortForm").insertAdjacentHTML("beforebegin", `
<div id="catsett">
	[<label><input type="checkbox" id="sett_fw"`+$mkcheck($bool(kkjs.get_cookie("cat_fw")))+`>Full width</label>]
	<label title="0 for auto">Columns:<input type="number" id="sett_cols" class="inputtext" value="`+kkjs.get_cookie("cat_cols")+`" min="0" max="20"></label><button onclick="kkcat.sett_save();">Apply</button><br>
	[<label><input type="checkbox" id="sett_sscase"`+$mkcheck($bool(kkjs.get_cookie("sett_sscase")))+` onclick="kkjs.set_cookie('sett_sscase', this.checked);kkcat.search();">Case insensitive</label>]
	<input type="search" id="sett_ss" class="inputtext" placeholder="Search" value="`+location.hash.substr(1)+`" oninput="kkcat.search(this.value);">
</div>
`);
		kkcat.search();
		return true;
	},
	reset: function () {
		if (!kkcat.cat) return;
		$del($id("catsett"));
	},
	/* - */
	cat: null,
	/* functions */
	sett_save: function() {
		var input_fw = $id("sett_fw");
		var input = $id("sett_cols");
		if (!input || !input_fw) return;
		kkjs.set_cookie("cat_cols", parseInt(input.value));
		kkjs.set_cookie("cat_fw", input_fw.checked);
		location.reload();
	},
	search: function(str='') {
		if (!str) str = $id("sett_ss").value;
		var uncase = $bool(kkjs.get_cookie("sett_sscase"));
		if (uncase) str = str.toLowerCase();
		var thread = $q("#catalog .thread");
		for (var i=0; i<thread.length; i++) {
			var text = thread[i].innerText;
			if (uncase) text = text.toLowerCase();
			thread[i].style.display = text.includes(str) ? "" : "none";
		}
		$id("jscat").disabled = str ? false : true;
		location.hash = str;
	},
};

/* Register */
if(typeof(KOKOJS)!="undefined"){kkjs.modules.push(kkcat);}else{console.log("ERROR: KOKOJS not loaded!\nPlease load 'koko.js' before this script.");}
