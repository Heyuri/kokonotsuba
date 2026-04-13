/**
 * Central helper: initialize JS features on newly inserted post elements.
 * Call once after appending new reply containers to the DOM.
 *
 * @param {Element[]|NodeList} newElements - newly added reply containers
 */
function initNewPosts(newElements) {
	if (newElements && newElements.length) {
		var useQr = typeof kkqr !== "undefined" && kkqr
			&& localStorage.getItem("useqr") == "true";

		for (var i = 0; i < newElements.length; i++) {
			var quButtons = newElements[i].querySelectorAll(".qu");
			for (var j = 0; j < quButtons.length; j++) {
				if (typeof kkqu !== "undefined") quButtons[j].addEventListener("click", kkqu._evquote);
				if (useQr) quButtons[j].addEventListener("click", kkqr._evqr);
			}
		}
	}

	if (typeof attachmentExpander !== "undefined" && attachmentExpander) attachmentExpander.startUpimageExpanding();
	if (typeof kkinline !== "undefined" && kkinline) kkinline.startup();
	if (typeof kkqr !== "undefined" && kkqr) kkqr.addScrollListener();
}
