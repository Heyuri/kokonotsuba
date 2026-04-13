/* LOL HEYURI
 */

(function() {
	var s = document.createElement('style');
	s.id = 'qrs';
	document.head.appendChild(s);
})();
window.kkqrLastSubmitButton = null;

function _qrEsc(s) {
	return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

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
	removeFixedButton: function() {
		if (kkqr._fixedBtnObserver) {
			kkqr._fixedBtnObserver.disconnect();
			kkqr._fixedBtnObserver = null;
		}
		if (kkqr._fixedBtn) {
			kkqr._fixedBtn.remove();
			kkqr._fixedBtn = null;
		}
	},
	reset: function () {
		if (!$id("postform")) return true;
		kkqr.closeqr();
		kkqr.removeFixedButton();
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
		var pel = pf.elements;
		kkqr.win.div.innerHTML += '<div id="qrcontents"><div id="qrinputs"></div></div>';
		var qr = $id("qrinputs");
		var qrcontents = $id("qrcontents");
		var submitplace = 'qr';
		if (pel['name']) {
			qr.innerHTML+= '<div id="qrnamediv"><input type="text" name="name" id="qrname" value="'+_qrEsc(pel['name'].value)+'" maxlength="100" class="inputtext" placeholder="Name" oninput="kkqr.input(this);"></div>';
			submitplace = 'qrnamediv';
		}
		if (pel['email']) {
			qr.innerHTML+= '<div id="qremaildiv"><input type="text" name="email" id="qremail" value="'+_qrEsc(pel['email'].value)+'" maxlength="100" class="inputtext" placeholder="Email" oninput="kkqr.input(this);"></div>';
			if (!submitplace) submitplace = 'qremaildiv';
		}
		if (pel['sub']) {
			qr.innerHTML+= '<div id="qrsubdiv"><input type="text" name="sub" id="qrsub" value="'+_qrEsc(pel['sub'].value)+'" maxlength="100" class="inputtext" placeholder="Subject" oninput="kkqr.input(this);"></div>';
			submitplace = 'qrsubdiv';
		}
		if (pel['com']) {
			qr.innerHTML += '<textarea name="com" id="qrcom" cols="48" rows="6" class="inputtext" placeholder="Comment" oninput="kkqr.input(this);">' + _qrEsc(pel['com'].value) + '</textarea>';
		}
		// --- File input ---
		if (pel['upfile[]']) {
			var qrFileWrap = document.createElement('div');
			qrFileWrap.id = 'qrFileWrap';

			// Clone the dropzone template if multi-attach is enabled, otherwise fall back to plain input
			var dzTemplate = document.getElementById('dropzoneTemplate');
			var attachLimit = dzTemplate ? parseInt(dzTemplate.dataset.attachmentLimit, 10) : 0;
			if (dzTemplate && attachLimit > 1 && window.clipboardWireDropzone) {
				var dzClone = dzTemplate.content.cloneNode(true);
				var dzWrap = dzClone.querySelector('.dropzoneWrap');
				qrFileWrap.appendChild(dzClone);
				qrcontents.appendChild(qrFileWrap);
				// Must wire AFTER it's in the DOM
				window.clipboardWireDropzone(dzWrap);
			} else {
				var qrUpfile = document.createElement('input');
				qrUpfile.type = 'file';
				qrUpfile.multiple = true;
				qrUpfile.id = 'quickReplyUpFile';
				qrUpfile.className = 'inputtext';
				qrFileWrap.appendChild(qrUpfile);
				qrcontents.appendChild(qrFileWrap);

				qrUpfile.addEventListener('change', function() {
					if (!qrUpfile.files || !qrUpfile.files.length) return;
					var picked = Array.prototype.slice.call(qrUpfile.files);
					qrUpfile.value = '';
					if (window.clipboardAddFile) {
						for (var i = 0; i < picked.length; i++) {
							if (window.clipboardCanAdd && !window.clipboardCanAdd()) break;
							window.clipboardAddFile(picked[i], picked[i].name);
						}
					}
				});
			}

			// Container for clipboard.js previews inside QR
			var qrPreviewTarget = document.createElement('div');
			qrPreviewTarget.id = 'qrPreviewTarget';
			qrFileWrap.appendChild(qrPreviewTarget);

			// Redirect clipboard.js preview rendering into QR
			if (window.clipboardSetRenderTarget) {
				window.clipboardSetRenderTarget(qrPreviewTarget);
			}
		}
		if (pel['noimg']) {
			qrcontents.insertAdjacentHTML('beforeend', '<nobr><label>[<input type="checkbox" name="noimg" id="qrnoimg" onclick="$id(\'noimg\').checked=this.checked;"' + (pel['noimg'].checked ? ' checked="checked"' : '') + '>No File]</label></nobr> ');
		}
		if (pel['anigif']) {
			qrcontents.insertAdjacentHTML('beforeend', '<nobr><label>[<input type="checkbox" name="anigif" id="qranigif" onclick="$id(\'anigif\').checked=this.checked;"' + (pel['anigif'].checked ? ' checked="checked"' : '') + '>Animated GIF]</label></nobr> ');
		}
		if (pel['category']) {
			// reserved
		}
		if (pel['pwd']) {
			qrcontents.insertAdjacentHTML('beforeend', '<div><input type="password" name="pwd" id="qrpwd" size="8" value="' + _qrEsc(pel['pwd'].value) + '" class="inputtext" placeholder="Password" oninput="kkqr.input(this);"> <span id="delPasswordInfo">(for deletion)</span></div>');
		}
		if (pel['captchacode']) {
			qrcontents.insertAdjacentHTML('beforeend', '<div id="qrcaptcha" class="postblock"><small> [<a href="#" onclick="(function(){var i=document.getElementById(\'chaimg\'),s=i.src;i.src=s+\'&amp;\';})();">Reload</a>]</small><br><input type="text" name="captchacode" id="qrcaptchacode" value="' + _qrEsc(pel['captchacode'].value) + '" autocomplete="off" class="inputtext" placeholder="Captcha" oninput="kkqr.input(this);"><nobr><small>(Please enter the words. Case-insensitive.)</small></nobr></div>');
			var qrc = $id("qrcaptcha"), chaimg = $id("chaimg");
			chaimg.insertAdjacentHTML("beforebegin", '<span id="chaimgDUMMY"></span>');
			qrc.insertAdjacentElement("afterbegin", chaimg);
		}
		kkqr.win.div.style.height = "";
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

				// trigger the real form submit button
				btn.click();
			};
			submitqr.appendChild(qrBtn);
		}


	},
	closeqr: function () {
		kkqr.qrs.disabled = true;
		kkqr.closedOnce = true;
		if (!kkqr.win) return;

		// Return clipboard.js preview rendering to default location
		if (window.clipboardSetRenderTarget) {
			window.clipboardSetRenderTarget(null);
		}

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

		if (window.resetClipboardFiles) window.resetClipboardFiles();
		var qrFile = document.getElementById("quickReplyUpFile");
		if (qrFile) qrFile.value = "";
	},

};

/* Register */
if(typeof(KOKOJS)!="undefined"){kkjs.modules.push(kkqr);}else{console.log("ERROR: KOKOJS not loaded!\nPlease load 'koko.js' before this script.");}
