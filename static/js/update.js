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
		// Earlier pages of a paginated thread are a fixed slice of old replies — there's
		// nothing newer to show there, and updating would wrongly pull later pages' posts
		// onto this one. Only the last page auto-updates. (_twOnLastThreadPage, updateThread.js)
		if (typeof _twOnLastThreadPage === "function" && !_twOnLastThreadPage()) {return true;}
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
	hold: 0,
	// True while a fetch is in flight, so neither the timer nor the manual [Update] link
	// can stack a second request on top of a slow or hung one.
	inflight: false,
	// Lengthen the auto-update interval one step (shared by "no new posts" and errors).
	backoff: function () {
		kkupdate.inci++;
		if (kkupdate.inci >= kkupdate.inc.length) kkupdate.inci--;
	},
	update: function () {
		if (kkupdate.inflight) return;

		var statusEl = document.querySelector("#update-status");

		// Offline (e.g. the connection dropped overnight): a fetch can only fail, so skip
		// it and back off instead of hammering a dead connection every few seconds forever.
		if (navigator.onLine === false) {
			kkupdate.backoff();
			if (statusEl) statusEl.innerText = "";
			kkupdate.hold = 0;
			return;
		}

		kkupdate.inflight = true;
		if (statusEl) statusEl.innerText = "Updating...";
		// Suppress the countdown display while the fetch is in flight.
		kkupdate.hold = -1;

		// fetchNewReplies (updateThread.js) pulls only the posts newer than what's on the
		// page via the post API and appends them; we just reflect the result in the UI.
		fetchNewReplies().then(function (inserted) {
			kkupdate.inflight = false;
			var npc = inserted.length;
			if (npc === 0) {
				// Nothing new — lengthen the auto-update interval (back off).
				kkupdate.backoff();
				if (statusEl) statusEl.innerText = "No new posts";
				// Keep the status visible for one tick before the countdown resumes.
				kkupdate.hold = 1;
				return;
			}
			kkupdate.total += npc;
			kkupdate.inci = 0;
			if (statusEl) statusEl.innerText = npc + " new post" + (npc > 1 ? "s" : "");
			kkupdate.hold = 1;
			kkTitle.set('updater', kkupdate.total);
		}).catch(function (err) {
			kkupdate.inflight = false;
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
			// Transient network/parse error: keep the updater running, but back off like
			// "no new posts" does — without this, errors retried at the shortest interval
			// forever (e.g. with the network down all night).
			kkupdate.backoff();
			if (statusEl) statusEl.innerText = "";
			kkupdate.hold = 0;
		});
	},
	toggleAuto: function () {
		if (kkupdate.auto) {
			clearInterval(kkupdate.auto);
			kkupdate.inci = 0;
			kkupdate.timer = 0;
			kkupdate.hold = 0;
			document.querySelector("#update-status").innerText = "";
			kkupdate.auto = null;
		} else {
			kkupdate.inci = 0;
			kkupdate.timer = kkupdate.inc[kkupdate.inci];
			kkupdate.hold = 0;
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
		if (kkupdate.hold > 0) {
			// Hold the last status message for a tick instead of overwriting it.
			kkupdate.hold -= 1;
		} else if (kkupdate.hold === 0) {
			document.querySelector("#update-status").innerText = kkupdate.timer;
		}
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
