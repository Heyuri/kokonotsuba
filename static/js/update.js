/* LOL HEYURI
 */

/* Module */
const kkupdate = { name: "KK Thread Updating",
	total: 0,
	startup: function () {
		if (!_kkSetting("update")) {
			return true;
		}
		if (!document.postform) {return true;}
		if (!document.postform.resto) {return true;}
		var controls = document.createElement("div");
		controls.id = "controls";
		controls.classList.add("threadUpdater");
		document.querySelector(".threadRear").appendChild(controls);
		controls.innerHTML += "[<a onclick=\"kkupdate.update();return false;\" href=\"\">Update</a>] [<label><input onchange=\"kkupdate.toggleAuto();\" checked type=\"checkbox\">Auto</label>] <span id=\"update-status\"></span>";
		// Throttle the scroll handler to one layout read per animation frame
		// to avoid forcing a reflow on every scroll event.
		var scrollTicking = false;
		document.addEventListener("scroll", function () {
			if (scrollTicking) return;
			scrollTicking = true;
			requestAnimationFrame(function () {
				scrollTicking = false;
				var de = document.documentElement;
				if ((window.innerHeight + de.scrollTop) >= (de.scrollHeight - 2)) {
					kkupdate.total = 0;
					kkTitle.set('updater', 0);
				}
			});
		}, { passive: true });
		kkupdate.toggleAuto();
		return true;
	},
	reset: function () {
		document.getElementById("controls").remove();
	},
	auto: null,
	inc: [5,10,30,60,120,180],
	inci: 0,
	timer: 0,
	update: function () {
		var statusEl = document.querySelector("#update-status");
		if (statusEl) statusEl.innerText = "Updating...";

		// fetchNewReplies (updateThread.js) pulls only the posts newer than what's on the
		// page via the post API and appends them; we just reflect the result in the UI.
		fetchNewReplies().then(function (inserted) {
			var npc = inserted.length;
			if (npc === 0) {
				// Nothing new — lengthen the auto-update interval (back off).
				kkupdate.inci++;
				if (kkupdate.inci >= kkupdate.inc.length) kkupdate.inci--;
				if (statusEl) statusEl.innerText = "No new posts";
				return;
			}
			kkupdate.total += npc;
			kkupdate.inci = 0;
			if (statusEl) statusEl.innerText = npc + " new post" + (npc > 1 ? "s" : "");
			kkTitle.set('updater', kkupdate.total);
		}).catch(function (err) {
			if (err === 'pruned') {
				// Thread is gone — stop auto-updating entirely.
				if (statusEl) statusEl.innerText = "This thread has been pruned or deleted";
				var input = document.querySelector("#controls input");
				if (input) input.disabled = true;
				var a = document.querySelector("#controls a");
				if (a) a.onclick = function () { return false; };
				if (kkupdate.auto) {
					clearInterval(kkupdate.auto);
					kkupdate.auto = null;
				}
				return;
			}
			// Transient network/parse error: keep the updater running, just clear the status.
			if (statusEl) statusEl.innerText = "";
		});
	},
	toggleAuto: function () {
		if (kkupdate.auto) {
			clearInterval(kkupdate.auto);
			kkupdate.inci = 0;
			kkupdate.timer = 0;
			document.querySelector("#update-status").innerText = "";
			kkupdate.auto = null;
		} else {
			kkupdate.inci = 0;
			kkupdate.timer = kkupdate.inc[kkupdate.inci];
			kkupdate._timer();
			kkupdate.auto = setInterval(kkupdate._timer, 1000);
		}
	},
	_timer: function () {
		if (kkupdate.timer <= 0) {
			clearInterval(kkupdate.auto);
			kkupdate.update(true);
			kkupdate.timer = kkupdate.inc[kkupdate.inci];
			kkupdate.auto = setInterval(kkupdate._timer, 1000);
		}
		document.querySelector("#update-status").innerText = kkupdate.timer;
		kkupdate.timer -= 1;
	},
};

/* Register */
if(typeof(KOKOJS)!="undefined"){
	kkjs.modules.push(kkupdate);
	kkSetting.add({ key: "update", label: "Thread updater", onChange: function () {
		kkupdate.reset();
		kkupdate.startup();
	} }, "Browsing");
}else{console.log("ERROR: KOKOJS not loaded!\nPlease load 'koko.js' before this script.");}
