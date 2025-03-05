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

        let url = new URL(this.originalSrc);
        url.searchParams.set('t', new Date().getTime());
        bannerElement.src = url.href;

        bannerElement.onload = bannerElement.onerror = () => {
            bannerElement.style.opacity = "1";
            this.isChanging = false; // Reset debounce flag
        };
    },
};

/* Register */
if (typeof (KOKOJS) != "undefined") {
    kkjs.modules.push(kkBannerSwitch);
} else {
    console.log("ERROR: KOKOJS not loaded!\nPlease load 'koko.js' before this script.");
}
