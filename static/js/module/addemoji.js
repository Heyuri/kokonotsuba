/* addemoji.js - Hydrates server-rendered emoji container with lazy loading */
(function() {
	const COMMENT = document.getElementById("com");
	if (!COMMENT) return;

	const emojiContainer = document.getElementById("emojiContainer");
	if (!emojiContainer) return;

	let emojiPopulated = false;
	emojiContainer.addEventListener("toggle", () => {
		if (!emojiContainer.open || emojiPopulated) return;
		emojiPopulated = true;

		const dataEl = document.getElementById("emojiData");
		if (!dataEl) return;
		let data;
		try { data = JSON.parse(dataEl.textContent); } catch(e) { return; }

		const target = document.getElementById("emojiButtons") || emojiContainer;
		data.items.forEach((emoji, index) => {
			let button = document.createElement("button");
			button.type = "button";
			button.classList.add("buttonEmoji", "emojiButton");
			button.title = emoji.title;
			button.value = emoji.value;

			let img = document.createElement("img");
			img.className = "emojiImage";
			img.src = data.baseUrl + emoji.src;
			img.loading = "lazy";
			img.title = emoji.title;
			img.alt = emoji.title;
			img.height = 24;
			button.appendChild(img);

			button.addEventListener("click", (e) => insertAtCursor(COMMENT, e.currentTarget.value));
			target.appendChild(button);
			if ((index + 1) % 70 === 0) {
				button.classList.add("row-end");
			}
		});
	});
})();
