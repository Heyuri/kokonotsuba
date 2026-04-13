/* addbbcode.js - Hydrates server-rendered BBCode container */
(function() {
	const COMMENT = document.getElementById("com");
	if (!COMMENT) return;

	const bbcodeContainer = document.getElementById("bbcodeContainer");
	if (!bbcodeContainer) return;

	const selectorConfigs = {
		code: { code: "code", selector: "" },
		color: { code: "color", selector: "#800043" },
		size: { code: "s", selector: "3" },
		pre: { selector: "ASCII (monospace)" },
	};

	/* Simple wrap buttons: just add click handlers */
	bbcodeContainer.querySelectorAll(".bbcodeButton:not(.bbcodeSelectorButton)").forEach((btn) => {
		btn.addEventListener("click", () => {
			wrapSelectionWithTags(COMMENT, btn.dataset.code);
		});
	});

	/* Selector buttons: attach dropdown/input based on data-type */
	bbcodeContainer.querySelectorAll(".bbcodeSelectorButton").forEach((btn) => {
		const type = btn.dataset.type;
		const config = selectorConfigs[type];
		if (!config) return;
		const [, selectorInput] = createSelectorButton(COMMENT, config, type, btn);
		btn.parentNode.insertBefore(selectorInput, btn.nextSibling);
	});
})();
