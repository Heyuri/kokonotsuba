/* Catalog Module */
const kkcat = {
	name: "KK Catalog Functions",
	cat: null,

	/**
	 * Initialize the catalog module.
	 * Hooks into server-rendered controls (search, settings, sort)
	 * that are already present in the HTML with the 'js-only' class.
	 */
	startup: function () {
		kkcat.cat = $id("catalog");
		if (!kkcat.cat) return true;

		// Restore saved display preferences into the settings controls
		kkcat._restoreSettings();

		// Apply display settings immediately so classes match cookie state
		kkcat.applyDisplaySettings();

		// Restore search query from URL hash
		var searchInput = $id("sett_ss");
		if (searchInput) {
			searchInput.value = location.hash.substring(1);
			searchInput.addEventListener("input", function () {
				kkcat.search(this.value);
			});
		}

		// Hook the case-insensitive checkbox
		var caseCheckbox = $id("sett_sscase");
		if (caseCheckbox) {
			caseCheckbox.addEventListener("change", function () {
				localStorage.setItem("sett_sscase", this.checked);
				kkcat.search();
			});
		}

		// Apply full-width and column settings live on change
		var fwCheckbox = $id("sett_fw");
		if (fwCheckbox) {
			fwCheckbox.addEventListener("change", function () {
				kkcat.applyDisplaySettings();
			});
		}

		var colsInput = $id("sett_cols");
		if (colsInput) {
			colsInput.addEventListener("input", function () {
				kkcat.applyDisplaySettings();
			});
			colsInput.addEventListener("change", function () {
				kkcat.applyDisplaySettings();
			});
		}

		// Hook the sort dropdown for automatic re-sorting via JSON
		var sortSelect = $id("catalogSortSelect");
		if (sortSelect) {
			sortSelect.addEventListener("change", function () {
				kkcat.sortViaJson(this.value);
			});
		}

		// Run initial search filter (in case hash has a query)
		kkcat.search();

		return true;
	},

	/**
	 * Restore saved display preferences from cookies into the HTML controls.
	 */
	_restoreSettings: function () {
		var fwCheckbox = $id("sett_fw");
		if (fwCheckbox) {
			fwCheckbox.checked = localStorage.getItem("cat_fw") === "true";
		}

		var colsInput = $id("sett_cols");
		if (colsInput) {
			colsInput.value = localStorage.getItem("cat_cols") || "";
		}

		var caseCheckbox = $id("sett_sscase");
		if (caseCheckbox) {
			var saved = localStorage.getItem("sett_sscase");
			caseCheckbox.checked = saved === null ? true : saved === "true";
		}
	},

	/**
	 * Apply display settings (full-width and column count) live and save to cookies.
	 */
	applyDisplaySettings: function () {
		var fwCheckbox = $id("sett_fw");
		var colsInput = $id("sett_cols");
		var table = $id("catalogTable");
		if (!colsInput || !fwCheckbox || !table) return;

		var cols = parseInt(colsInput.value) || 0;
		var fw = fwCheckbox.checked;

		// Save to localStorage and sync to cookies for server-side rendering
		localStorage.setItem("cat_cols", cols);
		localStorage.setItem("cat_fw", fw);
		kkjs.set_cookie("cat_cols", cols, 365 * 24 * 60 * 60 * 1000);
		kkjs.set_cookie("cat_fw", fw, 365 * 24 * 60 * 60 * 1000);

		// Toggle full-width class
		if (fw) {
			table.classList.add("full-width");
		} else {
			table.classList.remove("full-width");
		}

		// Toggle auto-cols / fixed-cols
		if (cols > 0) {
			table.classList.remove("auto-cols");
			table.classList.add("fixed-cols");
			table.style.setProperty("--cat-cols", cols);
		} else {
			table.classList.remove("fixed-cols");
			table.classList.add("auto-cols");
		}
	},

	/**
	 * Filter catalog threads by search string.
	 * Hides threads whose text content does not match the query.
	 *
	 * @param {string} str Search query (defaults to the search input value).
	 */
	search: function (str) {
		var searchInput = $id("sett_ss");
		if (typeof str === "undefined" || str === null) {
			str = searchInput ? searchInput.value : "";
		}

		var caseCheckbox = $id("sett_sscase");
		var caseInsensitive = caseCheckbox ? caseCheckbox.checked : true;
		if (caseInsensitive) str = str.toLowerCase();

		var threads = $q("#catalog .thread");
		for (var i = 0; i < threads.length; i++) {
			var text = threads[i].innerText;
			if (caseInsensitive) text = text.toLowerCase();
			threads[i].style.display = text.includes(str) ? "" : "none";
		}

		location.hash = str;
	},

	/**
	 * Fetch re-sorted catalog entries from the JSON endpoint
	 * and rebuild the catalog table body without a full page reload.
	 *
	 * @param {string} sortBy Sort key: 'bump' or 'time'.
	 */
	sortViaJson: function (sortBy) {
		var meta = document.querySelector('meta[name="catalogJsonUrl"]');
		if (!meta) return;

		var url = meta.getAttribute("content") + "&sort_by=" + encodeURIComponent(sortBy);

		var xhr = new XMLHttpRequest();
		xhr.open("GET", url, true);
		xhr.onreadystatechange = function () {
			if (xhr.readyState !== 4) return;
			if (xhr.status !== 200) return;

			try {
				var entries = JSON.parse(xhr.responseText);
			} catch (e) {
				return;
			}

			kkcat._rebuildTable(entries);

			// Persist sort choice to cookie
			kkjs.set_cookie("cat_sort_by", sortBy);

			// Re-apply search filter after rebuild
			kkcat.search();
		};
		xhr.send();
	},

	/**
	 * Rebuild the catalog table body from a JSON entries array.
	 *
	 * @param {Array} entries Array of catalog entry objects from the JSON endpoint.
	 */
	_rebuildTable: function (entries) {
		var table = $id("catalogTable");
		if (!table) return;

		var tbody = table.querySelector("tbody");
		if (!tbody) return;

		var tpl = $id("catalogThreadTpl");
		if (!tpl) return;

		// Grab the replies icon URL from the page before clearing
		var existingIcon = table.querySelector(".icon");
		var iconSrc = existingIcon ? existingIcon.src : "";

		var tr = document.createElement("tr");

		for (var i = 0; i < entries.length; i++) {
			var e = entries[i];
			var cell = tpl.content.firstElementChild.cloneNode(true);

			// Set thread links (thumb link + title link)
			var links = cell.querySelectorAll("a");
			links[0].href = e.url;
			links[1].href = e.url;

			// Build thumbnail image (template has empty THUMB_HTML placeholder)
			var img = document.createElement("img");
			img.className = "thumb";
			img.src = e.thumb;
			if (e.tw) img.width = e.tw;
			links[0].appendChild(img);

			// Set subject, reply count, comment, and replies icon
			cell.querySelector(".title").textContent = e.sub;
			cell.querySelector(".replyCount").textContent = e.r;
			cell.querySelector(".catComment").innerHTML = e.com;
			var icon = cell.querySelector(".icon");
			if (icon) icon.src = iconSrc;

			tr.appendChild(cell);
		}

		tbody.innerHTML = "";
		tbody.appendChild(tr);
	},

	/**
	 * Basic HTML attribute escaping.
	 *
	 * @param {string} str String to escape.
	 * @returns {string} Escaped string.
	 */
	_esc: function (str) {
		if (!str) return "";
		var div = document.createElement("div");
		div.appendChild(document.createTextNode(str));
		return div.innerHTML;
	},

	/**
	 * Clean up on module reset.
	 */
	reset: function () {
		// Nothing to remove since controls are server-rendered
	}
};

/* Register */
if (typeof KOKOJS !== "undefined") {
	kkjs.modules.push(kkcat);
} else {
	console.log("ERROR: KOKOJS not loaded!\nPlease load 'koko.js' before this script.");
}
