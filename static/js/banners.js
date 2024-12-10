const kkBannerSwitch = {
    name: "Banner switcher",
    elementId: 'banner',
    originalSrc: '', // To store the original PHP URL
    isChanging: false, // Debounce flag to prevent multiple simultaneous clicks

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
        if (this.isChanging) return; // Prevent multiple simultaneous clicks
        this.isChanging = true;

        const bannerElement = document.getElementById(this.elementId);
        if (!bannerElement) {
            console.log("ERROR: Banner element not found.");
            this.isChanging = false;
            return;
        }

        // Apply greying-out effect
        bannerElement.style.opacity = "0.5";

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

            // Reset greying-out effect after the image is loaded
            bannerElement.onload = () => {
                bannerElement.style.opacity = "1";
                this.isChanging = false; // Reset debounce flag
            };

        } catch (error) {
            console.error('There has been a problem with your fetch operation:', error);
            this.isChanging = false; // Reset debounce flag even if an error occurs
            bannerElement.style.opacity = "1"; // Reset opacity in case of failure
        }
    },
};

/* Register */
if (typeof (KOKOJS) != "undefined") {
    kkjs.modules.push(kkBannerSwitch);
} else {
    console.log("ERROR: KOKOJS not loaded!\nPlease load 'koko.js' before this script.");
}
