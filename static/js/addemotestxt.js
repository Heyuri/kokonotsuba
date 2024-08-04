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
  {meaning:"<b>Bold</b>&nbsp;",code:"b"},
  {meaning:"<i>Italics</i>&nbsp;",code:"i"},
  {meaning:"<u>Underline</u>&nbsp;",code:"u"},
  {meaning:"<s>Strikethrough</s>&nbsp;",code:"del"},
  {meaning:"<mark style='background-color:black;color:white'>&nbsp;Spoiler&nbsp;</mark>",code:"s"},
  {meaning:"<pre style='display:inline'>Preformatted</pre>",code:"pre"},
  {meaning:"<q>Blockquote</q>&nbsp;",code:"quote"},
  {meaning:"<code style='background-color:white;color:black'>&nbsp;Code&nbsp;</code>&nbsp;",code:"code"},
];
const selector_bbcode = [ // BBCodes with selectors
  {meaning:"<b style='display:inline;color:#489b67'>C<span style='color:#d30615'>o</span>l<span style='color:#d30615'>o</span>r</b>&nbsp;", code:"color",selector:"#800043"},
  {meaning:"<h3 style='display:inline'>Size</h3>&nbsp;",code:"s",selector:"7"},
];

/* CONSTANTS */
const NUM_EMOTES = emotes_list.length;
const COMMENT = document.getElementById("com");

/* FUNCTIONS */
const onClickHandler = (e) => insertAtCursor(COMMENT, e.target.title); //COMMENT.value += e.target.title;

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
    let checkboxes = document.querySelectorAll("#bbcode_container > label > input[type=checkbox]");

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
    insertAtCursor(COMMENT, formatted_str)//COMMENT.value += formatted_str;
  }
};

/* EMOTES */
let emotes_container = document.createElement("details");
emotes_container.innerHTML += SUMMARY_ELEMENT("Emotes");

emotes_list.forEach((emote,index) => {
  let button = document.createElement('button');
  button.type = "button";
  button.title = emote.value;
  button.innerHTML += '<img src="./static/image/emote/'+emote.src+'" alt="'+emote.value+'" title="'+emote.value+'" height="30px"/>';
  button.addEventListener("click", onClickHandler);
  emotes_container.appendChild(button);
  if (index%8 === 7) { // 8 emotes per row
    emotes_container.appendChild(document.createElement('br'));
  }
});

// A.after(B), B.after(C), etc... = A --> B --> C --> ...
COMMENT.after(emotes_container); // insert emotes after comment box

/* SHIFT_JIS */
let sjis_container = document.createElement("details");
sjis_container.innerHTML += SUMMARY_ELEMENT("Kaomoji");

shift_jis.forEach((sjis, index) => {
  let button = document.createElement('button');
  button.type = "button";
  button.title = sjis.value;
  button.dataset.value = sjis.value;
  button.innerHTML += '<div class="ascii" title="' + sjis.value + '">' + sjis.display + '</div>';
  button.addEventListener("click", onClickHandler);
  sjis_container.appendChild(button);
  if (index % 7 === 6) { // 7 kaomoji per row
    sjis_container.appendChild(document.createElement('br'));
  }
});
emotes_container.after(sjis_container); // insert sjis after emotes

/* BBCODE */
let bbcode_container = document.createElement("details");
bbcode_container.id = "bbcode_container";
bbcode_container.innerHTML += SUMMARY_ELEMENT("BBCode");
bbcode.forEach((code,index) => {
  let input_label = document.createElement("label");

  let checkbox = document.createElement("input");
  checkbox.type = "checkbox";

  input_label.appendChild(checkbox);
  input_label.innerHTML += code.meaning+" ";
  if (index%4 === 0 && index !== 0) { // 5 BBCodes per row
    bbcode_container.appendChild(document.createElement('br'));
  }
  bbcode_container.appendChild(input_label);
});
bbcode_container.appendChild(document.createElement('br'));

/* bbcode with selectors */
// color
let color_label = document.createElement("label");

let color_checkbox = document.createElement("input");
color_checkbox.type = "checkbox";
color_label.appendChild(color_checkbox);
color_label.innerHTML += selector_bbcode[0].meaning;

let color_input = document.createElement("input");
color_input.type = "color";
color_input.value = "#800043";
color_input.addEventListener('change',(e)=>selector_bbcode[0].selector=e.target.value);

color_label.appendChild(color_input);
bbcode_container.appendChild(color_label);

//size
let size_label = document.createElement("label");

let size_checkbox = document.createElement("input");
size_checkbox.type = "checkbox";
size_label.appendChild(size_checkbox);
size_label.innerHTML += selector_bbcode[1].meaning;

let size_input = document.createElement("input");
size_input.type = "number";
size_input.value = "7";
size_input.min = "1";
size_input.max = "7";
size_input.size = "1";
size_input.addEventListener('change',(e)=>selector_bbcode[1].selector=e.target.value);

size_label.appendChild(size_input);
bbcode_container.appendChild(size_label);
bbcode_container.appendChild(document.createElement("br"));

// text input + add button
let text_input = document.createElement("textarea");
text_input.rows = "3";
text_input.cols = "30";
text_input.id = "bbcode_text_input";
let add_button = document.createElement("input");
add_button.type = "button";
add_button.value = "Add";
add_button.id = "bbcode_add_button";
add_button.addEventListener('click', insertBBCode);

bbcode_container.appendChild(text_input);
bbcode_container.appendChild(add_button);

sjis_container.after(bbcode_container);
