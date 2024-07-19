/* LOL HEYURI
 */

document.write(`<style id="qrs">
#qrinputs {
	display: flex;
	flex-direction: column;
}
#qrinputs>div {
	display: flex;
	flex-direction: row;
}
#qrinputs>div>.inputtext {
	flex-basis: -moz-available;
	width: 100%;
}
#qrinputs button {
	white-space: nowrap;
}
#qrcaptcha {
	padding: 0.2em;
	margin: 0.2em 0;
}
#qrcom, #qrname, #qremail {
width: 90%;

}

#wintop {
width: 300px;

}
</style>`);

/* Module */
const kkqr = { name: "KK Quick Reply",
	startup: function () {
		kkqr.qrs.disabled = true;
		if (!localStorage.getItem("useqr"))
			localStorage.setItem("useqr", true);
		if (!$id("postform")) return true;
		$id("formfuncs").insertAdjacentHTML("beforeend", '<span id="qrfunc"> | <a href="javascript:kkqr.openqr();">Quick Reply</a></span>');
		if (localStorage.getItem("useqr")=="true") {
			if (localStorage.getItem("alwaysqr")=="true") {
				kkqr.openqr();
			}
			var qu = $class("qu");
			for (var i=0; i<qu.length; i++) {
				qu[i].addEventListener("click", kkqr._evqr);
			}
		}
		return true;
	},
	reset: function () {
		if (!$id("postform")) return true;
		kkqr.closeqr();
		$del($id("qrfunc"));
		if (localStorage.getItem("useqr")=="true") {
			var qu = $class("qu");
			for (var i=0; i<qu.length; i++) {
				qu[i].removeEventListener("click", kkqr._evqr);
			}
		}
	},
	/* - */
	qrs: $id("qrs"),
	win: null,
	/* Settings */
	sett: function (tab, div) { if (tab!="general") return;
		div.innerHTML+= '<label><input type="checkbox" onchange="localStorage.setItem(\'useqr\',this.checked);location.reload();"'+(localStorage.getItem("useqr")=="true"?'checked="checked"':'')+' />Use quick reply</label>';
		div.innerHTML+= '<label><input type="checkbox" onchange="localStorage.setItem(\'alwaysqr\',this.checked);if(this.checked&&!kkqr.win)kkqr.openqr();"'+(localStorage.getItem("alwaysqr")=="true"?'checked="checked"':'')+' />Persistent quick reply</label>';
	},
	/* Function */
	_evqr: function (event) {
		if (!kkqr.win) {
			kkqr.openqr();
			this.scrollIntoView({behavior:"smooth",block:"center"});
		}
	},
	openqr: function () {
		var d = $doc.documentElement;
		var pw=d.clientWidth/5, ph=320;
		if (pw<350) pw=350;
		if (pw>400) pw=400;
		var pm = $q("#postform .theading"), pmstr;
		if (pm.length) pmstr = pm[0].innerText;
		else pmstr = "Quick Reply";
		if (exist = $kkwm_name(pmstr)) {
			exist.flash();
			return;
		}
		kkqr.win = new kkwmWindow(pmstr, {x: d.clientWidth-pw-40, w: pw, y: d.clientHeight-ph-60, h: ph});
		kkqr.win.onclose = kkqr.closeqr;
		kkqr.qrs.disabled = false;
		var pf = $id("postform");
		with (pf) {
			kkqr.win.div.innerHTML+= '<div id="qrinputs"></div>';
			var qr = $id("qrinputs");
			var submitplace = 'qr';
			if (typeof(name)!='undefined') {
				qr.innerHTML+= '<div id="qrnamediv"><input type="text" name="name" id="qrname" value="'+name.value+'" maxlength="100" class="inputtext" placeholder="Name" oninput="kkqr.input(this);"/></div>';
				submitplace = 'qrnamediv';
			}
			if (typeof(email)!='undefined') {
				qr.innerHTML+= '<div id="qremaildiv"><input type="text" name="email" id="qremail" value="'+email.value+'" maxlength="100" class="inputtext" placeholder="Email" oninput="kkqr.input(this);"/></div>';
				if (!submitplace) submitplace = 'qremaildiv';
			}
			if (typeof(sub)!='undefined') {
				qr.innerHTML+= '';
				submitplace = 'qrsubdiv';
			}
			if (typeof(com)!='undefined')
				qr.innerHTML+= '<textarea name="com" id="qrcom" maxlength="5000" cols="48" rows="6" class="inputtext" placeholder="Comment" oninput="kkqr.input(this);">'+com.value+'</textarea>';
			if (typeof(upfile)!='undefined') {
				upfile.insertAdjacentHTML("beforebegin", '<span id="upfileDUMMY"></span>');
				kkqr.win.div.appendChild(upfile);
				kkqr.win.div.innerHTML+= '<small>[<a href="javascript:void(0);" onclick="$id(\'upfile\').value=\'\';">X</a>]</small> <br />';
			}
			if (typeof(noimg)!='undefined')
				kkqr.win.div.innerHTML+= '<nobr><label>[<input type="checkbox" name="noimg" id="qrnoimg" onclick="$id(\'noimg\').checked=this.checked;"'+(noimg.checked?' checked="checked"':'')+' />No File]</label></nobr> ';
			if (typeof(anigif)!='undefined')
				kkqr.win.div.innerHTML+= '<nobr><label>[<input type="checkbox" name="anigif" id="qranigif" onclick="$id(\'anigif\').checked=this.checked;"'+(anigif.checked?' checked="checked"':'')+' />Animated GIF]</label></nobr> ';
			if (typeof(category)!='undefined')
				kkqr.win.div.innerHTML+= '';
			if (typeof(pwd)!='undefined')
				kkqr.win.div.innerHTML+= '<div><input type="password" name="pwd" id="qrpwd" size="8" maxlength="8" value="'+pwd.value+'" class="inputtext" placeholder="Password" oninput="kkqr.input(this);" /> <small>(for deletion, 8 chars max)</small></div>';
			if (typeof(captchacode)!='undefined') {
				kkqr.win.div.innerHTML+= '<div id="qrcaptcha" class="postblock"><small> [<a href="#" onclick="(function(){var i=document.getElementById(\'chaimg\'),s=i.src;i.src=s+\'&amp;\';})();">Reload</a>]</small><br /><input type="text" name="captchacode" id="qrcaptchacode" value="'+captchacode.value+'" autocomplete="off" class="inputtext" placeholder="Captcha" oninput="kkqr.input(this);" /><nobr><small>(Please enter the words. Case-insensitive.)</small></nobr></div>';
				var qrc = $id("qrcaptcha"), chaimg = $id("chaimg");
				chaimg.insertAdjacentHTML("beforebegin", '<span id="chaimgDUMMY"></span>');
				qrc.insertAdjacentElement("afterbegin", chaimg);
			}
			kkqr.win.div.style.height = "";
		}
		var inputs = $q("#postform .inputtext");
		for (var i=0; i<inputs.length; i++) {
			inputs[i].addEventListener('input', kkqr._evinput2);
		}
		var submitbtns = $q("#postform button[name=mode]"), submitqr = $id(submitplace);
		for (var i=0; i<submitbtns.length; i++) {
			submitqr.insertAdjacentHTML("beforeend",
				'<button value="'+submitbtns[i].value+'" onclick="kkqr.closeqr();$q(\'#postform button[value=\'+this.value+\']\')[0].click();">'+submitbtns[i].innerText+'</button>');
		}
	},
	closeqr: function () {
		kkqr.qrs.disabled = true;
		if (!kkqr.win) return;
		var up = $id("upfile"), pf = $id("postform"), chaimg = $id("chaimg");
		if (up) {
			var dummy = $id("upfileDUMMY");
			dummy.insertAdjacentElement("afterend", up);
			$del(dummy);
		}
		if (chaimg) {
			var dummy = $id("chaimgDUMMY");
			dummy.insertAdjacentElement("afterend", chaimg);
			$del(dummy);
		}
		kkqr.win = null;
		var inputs = $q("#postform .inputtext");
		for (var i=0; i<inputs.length; i++) {
			inputs[i].removeEventListener('input', kkqr._evinput2);
		}
		pf.scrollIntoView({behavior:"smooth",block:"center"});
	},
	input: function (qrinput) {
		var input = $q("#postform [name="+qrinput.name+"]");
		if (!input.length) return false;
		input[0].value = qrinput.value;
		if (qrinput.name=='com'&&typeof(kkqu)!='undefined') kkqu.hlquotes();
		else if (qrinput.name=='email') {
			// cause email checkboxes to update
			var event = $doc.createEvent("HTMLEvents");
			event.initEvent("input", true, true);
			input[0].dispatchEvent(event);
		}
		return true;
	},
	input2: function (input) {
		var qrinput = $q("#qr"+input.name);
		if (!qrinput.length) return false;
		qrinput[0].value = input.value;
		return true;
	},
	/* event */
	_evinput2: function (event) {
		if (!kkqr.input2(this))
			console.log("ERROR: Cannot set value of quick reply");
	},
};

/* Register */
if(typeof(KOKOJS)!="undefined"){kkjs.modules.push(kkqr);}else{console.log("ERROR: KOKOJS not loaded!\nPlease load 'koko.js' before this script.");}
