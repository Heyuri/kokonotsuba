/* DATA */
const emotes_list = [
	{src:"mona2.gif", value:":mona2:"},
	{src:"nida.gif", value:":nida:"},
	{src:"iyahoo.gif", value:":iyahoo:"},
	{src:"banana.gif", value:":banana:"},
	{src:"onigiri.gif", value:":onigiri:"},
	{src:"anime_shii01.gif", value:":shii:"},
	{src:"anime_saitama05.gif", value:":saitama:"},
	{src:"foruda.gif", value:":foruda:"},
	{src:"nagato.gif", value:":nagato:"},
	{src:"u_pata.gif", value:":pata:"},
	{src:"u_sasu.gif", value:":depression:"},
	{src:"anime_saitama06.gif", value:":saitama2:"},
	{src:"anime_miruna_pc.gif", value:":monapc:"},
	{src:"purin.gif", value:":purin:"},
	{src:"anime_imanouchi04.gif", value:":ranta:"},
];

const shift_jis = [
	{display: "ヽ(´ー｀)ノ", value: "[kao]ヽ(´ー｀)ノ[/kao]"},
	{display: "(;´Д`)", value: "[kao](;´Д`)[/kao]"},
	{display: "ヽ(´∇`)ノ", value: "[kao]ヽ(´∇`)ノ[/kao]"},
	{display: "(´人｀)", value: "[kao](´人｀)[/kao]"},
	{display: "(＾Д^)", value: "[kao](＾Д^)[/kao]"},
	{display: "(´ー`)", value: "[kao](´ー`)[/kao]"},
	{display: "（ ´,_ゝ`）", value: "[kao]（ ´,_ゝ`）[/kao]"},
	{display: "(´～`)", value: "[kao](´～`)[/kao]"},
	{display: "(;ﾟДﾟ)", value: "[kao](;ﾟДﾟ)[/kao]"},
	{display: "(;ﾟ∀ﾟ)", value: "[kao](;ﾟ∀ﾟ)[/kao]"},
	{display: "┐(ﾟ～ﾟ)┌", value: "[kao]┐(ﾟ～ﾟ)┌[/kao]"},
	{display: "ヽ(`Д´)ノ", value: "[kao]ヽ(`Д´)ノ[/kao]"},
	{display: "( ´ω`)", value: "[kao]( ´ω`)[/kao]"},
	{display: "(ﾟー｀)", value: "[kao](ﾟー｀)[/kao]"},
	{display: "(・∀・)", value: "[kao](・∀・)[/kao]"},
	{display: "（⌒∇⌒ゞ）", value: "[kao]（⌒∇⌒ゞ）[/kao]"},
	{display: "(ﾟ血ﾟ#)", value: "[kao](ﾟ血ﾟ#)[/kao]"},
	{display: "(ﾟｰﾟ)", value: "[kao](ﾟｰﾟ)[/kao]"},
	{display: "(´￢`)", value: "[kao](´￢`)[/kao]"},
	{display: "(´π｀)", value: "[kao](´π｀)[/kao]"},
	{display: "ヽ(ﾟρﾟ)ノ", value: "[kao]ヽ(ﾟρﾟ)ノ[/kao]"},
	{display: "Σ(;ﾟДﾟ)", value: "[kao]Σ(;ﾟДﾟ)[/kao]"},
	{display: "Σ(ﾟдﾟ|||)", value: "[kao]Σ(ﾟдﾟ|||)[/kao]"},
	{display: "(*ﾟ∀ﾟ)", value: "[kao](*ﾟ∀ﾟ)[/kao]"},
	{display: "(￣ー￣)", value: "[kao](￣ー￣)[/kao]"},
	{display: "＼(＾o＾)／", value: "[kao]＼(＾o＾)／[/kao]"},
	{display: "(´･ω･`)", value: "[kao](´･ω･`)[/kao]"},
	{display: "(σ･∀･)σ", value: "[kao](σ･∀･)σ[/kao]"},
	{display: "(*^ーﾟ)b ", value: "[kao](*^ーﾟ)b [/kao]"},
	{display: "(-＿-)", value: "[kao](-＿-)[/kao]"},
	{display: "(=ﾟωﾟ)ﾉ", value: "[kao](=ﾟωﾟ)ﾉ[/kao]"},
	{display: "（・Ａ・）", value: "[kao]（・Ａ・）[/kao]"},
	{display: "（　´∀｀）つ", value: "[kao]（　´∀｀）つ[/kao]"},
	{display: "(＠^▽^)/", value: "[kao](＠^▽^)/[/kao]"}
];

