/* addemotes.js - Hydrates server-rendered emote/emoji/kaomoji/bbcode containers */

/* UTILITY FUNCTIONS */
function insertAtCursor(myField, myValue) {
	if (document.selection) {
		myField.focus();
		sel = document.selection.createRange();
		sel.text = myValue;
	} else if (myField.selectionStart || myField.selectionStart === 0) {
		var startPos = myField.selectionStart;
		var endPos = myField.selectionEnd;
		myField.value = myField.value.substring(0, startPos)
			+ myValue
			+ myField.value.substring(endPos, myField.value.length);
		myField.selectionStart = startPos + myValue.length;
		myField.selectionEnd = startPos + myValue.length;
		myField.focus();
	} else {
		myField.value += myValue;
	}
}

function wrapSelectionWithTags(textarea, tag, value = null) {
	let start = textarea.selectionStart;
	let end = textarea.selectionEnd;
	let selectedText = textarea.value.substring(start, end);

	let openingTag, closingTag;
	if (tag === "s" && value !== null) {
		openingTag = `[s${value}]`;
		closingTag = `[/s${value}]`;
	} else if (value !== null) {
		openingTag = `[${tag}=${value}]`;
		closingTag = `[/${tag}]`;
	} else {
		openingTag = `[${tag}]`;
		closingTag = `[/${tag}]`;
	}

	let newText = textarea.value.substring(0, start) +
		openingTag + selectedText + closingTag +
		textarea.value.substring(end);

	textarea.value = newText;
	textarea.focus();
	if (selectedText.length > 0) {
		textarea.selectionStart = start;
		textarea.selectionEnd = end + openingTag.length + closingTag.length;
	} else {
		let cursor = start + openingTag.length;
		textarea.selectionStart = cursor;
		textarea.selectionEnd = cursor;
	}
}

function positionElementNear(trigger, targetElement) {
	document.body.appendChild(targetElement);
	targetElement.style.display = "block";

	let rect = trigger.getBoundingClientRect();
	let inputWidth = targetElement.offsetWidth;
	let left = rect.left + window.scrollX;
	let top = rect.bottom + window.scrollY;
	let maxLeft = window.innerWidth + window.scrollX - inputWidth;

	if (left > maxLeft) left = maxLeft;
	if (left < 0) left = 0;

	targetElement.style.left = `${left}px`;
	targetElement.style.top = `${top}px`;
	targetElement.focus();
}

function createSelectorButton(COMMENT, config, type, existingButton = null) {
	let button = existingButton || document.createElement("button");
	if (!existingButton) {
		button.classList.add("bbcodeButton");
		button.type = "button";
		button.innerHTML = config.meaning;
		button.title = config.title;
	}

	let input;
	let saved = { start: 0, end: 0 };

	if (type === "color") {
		input = document.createElement("input");
		input.type = "color";
		input.value = config.selector;
	} else if (type === "size") {
		input = document.createElement("select");
		let placeholder = document.createElement("option");
		placeholder.disabled = true;
		placeholder.selected = true;
		placeholder.hidden = true;
		placeholder.textContent = "Select a size";
		input.appendChild(placeholder);

		const sizeLabels = {
			1: "Tiny", 2: "Small", 3: "Normal",
			4: "Large", 5: "Larger", 6: "Huge", 7: "Massive"
		};
		for (let i = 1; i <= 7; i++) {
			let opt = document.createElement("option");
			opt.value = i;
			opt.textContent = `${sizeLabels[i]} (${i})`;
			opt.className = `fontSize${i}`;
			input.appendChild(opt);
		}
	} else if (type === "pre") {
		input = document.createElement("select");
		let placeholder = document.createElement("option");
		placeholder.disabled = true;
		placeholder.selected = true;
		placeholder.hidden = true;
		placeholder.textContent = "Select format";
		input.appendChild(placeholder);

		const preOptions = [
			{ label: "ASCII (monospace)", tag: "pre" },
			{ label: "Shift-JIS (2ch)", tag: "aa" },
			{ label: "Shift-JIS (Ayashii)", tag: "sw" }
		];
		preOptions.forEach((option) => {
			let opt = document.createElement("option");
			opt.value = option.tag;
			opt.textContent = option.label;
			input.appendChild(opt);
		});
	} else if (type === "code") {
		input = document.createElement("select");
		let placeholder = document.createElement("option");
		placeholder.disabled = true;
		placeholder.selected = true;
		placeholder.hidden = true;
		placeholder.textContent = "Select a language";
		input.appendChild(placeholder);

		const langs = [
			{label: "C", value: "c"},
			{label: "C++", value: "cpp"},
			{label: "PHP", value: "php"},
			{label: "JavaScript", value: "js"},
			{label: "Python", value: "py"},
			{label: "Perl", value: "pl"},
			{label: "Fortran", value: "f"},
			{label: "HTML", value: "html"},
			{label: "CSS", value: "css"},
			{label: "Other", value: "other"}
		];
		langs.forEach(lang => {
			let opt = document.createElement("option");
			opt.value = lang.value;
			opt.textContent = lang.label;
			input.appendChild(opt);
		});
	}

	input.style.display = "none";
	input.style.position = "absolute";
	input.style.zIndex = "1000";

	button.addEventListener("click", (e) => {
		saved.start = COMMENT.selectionStart;
		saved.end = COMMENT.selectionEnd;
		positionElementNear(e.target, input);
	});

	input.addEventListener("change", (e) => {
		COMMENT.focus();
		COMMENT.setSelectionRange(saved.start, saved.end);
		let val = e.target.value;

		if (type === "code") {
			if (val === "other") {
				wrapSelectionWithTags(COMMENT, "code");
			} else {
				wrapSelectionWithTags(COMMENT, "code", val);
			}
		} else if (type !== "pre") {
			config.selector = val;
			wrapSelectionWithTags(COMMENT, config.code, val);
		} else {
			wrapSelectionWithTags(COMMENT, val);
		}
		input.style.display = "none";
	});

	input.addEventListener("blur", () => {
		input.style.display = "none";
	});

	return [button, input];
}

/* HYDRATION - runs after DOM is ready (script is loaded at end of page) */
(function() {
	const COMMENT = document.getElementById("com");
	if (!COMMENT) return;

	/* Emotes: server-rendered buttons, just add click handlers */
	const emotesContainer = document.getElementById("emotesContainer");
	if (emotesContainer) {
		emotesContainer.querySelectorAll(".emoteButton").forEach((btn) => {
			btn.addEventListener("click", (e) => insertAtCursor(COMMENT, e.target.title));
		});
	}

	/* Emoji: read JSON data from inline script, build buttons on first open */
	const emojiContainer = document.getElementById("emojiContainer");
	if (emojiContainer) {
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
				button.innerHTML = '<img class="emojiImage" src="' + data.baseUrl + emoji.src + '" loading="lazy" title="' + emoji.title + '" alt="' + emoji.title + '" height="24">';
				button.addEventListener("click", (e) => insertAtCursor(COMMENT, e.currentTarget.value));
				target.appendChild(button);
				if ((index + 1) % 70 === 0) {
					button.classList.add("row-end");
				}
			});
		});
	}

	/* Kaomoji: server-rendered buttons, just add click handlers */
	const kaomojiContainer = document.getElementById("kaomojiContainer");
	if (kaomojiContainer) {
		kaomojiContainer.querySelectorAll(".kaomojiButton").forEach((btn) => {
			btn.addEventListener("click", (e) => insertAtCursor(COMMENT, e.target.title));
		});
	}
})();
