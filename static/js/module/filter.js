/* LOL HEYURI */

/* Module */
const kkfilter = {
	name: "KK Filter",
	F: Array(),
	die: '',
	matchers: null,
	hiddenImages: [],
	pass: 0,
	startup: function () {
		// the early pass compiled already; reset() clears it to force a fresh one
		if (!kkfilter.matchers) kkfilter.compile();
		for (var post of kkjs.posts) kkfilter.applyToPost(post);

		// Hook into the widget dropdown system (if it's loaded)
		if (window.postWidget) {

			// 1. Dynamic label for the "hide" widget action
			//    Returns "Hide" or "Show" depending on the post's current state
			if (typeof window.postWidget.registerLabelProvider === 'function') {
				window.postWidget.registerLabelProvider('hide', function (ctx) {
					var post = ctx && (ctx.post || (ctx.arrow && ctx.arrow.closest('.post')));
					if (!post) return 'Hide';

					var hidden =
						post.classList.contains('filter') ||
						(
							post.classList.contains('op') &&
							post.parentNode &&
							post.parentNode.classList &&
							post.parentNode.classList.contains('filter')
						);

					return hidden ? 'Show' : 'Hide';
				});
			}

			// 2. Action handler for clicking the "hide" widget item
			//    Calls the existing toggle logic
			if (typeof window.postWidget.registerActionHandler === 'function') {
				window.postWidget.registerActionHandler('hide', function (ctx) {
					if (!ctx) return;

					var post = ctx.post || (ctx.arrow && ctx.arrow.closest('.post'));
					if (!post || !post.id) return;

					var no = post.id.slice(1); // strip leading "p" from post id
					kkfilter.togglepostno(no);
				});
			}
		}

		// Hook into the attachment widget dropdown (if it's loaded)
		if (window.attachmentWidget) {
			// Dynamic label: "Hide image" or "Show image"
			window.attachmentWidget.registerLabelProvider('hideImage', function (ctx) {
				if (!ctx.container || !ctx.post) return null;
				var key = kkfilter.getAttachmentKey(ctx.post, ctx.container);
				if (!key) return null;
				return kkfilter.isImageHidden(key) ? 'Show image' : 'Hide image';
			});

			// Action handler: toggle image visibility
			window.attachmentWidget.registerActionHandler('hideImage', function (ctx) {
				if (!ctx.container || !ctx.post) return;
				var key = kkfilter.getAttachmentKey(ctx.post, ctx.container);
				if (!key) return;
				kkfilter.toggleImage(key, ctx.container);
			});
		}

		return true;
	},

	// Compile every stored filter into matchers, once per pass
	compile: function () {
		kkfilter.die = '';
		kkfilter.pass++;
		kkfilter.matchers = [];
		kkfilter.F.forEach(function (F) { F.compile(kkfilter.matchers); });
		kkfilter.hiddenImages = kkfilter.getHiddenImages();
	},
	isThreadPage: function () {
		return /[?&]res=\d/.test(location.search);
	},
	matches: function (post) {
		return kkfilter.matchers.some(function (m) { return m.func(post, m.regex); });
	},
	applyToPost: function (post) {
		// skip posts already handled in this pass
		if (post.kkFilterPass === kkfilter.pass) return;
		post.kkFilterPass = kkfilter.pass;
		if (kkfilter.matches(post)) {
			if (post.classList.contains('op')) {
				// an OP is never hidden on its own thread page
				if (!kkfilter.isThreadPage()) {
					var thread = post.closest('.thread');
					if (thread) thread.classList.add('filter');
					post.classList.add('filter');
				}
			} else post.classList.add('filter');
		}
		kkfilter.applyHiddenImages(post);
	},

	/*
	 * Runs from <head>: filters each post as the parser finishes it, so a hidden
	 * post never paints. The post still being parsed is held invisible until its
	 * end tag arrives, since its comment cannot be matched before then.
	 */
	early: function () {
		if (!window.MutationObserver) return;
		kkfilter.compile();
		if (!kkfilter.matchers.length && !kkfilter.hiddenImages.length) return;

		var posts = $class('post');
		var next = 0;
		var pending = null;

		// true once something follows the element, i.e. its end tag has been parsed
		function complete(el) {
			for (var n = el; n && n !== $doc.body; n = n.parentNode) {
				if (n.nextSibling) return true;
			}
			return false;
		}

		function sweep() {
			// every post but the newest is complete, since a later one follows it
			var count = posts.length;
			while (next < count && (next < count - 1 || complete(posts[next]))) {
				kkfilter.applyToPost(posts[next]);
				next++;
			}
			var tail = next < count ? posts[next] : null;
			if (pending && pending !== tail) pending.classList.remove('filterPending');
			if (tail && tail !== pending) tail.classList.add('filterPending');
			pending = tail;
		}

		var observer = new MutationObserver(sweep);
		observer.observe($doc.documentElement, { childList: true, subtree: true });
		$doc.addEventListener('DOMContentLoaded', function () {
			observer.disconnect();
			sweep();
		});
	},

	reset: function () {
		var f = $class("filter");
		for (var i=f.length; i; i--) {
			f[i-1].classList.remove("filter");
		}
		var fp = $class("filterpostContainer");
		for (var i=fp.length; i; i--) {
			fp[i-1].remove();
		}
		kkfilter.die = '';
		kkfilter.matchers = null;
	},
	sett_tab: function (id) {
		$id(id).innerHTML+= ' | <a href="javascript:kkjs.sett_tab(\'filter\');" id="settab_filter">Filter</a>';
	},
	sett: function (tab, div) {
		if (tab!="filter") return;
		div.innerHTML+= `
			<details>
				<summary>Guide</summary>
				<ul>
					<li>Use <a href="https://developer.mozilla.org/en-US/docs/Web/JavaScript/Guide/Regular_Expressions" target="_blank">regular expressions</a>, one per line.</li>
					<li>Lines starting with a # will be ignored.</li>
					<li>For example, <code class="code">/weeaboo/i</code> will filter posts containing the string <code class="code">weeaboo</code>, case-insensitive.</li>
				</ul>
			</details>
			<select id="filtermode" onchange="kkfilter.update_textarea(this.value);"></select>
			<textarea id="settuserfilter" class="inputtext" oninput="kkfilter.update_filter(this.value);" placeholder="Regex filters"></textarea>
			<div id="filterdie"></div>`;
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
	console.log("Filter saved to localStorage:", FM, value);
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
		var a = localStorage.getItem("filter_postnum");
		if (a===null) a = '';
		var b = a.split("\n");
		var hide = false;
		var filter = `/^p${no}$/`;
		if (!p.classList.contains("filter")) {
			hide = true;
		}
		if (hide) {
			kkfilter.hidepost(no);
			b.push(filter);
		} else {
			kkfilter.showpost(no);
			if (b.indexOf(filter) >= 0) {
				b.splice(b.indexOf(filter), 1);
			}
		}

		localStorage.setItem("filter_postnum", b.join("\n"));
		var fm = $id("filtermode");
		if (fm) {
			fm.value = "filter_postnum";
			kkfilter.update_textarea("filter_postnum");
		}
	},
	showpost: function (no) {
		var p = $id('p' + no);
		p.classList.remove('filter');

		if (p.classList.contains('op')) {
			var thread = p.closest('.thread');
			if (thread) thread.classList.remove('filter');
		}

		var link = p.querySelector('.filterpost');
		if (link) {
			link.innerText = 'Hide';
			link.title = 'Hide this post';
		}
	},
	hidepost: function(no) {
		var p = $id('p' + no);
		p.classList.add("filter");
	
		if (p.classList.contains("op")) {
			var thread = p.closest('.thread');
			if (thread) {
				thread.classList.add('filter');
				if (thread.getBoundingClientRect().top < 0) thread.scrollIntoView();
			}
		}
	
		var link = p.querySelector('.filterpost');
		if (link) {
			link.innerText = 'Show';
			link.title = 'Show this post';
		}
	},

	// ---- Image hiding ----

	getAttachmentKey: function (postEl, containerEl) {
		if (!postEl || !postEl.id) return null;
		var postNo = postEl.id.slice(1); // strip "p"
		var anchor = containerEl.querySelector('.attachmentAnchor');
		var index = anchor ? anchor.dataset.attachmentIndex : '0';
		return postNo + '-' + index;
	},

	getHiddenImages: function () {
		try {
			return JSON.parse(localStorage.getItem('filter_hidden_images')) || [];
		} catch (e) {
			return [];
		}
	},

	saveHiddenImages: function (list) {
		localStorage.setItem('filter_hidden_images', JSON.stringify(list));
	},

	isImageHidden: function (key) {
		return this.getHiddenImages().indexOf(key) !== -1;
	},

	toggleImage: function (key, containerEl) {
		var list = this.getHiddenImages();
		var idx = list.indexOf(key);
		if (idx === -1) {
			list.push(key);
			var expanded = containerEl.querySelector('.expandedImageContainer, .expand');
			if (expanded) {
				var anchor = containerEl.querySelector('.attachmentAnchor');
				if (anchor) anchor.style.display = '';
				expanded.remove();
			}
			containerEl.classList.add('filterImage');
		} else {
			list.splice(idx, 1);
			containerEl.classList.remove('filterImage');
		}
		this.saveHiddenImages(list);
		kkfilter.hiddenImages = list;
	},

	applyHiddenImages: function (post) {
		var list = kkfilter.hiddenImages;
		if (!list.length || !post.id) return;
		var postNo = post.id.slice(1);
		post.querySelectorAll('.attachmentContainer').forEach(function (c) {
			var anchor = c.querySelector('.attachmentAnchor');
			if (anchor && list.indexOf(postNo + '-' + anchor.dataset.attachmentIndex) !== -1) {
				c.classList.add('filterImage');
			}
		});
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
	// Append a matcher per valid stored line to list
	compile (list) {
		var a = localStorage.getItem(this.storagename);
		if (a===null) a = '';
		var that = this;
		a.split("\n").forEach( function (line, i) {
			if (line.match(/^\s*(#|$)/)) return; // continue
			var m = line.match(/^\/(.*)\/([a-z]*)/i);
			if (m===null) {
				kkfilter.die = 'Invalid Regex in <q>'+that.name+'</q> on line '+i;
				return; // continue
			}
			try {
				var r = new RegExp(m[1], m[2]);
			} catch (e) {
				kkfilter.die = 'Invalid Regex in <q>' + that.name + '</q> on line ' + i + ':<div>' + e.message + '</div>';
				return; // continue
			}
			list.push({ func: that.func, regex: r });
		});
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
	return post.id.match(r) !== null;
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
if (typeof(KOKOJS) != "undefined"){
	kkjs.modules.push(kkfilter);
	kkfilter.early();
} else {
	console.log("ERROR: KOKOJS not loaded!\nPlease load 'koko.js' before this script.");
}
