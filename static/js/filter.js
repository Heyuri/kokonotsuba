/* LOL HEYURI
 */

document.write(`<style>
#settuserfilter {
    height: 300px;
}
.filter .comment, .filter .post, .filter .omittedposts, .filter .filesize, .filter .postimg, .filter>table {
    display: none;
}
.filter .post.op {
    display: block;
}
.filter .postinfo, .filter .category {
    opacity: 0.5;
}
.filterpost {
    text-decoration: none;
    font-weight: bold;
	font-size: 9pt;
}
.filterpost:hover {
    opacity: 1;
}
</style>`);

/* Module */
const kkfilter = { name: "KK Filter",
	F: Array(),
	die: '',
    startup: function () {
		kkfilter.F.forEach(function(F){
			F.exec();
			if (F.storagename=="filter_postnum") {
				for (var post of $class("post")) {
					var pi = post.getElementsByClassName("postinfo")[0];
					if (typeof(pi) === "undefined") continue;
					pi.insertAdjacentHTML("beforeend", '<a class="filterpost" href="javascript:void(0);" onclick="kkfilter.togglepostno('+post.id.substr(1)+');">'+
						(post.classList.contains("filter")?'[F]':'[F]')+
					'</a>');
				}
			}
		});
		return true;
    },
    reset: function () {
		var f = $class("filter");
		for (var i=f.length; i; i--) {
			f[i-1].classList.remove("filter");
		}
		var fp = $class("filterpost");
		for (var i=fp.length; i; i--) {
			fp[i-1].remove();
		}
		kkfilter.die = '';
    },
    sett_tab: function (id) {
        $id(id).innerHTML+= ' | <a href="javascript:kkjs.sett_tab(\'filter\');" id="settab_filter">Filter</a>';
    },
    sett: function (tab, div) { if (tab!="filter") return;
		div.innerHTML+= `<textarea cols="48" rows="6" id="settuserfilter" oninput="kkfilter.update_filter(this.value);" placeholder="Regex filters">`+_usercss+`</textarea>
<select id="filtermode" onchange="kkfilter.update_textarea(this.value);"></select><div id="filterdie"></div>`;
		var sel = $id("filtermode");
		kkfilter.F.forEach(function(F){
			var opt = $doc.createElement("OPTION");
			opt.innerText = F.name;
			opt.value = F.storagename;
			sel.add(opt);
		});
		kkfilter.update_textarea(sel.value);
		$id("filterdie").innerHTML = kkfilter.die;
    },
	update_filter: function (value) {
		var FM = $id("filtermode").value;
		localStorage.setItem(FM, value);
		kkfilter.reset();
		kkfilter.startup();
		var filterdie = $id("filterdie");
		if (filterdie) filterdie.innerHTML = kkfilter.die;
	},
	update_textarea: function (value) {
		$id("settuserfilter").value = localStorage.getItem(value);
	},
	togglepostno: function (no) {
		var p = $id("p"+no);
		var a = localStorage.getItem("filter_postnum"); if (a===null) a = '';
		var b = a.split("\n");
		var hack = true;
		if (p.classList.contains("filter")) {
			for (var i=0; i<b.length; i++) {
				var line = b[i];
				var m = line.match(/^\/(.*)\/([a-z]*)/i);
				if (m===null) continue;
				if (m[1] != "\\b"+no+"\\b") continue;
				b.splice(i, 1);
				i--;
			}
		} else {
			b.push("/\\b"+no+"\\b/");
			hack = false;
		}
		localStorage.setItem("filter_postnum", b.join("\n"));
		kkfilter.reset();
		kkfilter.startup();
		var fm = $id("filtermode");
		if (fm) {
			fm.value = "filter_postnum";
			kkfilter.update_textarea("filter_postnum");
		}
		if (hack) {
			p.classList.remove("filter");
		}
	}
};

class kkFilter {
	constructor (name, storagename, func) {
		if (!name) {
			delete this;
			return;
		}
		this.name = name;
		this.storagename = storagename;
		this.func = func;
		kkfilter.F.push(this);
	}
	exec () {
		var a = localStorage.getItem(this.storagename); if (a===null) a = '';
		var b = a.split("\n");
		var that = this;
		b.forEach( function (line, i) {
			if (line.match(/^\s*(#|$)/)) return; // continue
			var m = line.match(/^\/(.*)\/([a-z]*)/i);
			if (m===null) {
				kkfilter.die = 'Invalid Regex in <q>'+that.name+'</q> on line '+i;
				return; // continue
			}
			var r = new RegExp(m[1], m[2]);
			for (var post of kkjs.posts) {
				if (that.func(post, r)) {
					if (post.classList.contains("op")) {
						if ($class("thread").length!=1) {
							post.parentNode.classList.add("filter");
							post.classList.add("filter");
						}
					}
					else post.classList.add("filter");
				}
			}
		} );
	}
}

new kkFilter("General", "filter_general", function(post, r) {
	var find = false;
	kkfilter.F.forEach(function(F){
		if (F.storagename=="filter_general") return; // continue
		if (F.func(post, r)) find = true;
	});
	return find;
});

new kkFilter("Post number", "filter_postnum", function(post, r) {
	var pnum = post.getElementsByClassName("postnum")[0];
	if (typeof(pnum) === "undefined") return false;
	return pnum.textContent.match(r) !== null;
});

new kkFilter("Name", "filter_name", function(post, r) {
	var name = post.getElementsByClassName("name")[0];
	if (typeof(name) === "undefined") return false;
	return name.textContent.match(r) !== null;
});

new kkFilter("Subject", "filter_sub", function(post, r) {
	var subject = post.getElementsByClassName("title")[0];
	if (typeof(subject) === "undefined") return false;
	return subject.textContent.match(r) !== null;
});

new kkFilter("Comment", "filter_com", function(post, r) {
	var comment = post.getElementsByClassName("comment")[0];
	if (typeof(comment) === "undefined") return false;
	return comment.textContent.match(r) !== null;
});

new kkFilter("Filename", "filter_fname", function(post, r) {
	var a = post.querySelector(".filesize a");
	if (a == null) return false;
	return a.textContent.match(r) !== null;
});

new kkFilter("Category", "filter_category", function(post, r) {
	var category = post.querySelectorAll(".category a");
	if (typeof(category) === "undefined") return false;
	var find = false;
	for (var a of category) {
		if (a.textContent.match(r)) find = true;
	}
	return find;
});

/* Register */
if(typeof(KOKOJS)!="undefined"){kkjs.modules.push(kkfilter);}else{console.log("ERROR: KOKOJS not loaded!\nPlease load 'koko.js' before this script.");}