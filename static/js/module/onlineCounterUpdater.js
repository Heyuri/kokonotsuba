const kkUserUpdate = {  name: "KK online user updating",
	intervalId: null,
	elementId: null,
	countid: null,
	minutes: 0,

	// Startup function to initialize the reloader
	startup: function () {
		this.elementId = 'usercounter';
		this.countid = 'countnumber'
		const element = document.getElementById(this.elementId);
		if (!element) {
			return true;
		}

		this.reloadElement();
		this.startInterval();
		return true;
	},

	startInterval: function () {
		// data-timeout comes from board config, so treat it as untrusted: a missing, empty,
		// zero or garbage value would make setInterval fire with a 0ms delay — a fetch storm
		// that pegs the CPU and queues requests faster than they complete, growing memory
		// without bound on any tab left open. Clamp to at least 1 minute, default 10.
		this.minutes = parseInt(document.getElementById(this.elementId).dataset.timeout, 10);
		if (!Number.isFinite(this.minutes) || this.minutes < 1) this.minutes = 10;
		const milliseconds = this.minutes * 60 * 1000;
		this.intervalId = setInterval(() => {
			// don't poll while the tab is hidden or the network is down
			if (!document.hidden && navigator.onLine !== false) this.reloadElement();
		}, milliseconds);
	},

	reloadElement: async function () {
		try {
			let url = document.getElementById(this.elementId).dataset.modurl;
			const response = await fetch(url);
		
			if (!response.ok) {
				throw new Error(`HTTP error! status: ${response.status}`);
			}
		
			const data = await response.json();
		
			// Check for success and update with active_users from the response
			if (data.success && typeof data.active_users !== 'undefined') {
				let onlinecounterelement = document.getElementById(this.countid);
				onlinecounterelement.innerHTML = data.active_users;
			} else {
				console.warn('Unexpected response format or unsuccessful request:', data);
			}
		} catch (error) {
			console.error('Error fetching data:', error);
		}
	},

	reset: function () {
		if (this.intervalId !== null) {
			clearInterval(this.intervalId);
			this.intervalId = null;
		}
	},
};


/* Register */
if(typeof(KOKOJS)!="undefined"){kkjs.modules.push(kkUserUpdate);}else{console.log("ERROR: KOKOJS not loaded!\nPlease load 'koko.js' before this script.");}
