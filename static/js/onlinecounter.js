const kkUserUpdate = {  name: "KK online user updating",
	intervalId: null,
	elementId: null,
	minutes: 0,

	// Startup function to initialize the reloader
	startup: function () {
		this.elementId = 'usercounter';
		const element = document.getElementById(this.elementId);
		if (!element) {
			return true;
		}
		this.reloadElement();
		this.startInterval();
		return true;
	},

	startInterval: function () {
		this.minutes = document.getElementById(this.elementId).dataset.timeout;
		const milliseconds = this.minutes * 60 * 1000;
		this.intervalId = setInterval(() => {
			this.reloadElement();
		}, milliseconds);
	},

	reloadElement: function () {
		const element = document.getElementById(this.elementId);
		if (!element) {
			console.log("ERROR: Online user counter element not found.");
			return;
		}

		fetch(window.location.href)
		.then(response => response.text())
		.then(html => {

			const parser = new DOMParser();
			const doc = parser.parseFromString(html, 'text/html');
			const newElement = doc.getElementById(this.elementId);

			if (newElement) {
				element.innerHTML = newElement.innerHTML;
			} else {
				console.log("ERROR: Updated element not found in the fetched HTML.");
			}
		})
		.catch(error => {
			console.error("Error reloading element:", error);
		});
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
