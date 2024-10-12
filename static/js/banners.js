// Configuration Variables
var scriptUrl = document.currentScript.src; // Get the current script's URL
const baseUrl = scriptUrl.substring(0, scriptUrl.lastIndexOf('/')).replace('/js', '/image/banner/banner'); // Construct the base URL for banners
var totalBanners = 5;       // Total number of possible banners (e.g., banner001 to banner050). Set a little higher if you don't want to bother updating later.
var maxAttempts = 9001;        // Number of banner numbers to try before stopping. Probably doesn't need to be changed.

// Cache to store checked URLs
const cache = {};

// Function to check if a file exists using HTTP headers
async function fileExists(url) {
    if (cache[url] !== undefined) return cache[url];
    try {
        const response = await fetch(url, { method: 'HEAD' });
        cache[url] = response.ok;
        return response.ok;
    } catch (err) {
        cache[url] = false;
        return false;
    }
}

// Function to get a random image URL
async function getRandomImageUrl() {
    let attempts = 0;
    while (attempts < maxAttempts) {
        let randomNum = Math.floor(Math.random() * totalBanners) + 1;
        let paddedNum = String(randomNum).padStart(3, '0');
        // Check for file existence in the order: png, gif, jpg
        for (let ext of ['png', 'gif', 'jpg']) {
            let filename = `${baseUrl}${paddedNum}.${ext}`; // Base URL + padded number + extension
            if (await fileExists(filename)) {
                return filename;
            }
        }
        attempts++;
    }
    return null;
}

// Debounce flag
let isChanging = false;

// Function to display a random image
async function getRandomImage() {
    let imgUrl = await getRandomImageUrl();
    if (imgUrl) {
        document.getElementById("bannerContainer").innerHTML =
            '<img border="1" src="' + imgUrl + '" id="banner" style="max-width: 300px;" title="Click to change" onclick="change()" />';
    } else {
        document.getElementById("bannerContainer").innerHTML = 'No banners available. ;_;';
    }
}

// Function to change the displayed image when clicked
async function change() {
    if (isChanging) return; // Prevent multiple simultaneous clicks
    isChanging = true;
    const banner = document.getElementById("banner");
    banner.style.opacity = "0.5";  // Set opacity to 0.5
    let imgUrl = await getRandomImageUrl();
    if (imgUrl) {
        banner.src = imgUrl;
        banner.onload = () => {
            banner.style.opacity = "1";  // Reset opacity once loaded
            isChanging = false; // Reset debounce flag
        };
    } else {
        isChanging = false; // Reset debounce flag even if no image is found
    }
}

// Display a random image on page load
getRandomImage();
