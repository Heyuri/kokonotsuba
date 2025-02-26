/* LOL HEYURI
 */

/* Better comment previews */
function preview() {
	const STATIC_URL = "https://static.heyuri.net/koko/"
	var p = document.querySelector("#previewView");
	var c = document.querySelector("#com").value;

	/* Standard post elements */
	c = c.replaceAll(">", "&gt;");
	c = c.replaceAll(/&gt;&gt;([0-9]+)/g, "<a href=\"#p$1\">&gt;&gt;$1</a>");
	c = c.replaceAll(/&gt;([^\n|$]+)/g, "<span class=\"quote\">&gt;$1</span>");

	/* BBCode emotes */
	var emotes = {
		'nigra': STATIC_URL+'image/emote/nigra.gif',
		'sage': STATIC_URL+'image/emote/sage.gif',
		'longcat': STATIC_URL+'image/emote/longcat.gif',
		'tacgnol': STATIC_URL+'image/emote/tacgnol.gif',
		'angry': STATIC_URL+'image/emote/emo-yotsuba-angry.gif',
		'astonish': STATIC_URL+'image/emote/emo-yotsuba-astonish.gif',
		'biggrin': STATIC_URL+'image/emote/emo-yotsuba-biggrin.gif',
		'closed-eyes': STATIC_URL+'image/emote/emo-yotsuba-closed-eyes.gif',
		'closed-eyes2': STATIC_URL+'image/emote/emo-yotsuba-closed-eyes2.gif',
		'cool': STATIC_URL+'image/emote/emo-yotsuba-cool.gif',
		'cry': STATIC_URL+'image/emote/emo-yotsuba-cry.gif',
		'dark': STATIC_URL+'image/emote/emo-yotsuba-dark.gif',
		'dizzy': STATIC_URL+'image/emote/emo-yotsuba-dizzy.gif',
		'drool': STATIC_URL+'image/emote/emo-yotsuba-drool.gif',
		'glare': STATIC_URL+'image/emote/emo-yotsuba-glare.gif',
		'glare1': STATIC_URL+'image/emote/emo-yotsuba-glare-01.gif',
		'glare2': STATIC_URL+'image/emote/emo-yotsuba-glare-02.gif',
		'happy': STATIC_URL+'image/emote/emo-yotsuba-happy.gif',
		'huh': STATIC_URL+'image/emote/emo-yotsuba-huh.gif',
		'nosebleed': STATIC_URL+'image/emote/emo-yotsuba-nosebleed.gif',
		'nyaoo-closedeyes': STATIC_URL+'image/emote/emo-yotsuba-nyaoo-closedeyes.gif',
		'nyaoo-closed-eyes': STATIC_URL+'image/emote/emo-yotsuba-nyaoo-closedeyes.gif',
		'nyaoo': STATIC_URL+'image/emote/emo-yotsuba-nyaoo.gif',
		'nyaoo2': STATIC_URL+'image/emote/emo-yotsuba-nyaoo2.gif',
		'ph34r': STATIC_URL+'image/emote/emo-yotsuba-ph34r.gif',
		'ninja': STATIC_URL+'image/emote/emo-yotsuba-ph34r.gif',
		'rolleyes': STATIC_URL+'image/emote/emo-yotsuba-rolleyes.gif',
		'rollseyes': STATIC_URL+'image/emote/emo-yotsuba-rolleyes.gif',
		'sad': STATIC_URL+'image/emote/emo-yotsuba-sad.gif',
		'smile': STATIC_URL+'image/emote/emo-yotsuba-smile.gif',
		'sweat': STATIC_URL+'image/emote/emo-yotsuba-sweat.gif',
		'sweat2': STATIC_URL+'image/emote/emo-yotsuba-sweat2.gif',
		'sweat3': STATIC_URL+'image/emote/emo-yotsuba-sweat3.gif',
		'tongue': STATIC_URL+'image/emote/emo-yotsuba-tongue.gif',
		'unsure': STATIC_URL+'image/emote/emo-yotsuba-unsure.gif',
		'wink': STATIC_URL+'image/emote/emo-yotsuba-wink.gif',
		'x3': STATIC_URL+'image/emote/emo-yotsuba-x3.gif',
		'xd': STATIC_URL+'image/emote/emo-yotsuba-xd.gif',
		'xp': STATIC_URL+'image/emote/emo-yotsuba-xp.gif',
		'party': STATIC_URL+'image/emote/emo-yotsuba-partyhat.png'
	};
	for (key in emotes) {
		c = c.replaceAll(":"+key+":", "<img class=\"emote\" src=\""+emotes[key]+"\" alt=\""+key+"\" border=\"0\">");
	}

	/* BBCode html */
	c = c.replaceAll(/\[b\](.*?)\[\/b\]/gi, "<b>$1</b>");
	c = c.replaceAll(/\[i\](.*?)\[\/i\]/gi, "<i>$1</i>");
	c = c.replaceAll(/\[u\](.*?)\[\/u\]/gi, "<u>$1</u>");
	c = c.replaceAll(/\[p\](.*?)\[\/p\]/gi, "<p>$1</p>");
	c = c.replaceAll(/\[color=(\S+?)\](.*?)\[\/color\]/gi, "<span style=\"color:$1;\">$2</span>");
	c = c.replaceAll(/\[s([1-7])\](.*?)\[\/s([1-7])\]/gi, "<span class=\"fontSize$1\">$2</span>");
	c = c.replaceAll(/\[del\](.*?)\[\/del\]/gi, "<del>$1</del>");
	c = c.replaceAll(/\[pre\](.*?)\[\/pre\]/gi, "<pre>$1</pre>");
	c = c.replaceAll(/\[blockquote\](.*?)\[\/blockquote\]/gi, "<blockquote>$1</blockquote>");
	c = c.replaceAll(/\[aa\](.*?)\[\/aa\]/gi, "<pre class=\"ascii\">$1</pre>");
	c = c.replaceAll(/\[email\](\S+?@\S+?\\.\S+?)\[\/email\]/gi, "<a href=\"mailto:$1\">$1</a>");
	c = c.replaceAll(/\[email=(\S+?@\S+?\\.\S+?)\](.*?)\[\/email\]/gi, "<a href=\"mailto:$1\">$2</a>");
	c = c.replaceAll("\r", "");
	c = c.replaceAll("\n", "<br>");
	p.innerHTML = c;
}
window.addEventListener("DOMContentLoaded", function () {
	var r = document.createElement("tr");
	r.innerHTML = "<td class=\"postblock\" align=\"left\"><b>Preview</b></td><td id=\"previewView\"></td>";
	document.querySelector("#com").parentElement.parentElement.insertAdjacentElement("afterEnd",r);
	var b = document.forms["postform"].querySelector("button[value='preview']");
	b.addEventListener("click", function (e) {
		e.preventDefault();
		preview();
		return false;
	});
});
