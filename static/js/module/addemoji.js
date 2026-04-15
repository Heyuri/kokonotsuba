/* addemoji.js - Wires click handlers on server-rendered emoji buttons */
(function() {
	const COMMENT = document.getElementById("com");
	if (!COMMENT) return;

	const emojiContainer = document.getElementById("emojiContainer");
	if (!emojiContainer) return;

	emojiContainer.addEventListener("click", (e) => {
		const button = e.target.closest(".emojiButton");
		if (button) insertAtCursor(COMMENT, button.value);
	});
})();
