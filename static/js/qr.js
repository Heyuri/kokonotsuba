/* LOL HEYURI
 */

document.write(`<style id="qrs"></style>`);
window.kkqrLastSubmitButton = null;


/* Module */
const kkqr = { name: "KK Quick Reply",
	startup: function () {
		// Check if a hidden input with the name "resto" doesn't exist
		if (!document.querySelector('input[name="resto"]')) {
			return true;
		}

		kkqr.qrs.disabled = true;
		if (!localStorage.getItem("useqr"))
			localStorage.setItem("useqr", true);
		if (!$id("postform")) return true;
		$id("formfuncs").insertAdjacentHTML("beforeend", '<span id="qrfunc"> | <a href="javascript:kkqr.openqr();">Quick reply</a></span>');
		if (localStorage.getItem("useqr")=="true") {
			if (localStorage.getItem("alwaysqr")=="true" && !kkqr.closedOnce) {
				kkqr.openqr();
			}
			var qu = $class("qu");
			for (var i=0; i<qu.length; i++) {
				qu[i].addEventListener("click", kkqr._evqr);
			}
		}

		// Only trigger on threads (resto input exists)
		const resto = document.querySelector('input[name="resto"]');
		if (!resto) return;

		kkqr.addScrollListener();

		// Open Quick Reply only when arriving with a q-link (e.g. #q12345)
		if (
			localStorage.getItem("useqr") == "true"
			&& /^#q\d+$/.test(location.hash)
		)
			kkqr.openqr();



		// Trigger ONE sync so comment mirroring picks up whatever the page adds
		setTimeout(() => {
			const com = document.getElementById("com");
			if (!com) return;

			// Fire input event → QR mirrors main form comment automatically
			const ev = new Event("input", { bubbles: true });
			com.dispatchEvent(ev);
		}, 50);

		return true;
	},
	addScrollListener: function() {
		const hash = window.location.hash;
		const match = hash.match(/^#q(\d+)$/);
		if (!match) return;

		const postNum = match[1];

		// Find real post (id pattern *_123)
		const quotedPost = document.querySelector(`[id$="_${postNum}"]`);
		if (quotedPost) {
			quotedPost.scrollIntoView({ behavior: "smooth", block: "center" });
		}
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
	closedOnce: false,
	/* Settings */
	sett: function (tab, div) { if (tab!="general") return;
		div.innerHTML+= '<label><input id="useqr_cb" type="checkbox" onchange="localStorage.setItem(\'useqr\',this.checked);if(!this.checked)setTimeout(()=>kkqr.closeqr(), 0);"'+(localStorage.getItem("useqr")=="true"?'checked="checked"':'')+'>Use quick reply</label>';
		div.innerHTML+= '<label><input type="checkbox" onchange="localStorage.setItem(\'alwaysqr\',this.checked);if(this.checked){localStorage.setItem(\'useqr\',true);document.getElementById(\'useqr_cb\').checked=true;if(!kkqr.win)kkqr.openqr();}"'+(localStorage.getItem("alwaysqr")=="true"?'checked="checked"':'')+'>Persistent quick reply</label>';
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
		else pmstr = "Quick reply";
		if (exist = $kkwm_name(pmstr)) {
			exist.flash();
			return;
		}
		kkqr.win = new kkwmWindow(pmstr, {x: d.clientWidth-pw-40, w: pw, y: d.clientHeight-ph-60, h: ph});
		kkqr.win.onclose = kkqr.closeqr;
		kkqr.qrs.disabled = false;
		var pf = $id("postform");
		with (pf) {
			kkqr.win.div.innerHTML += '<div id="qrcontents"><div id="qrinputs"></div></div>';
			var qr = $id("qrinputs");
			var qrcontents = $id("qrcontents");
			var submitplace = 'qr';
			if (typeof(name)!='undefined') {
				qr.innerHTML+= '<div id="qrnamediv"><input type="text" name="name" id="qrname" value="'+name.value+'" maxlength="100" class="inputtext" placeholder="Name" oninput="kkqr.input(this);"></div>';
				submitplace = 'qrnamediv';
			}
			if (typeof(email)!='undefined') {
				qr.innerHTML+= '<div id="qremaildiv"><input type="text" name="email" id="qremail" value="'+email.value+'" maxlength="100" class="inputtext" placeholder="Email" oninput="kkqr.input(this);"></div>';
				if (!submitplace) submitplace = 'qremaildiv';
			}
			if (typeof(sub)!='undefined') {
				qr.innerHTML+= '<div id="qrsubdiv"><input type="text" name="sub" id="qrsub" value="'+sub.value+'" maxlength="100" class="inputtext" placeholder="Subject" oninput="kkqr.input(this);"></div>';
				submitplace = 'qrsubdiv';
			}
			if (typeof(com) != 'undefined') {
				qr.innerHTML += '<textarea name="com" id="qrcom" cols="48" rows="6" class="inputtext" placeholder="Comment" oninput="kkqr.input(this);">' + com.value + '</textarea>';
			}
			// --- File input ---
			if (typeof(upfile) != 'undefined') {
				// Create a fresh file input for the QR
				const qrUpfile = document.createElement("input");
				qrUpfile.type = "file";
				qrUpfile.multiple = true
				qrUpfile.name = "quickReplyUpFile[]";
				qrUpfile.id = "quickReplyUpFile";
				qrUpfile.className = "inputtext";

				qrcontents.appendChild(qrUpfile);

				// optional: add clear link
				qrcontents.innerHTML += '[<a href="javascript:void(0);" onclick="$id(\'quickReplyUpFile\').value=\'\';">X</a>]<br>';
			}
			if (typeof(noimg) != 'undefined') {
				qrcontents.innerHTML += '<nobr><label>[<input type="checkbox" name="noimg" id="qrnoimg" onclick="$id(\'noimg\').checked=this.checked;"' + (noimg.checked ? ' checked="checked"' : '') + '>No File]</label></nobr> ';
			}
			if (typeof(anigif) != 'undefined') {
				qrcontents.innerHTML += '<nobr><label>[<input type="checkbox" name="anigif" id="qranigif" onclick="$id(\'anigif\').checked=this.checked;"' + (anigif.checked ? ' checked="checked"' : '') + '>Animated GIF]</label></nobr> ';
			}
			if (typeof(category) != 'undefined') {
				qrcontents.innerHTML += '';
			}
			if (typeof(pwd) != 'undefined') {
				qrcontents.innerHTML += '<div><input type="password" name="pwd" id="qrpwd" size="8" value="' + pwd.value + '" class="inputtext" placeholder="Password" oninput="kkqr.input(this);"> <span id="delPasswordInfo">(for deletion)</span></div>';
			}
			if (typeof(captchacode) != 'undefined') {
				qrcontents.innerHTML += '<div id="qrcaptcha" class="postblock"><small> [<a href="#" onclick="(function(){var i=document.getElementById(\'chaimg\'),s=i.src;i.src=s+\'&amp;\';})();">Reload</a>]</small><br><input type="text" name="captchacode" id="qrcaptchacode" value="' + captchacode.value + '" autocomplete="off" class="inputtext" placeholder="Captcha" oninput="kkqr.input(this);"><nobr><small>(Please enter the words. Case-insensitive.)</small></nobr></div>';
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
		var submitbtns = $q("#postform button[name='mode']"), submitqr = $id(submitplace);
		if (!submitqr) {
			console.error(`submitplace '${submitplace}' does not exist. Creating a default container.`);
			$id("qrinputs").insertAdjacentHTML("beforeend", '<div id="qrsubmit"></div>');
			submitqr = $id("qrsubmit"); // Fallback to new container
		}
		
		for (var i=0; i<submitbtns.length; i++) {
			const btn = submitbtns[i];
			const qrBtn = document.createElement("button");
			qrBtn.type = "button"; // prevent automatic form submit
			qrBtn.innerText = btn.innerText;
			qrBtn.onclick = function() {
				// disable the QR button to prevent repeat clicks
				qrBtn.disabled = true;
				window.kkqrLastSubmitButton = qrBtn;

				const qrFile = document.getElementById("quickReplyUpFile");
				let placeholder;

				if (qrFile) {
					// temporarily move QR file into main form
					placeholder = document.createElement("div");
					placeholder.id = "quickReplyUpFile_placeholder";
					qrFile.parentNode.insertBefore(placeholder, qrFile);
					$id("postform").appendChild(qrFile);
				}

				// trigger the real form submit button
				btn.click();

				// move the QR file back to the QR window
				if (qrFile && placeholder) {
					placeholder.parentNode.insertBefore(qrFile, placeholder);
					placeholder.remove();
				}

				// DO NOT close QR window
			};
			submitqr.appendChild(qrBtn);
		}


	},
	closeqr: function () {
		kkqr.qrs.disabled = true;
		kkqr.closedOnce = true;
		if (!kkqr.win) return;

		// Restore the original file input to its place
		const up = $id("upfile");
		if (up) {
			const dummy = $id("upfileDUMMY");
			if (dummy) {
				dummy.insertAdjacentElement("afterend", up);
				$del(dummy);
			}
		}

		// Original main form file stays untouched, no dummy needed
		const chaimg = $id("chaimg");
		if (chaimg) {
			var dummy = $id("chaimgDUMMY");
			if (dummy) {
				dummy.insertAdjacentElement("afterend", chaimg);
				$del(dummy);
			}
		}

		kkqr.win = null;

		const inputs = $q("#postform .inputtext");
		for (var i=0; i<inputs.length; i++) {
			inputs[i].removeEventListener('input', kkqr._evinput2);
		}
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

	/**
	 * Clear Quick Reply fields without touching the main form.
	 * Resets comment, subject, and file input.
	 */
	resetQRFields: function() {
		const qrcom = document.getElementById("qrcom");
		if (qrcom) qrcom.value = "";

		const qrsub = document.getElementById("qrsub");
		if (qrsub) qrsub.value = "";

		const qrFile = document.getElementById("quickReplyUpFile");
		if (qrFile) qrFile.value = "";
	},

};

/* Register */
if(typeof(KOKOJS)!="undefined"){kkjs.modules.push(kkqr);}else{console.log("ERROR: KOKOJS not loaded!\nPlease load 'koko.js' before this script.");}
