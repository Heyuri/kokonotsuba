const kkinline = {
	name: "KK Quote Inlining",
	startup: function() {
		if (!localStorage.getItem("quoteinline")) {
			localStorage.setItem("quoteinline", "false");
		}
		if (localStorage.getItem("quoteinline") !== "true") {
			return true;
		}
		document.querySelectorAll("a.quotelink").forEach(function(link) {
			link.onclick = function(e) {
				if (e.shiftKey) {
					window.location.href = this.href;
					return false;
				}
				// if an inline-quote is already right after us, toggle it off
				const next = this.nextElementSibling;
				if (next && next.classList.contains("inline-quote")) {
					next.remove();
					return false;
				}
				// grab the target post ID
				const targetId = this.hash.replace(/^#/, "");
				const orig = document.getElementById(targetId);
				if (!orig) {
					return true;  // fall back to normal behavior
				}
				// build our inline container
				const t = document.createElement("div");
				t.classList.add("inline-quote");
				this.insertAdjacentElement("afterend", t);
				t.style.display = "table";
				t.style.border = "1px dashed";
				t.style.borderColor = window.getComputedStyle(document.querySelector(".reply"), null).getPropertyValue("border-color");
				t.innerHTML = orig.outerHTML;
				// strip out any backlinks in the cloned content
				const back = t.querySelector(".backlinks");
				if (back) back.remove();
				return false;
			};
		});
		return true;
	},
	reset: function() {
		document.querySelectorAll(".inline-quote").forEach(function(el) {
			el.remove();
		});
		document.querySelectorAll("a.quotelink").forEach(function(link) {
			link.onclick = null;
		});
	},
	sett: function(tab, div) {
		if (tab !== "general") return;
		div.innerHTML += `
			<label><input type="checkbox" onchange="localStorage.setItem('quoteinline',this.checked);kkinline.reset();kkinline.startup();" ${(localStorage.getItem("quoteinline")==="true"?'checked':'')} /> Quote inlining</label>
		`;
	}
};

if (typeof KOKOJS !== "undefined") {
	kkjs.modules.push(kkinline);
} else {
	console.error("ERROR: KOKOJS not loaded! Please load 'koko.js' before this script.");
}
