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

		// Remove 'hidden' class from <li id="counterListItemJS"> and add 'hidden' class to <li id="counterListItemNoJS">
		const jsListItem = document.getElementById('counterListItemJS');
		const noJsListItem = document.getElementById('counterListItemNoJS');

		if (jsListItem) {
			jsListItem.classList.remove('hidden');
		}
		if (noJsListItem) {
			noJsListItem.classList.add('hidden');
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

	reloadElement: async function () {
		try {
			let url = document.getElementById(this.elementId).dataset.modurl;
			const response = await fetch(url);
		
			if (!response.ok) {
				throw new Error(`HTTP error! status: ${response.status}`);
			}
		
			const data = await response.json();
		
			if (data !== 'undefined') {
				let onlinecounterelement = document.getElementById(this.countid);
				onlinecounterelement.innerHTML = data;
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
