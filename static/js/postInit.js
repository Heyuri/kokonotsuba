/**
 * Central helper: initialize JS features on newly inserted post elements.
 * Call once after appending new reply containers to the DOM.
 *
 * Best-effort by design: the posts are already in the page by the time this runs, so a
 * feature throwing here must not abort the caller mid-flow. The noko path still has a form
 * to clear and a post to scroll to after inserting.
 *
 * @param {Element[]|NodeList} newElements - newly added reply containers
 */
function initNewPosts(newElements) {
	try {
		if (newElements && newElements.length) {
			var useQr = typeof kkqr !== "undefined" && kkqr
				&& _kkSetting("useqr");

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
	} catch (e) {
		try { console.warn("initNewPosts:", e); } catch (e2) {}
	}
}
