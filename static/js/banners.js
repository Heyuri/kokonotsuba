const kkBannerSwitch = {
	name: "Banner switcher",
	elementId: 'banner',
	originalSrc: '', // To store the original PHP URL

	startup: function() {
		const bannerElement = document.getElementById(this.elementId);

		if (!bannerElement) {
			console.log("ERROR: Banner element not found.");
			return false;
		}

		// Store the original src attribute value
		this.originalSrc = bannerElement.src;

		this.addClickListener();
		return true;
	},

	addClickListener: function() {
		const bannerElement = document.getElementById(this.elementId);
		bannerElement.addEventListener('click', () => {
			this.reloadImage();
		});
	},

	reloadImage: async function() {
		const bannerElement = document.getElementById(this.elementId);
		if (!bannerElement) {
			console.log("ERROR: Banner element not found.");
			return;
		}

		try {
			// Fetch a new image from the original PHP URL
			const response = await fetch(this.originalSrc, {
				method: 'GET',
				mode: 'cors',
				cache: 'no-cache'
			});

			if (!response.ok) {
				throw new Error('Network response was not ok: ' + response.statusText);
			}

			// Create a Blob from the response
			const blob = await response.blob();
			const newImageUrl = URL.createObjectURL(blob);

			// Update the src of the img element
			bannerElement.src = newImageUrl;

		} catch (error) {
			console.error('There has been a problem with your fetch operation:', error);
		}
	},

};

/* Register */
if(typeof(KOKOJS)!="undefined"){kkjs.modules.push(kkBannerSwitch);}else{console.log("ERROR: KOKOJS not loaded!\nPlease load 'koko.js' before this script.");}