const bbcode = [
	{meaning: "<b>B</b>", title: "Bold", code:"b"},
	{meaning: "<i>I</i>", title: "Italics", code:"i"},
	{meaning: "<u>U</u>", title: "Underline", code:"u"},
	{meaning: "<s>S</s>", title: "Strikethrough", code:"del"},
	{meaning: "<span style='background-color:black;color:white'>Spoiler</span>", title: "Spoiler", code:"s"},
	{meaning: "<q>Quote</q>", title: "Blockquote", code:"quote"},
	{meaning: "<code class='code'>Code</code>", title: "Code", code:"code"},
];

const selector_bbcode = [ // BBCodes with selectors
	{meaning:"<span style='font-weight:bold'><span class='bokuRed'>C</span><span class='bokuGreen'>o</span><span class='bokuRed'>l</span><span class='bokuGreen'>o</span><span class='bokuRed'>r</span>", title:"Font color", code:"color", selector:"#800043"},
	{meaning:"Size", title: "Font size", code:"s", selector:"3"},
	{meaning: "<pre style='display:inline'>ASCII</pre>", title: "ASCII art", selector:"ASCII (monospace)"},
];

let savedSelection = { start: 0, end: 0 };

/*
add text to where the cursor is located in a textarea (and focus on it)
this also replaces any selected text!
this function taken from https://stackoverflow.com/a/11077016
with minor changes
myField: which textarea to change (in this file, it's gonna be COMMENT)
myValue: what text to add
*/
function insertAtCursor(myField, myValue) {
	//IE support
	if (document.selection) {
		myField.focus();
		sel = document.selection.createRange();
		sel.text = myValue;
	}
	//MOZILLA and others
	else if (myField.selectionStart || myField.selectionStart === 0) {
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

	let openingTag = value ? `[${tag}=${value}]` : `[${tag}]`;
	let closingTag = `[/${tag}]`;

	// Special handling for [s7]...[/s7] format
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

	// Adjust selection to remain around the newly wrapped text
	textarea.focus();
	if (selectedText.length > 0) {
		textarea.selectionStart = start;
		textarea.selectionEnd = end + openingTag.length + closingTag.length;
	} else {
		// If no text was selected, place cursor between the new tags
		let cursor = start + openingTag.length;
		textarea.selectionStart = cursor;
		textarea.selectionEnd = cursor;
	}
}

/* CONSTANTS */
const NUM_EMOTES = emotes_list.length;
const COMMENT = document.getElementById("com");

/* FUNCTIONS */
const onClickHandler = (e) => insertAtCursor(COMMENT, e.target.title); //COMMENT.value += e.target.title;
const onClickHandler2 = (e) => insertAtCursor(COMMENT, e.currentTarget.value); //COMMENT.value += e.currentTarget.value;

const SUMMARY_ELEMENT = (summary_title) => {
	return "<summary style='width:fit-content;height:fit-content'"+
				 "onMouseOver='this.style.fontWeight=`bold`;this.style.cursor=`pointer`'"+
				 "onMouseOut='this.style.fontWeight=`normal`'>"+summary_title+"</summary>";
};
const insertBBCode = () => {
	if (text_input.value === "") {
		return;
	} else {
		let formatted_str = "";

		// get all checkboxes
		let checkboxes = document.querySelectorAll("#bbcodeContainer > label > input[type=checkbox]");

		// run through all checkboxes
		checkboxes.forEach((checkbox,index) => {
			if (checkbox.checked) {
				switch(index) {
					case 8: // COLOR
						formatted_str += "["+selector_bbcode[0].code+"="+selector_bbcode[0].selector+"]";
						break;
					case 9: // SIZE
						formatted_str += "["+selector_bbcode[1].code+selector_bbcode[1].selector+"]";
						break;
					default:
						//console.log(checkbox,"checkbox");
						formatted_str += "["+bbcode[index].code+"]";
						break;
				}
			}
		})

		// add string in text input after running through checkboxes
		formatted_str += text_input.value;

		// check in reverse and add closing tags
		for (let index=checkboxes.length; index>=0;index--) {
			if (checkboxes[index]?.checked) { // '?.'-https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Operators/Optional_chaining
				switch(index) {
					case 8: // COLOR
						formatted_str += "[/"+selector_bbcode[0].code+"]";
						break;
					case 9: // SIZE
						formatted_str += "[/"+selector_bbcode[1].code+selector_bbcode[1].selector+"]";
						break;
					default:
						//console.log(checkbox,"checkbox");
						formatted_str += "[/"+bbcode[index].code+"]";
						break;
				}
			}
		}

		// add the BBCode formatted string to the comment field
		insertAtCursor(COMMENT, formatted_str); //COMMENT.value += formatted_str;
	}
};

/* EMOTES */
let emotes_container = document.createElement("details");
emotes_container.innerHTML += SUMMARY_ELEMENT("Emotes");
emotes_container.id = "emotesContainer";
emotes_container.classList.add("formattingDetails");

emotes_list.forEach((emote,index) => {
	let button = document.createElement('button');
	button.type = "button";
	button.classList.add("buttonEmote");
	button.title = emote.value;
	button.classList.add("emoteButton");
	button.innerHTML += '<img class="emoteImage" src="'+STATIC_URL+'image/emote/'+emote.src+'" loading="lazy" title="'+emote.value+'" alt="'+emote.value+'">';
	button.addEventListener("click", onClickHandler);
	emotes_container.appendChild(button);
});

// A.after(B), B.after(C), etc... = A --> B --> C --> ...
COMMENT.after(emotes_container); // insert emotes after comment box

/* SHIFT_JIS */
let sjis_container = document.createElement("details");
sjis_container.innerHTML += SUMMARY_ELEMENT("Kaomoji");
sjis_container.id = "kaomojiContainer";
sjis_container.classList.add("formattingDetails");

shift_jis.forEach((sjis, index) => {
	let button = document.createElement('button');
	button.type = "button";
	button.classList.add("buttonSJIS");
	button.title = sjis.value;
	button.dataset.value = sjis.value;
	button.classList.add("kaomojiButton");
	button.innerHTML += '<div class="ascii" title="' + sjis.value + '">' + sjis.display + '</div>';
	button.addEventListener("click", onClickHandler);
	sjis_container.appendChild(button);
});
emotes_container.after(sjis_container); // insert sjis after emotes

/* BBCODE */
let bbcode_container = document.createElement("details");
bbcode_container.id = "bbcodeContainer";
bbcode_container.classList.add("formattingDetails");
bbcode_container.innerHTML += SUMMARY_ELEMENT("BBCode");

let bbcode_button_container = document.createElement("div");
bbcode_button_container.id = "bbcodeButtonContainer";

bbcode.forEach((code) => {
	let button = document.createElement("button");
	button.classList.add("bbcodeButton");
	button.type = "button";
	button.innerHTML = code.meaning;
	button.title = code.title;
	button.addEventListener("click", () => {
		wrapSelectionWithTags(COMMENT, code.code);
	});
	bbcode_button_container.appendChild(button);
});

bbcode_container.appendChild(bbcode_button_container);

const [colorBtn, colorInput] = createSelectorButton(selector_bbcode[0], "color");
bbcode_button_container.appendChild(colorBtn);
bbcode_button_container.appendChild(colorInput);

const [sizeBtn, sizeInput] = createSelectorButton(selector_bbcode[1], "size");
bbcode_button_container.appendChild(sizeBtn);
bbcode_button_container.appendChild(sizeInput);

const [preBtn, preInput] = createSelectorButton(selector_bbcode[2], "pre");
bbcode_button_container.appendChild(preBtn);
bbcode_button_container.appendChild(preInput);

function positionElementNear(trigger, targetElement) {
	document.body.appendChild(targetElement); // ensure it's in the DOM
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

function createSelectorButton(selectorConfig, type) {
	let button = document.createElement("button");
	button.classList.add("bbcodeButton");
	button.type = "button";
	button.innerHTML = selectorConfig.meaning;
	button.title = selectorConfig.title;

	let input;
	let saved = { start: 0, end: 0 };

	if (type === "color") {
		input = document.createElement("input");
		input.type = "color";
		input.value = selectorConfig.selector;
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
		if (type !== "pre") {
			selectorConfig.selector = val;
			wrapSelectionWithTags(COMMENT, selectorConfig.code, val);
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

sjis_container.after(bbcode_container);
