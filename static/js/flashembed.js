//chatgpt-sensei & hachichigo
const staticURL = "./static/";

function openFlashEmbedWindow(file, name, w, h) {
    if (!document.getElementById("swfWindow")) {

        const darkenoverlay = document.createElement("div");
        darkenoverlay.id = "darken-embed-screen";
        document.body.appendChild(darkenoverlay);

        const swfWindow = document.createElement("div");
        swfWindow.id = "swfWindow";

        let Winw = w;
        let Winh = h;

        if (window.innerHeight < h || window.innerWidth < w) {
            Winh = Math.round(0.8 * window.innerHeight);
            Winw = Math.round(0.8 * window.innerWidth);
        }

        swfWindow.style.width = `${Winw - 26}px`;
        swfWindow.style.height = `${Winh}px`;

        // Enable resizing for the window and hide scrollbars
        swfWindow.style.resize = "both";
        swfWindow.style.overflow = "hidden";  // Hide scrollbars

        swfWindow.innerHTML = `
            <div id="swf-embed-header" style="cursor: move;">
                <img src="${staticURL}image/cross2embed.png?v1" id="closeButton" style="float: right; cursor: pointer;">
                <div id="embed-swf-details">${name}, ${w}x${h} <a href="${file}" download="${name}" id="downloadButton"><div class="download"></div></a></div>
            </div>
            <div id="ruffleContainer" style="width: 100%; height: calc(100% - 20px);"></div>
        `;

        document.body.appendChild(swfWindow);

        const container = document.getElementById('ruffleContainer');

        if (container) {
            // Load and resize the flash player inside the container
            const ruffle = window.RufflePlayer.newest();
            const rufflePlayer = ruffle.createPlayer();

            container.appendChild(rufflePlayer);

            rufflePlayer.load(file).then(() => {
                console.log("Flash file loaded successfully");
            }).catch((err) => {
                console.error("Failed to load Flash file:", err);
            });

            // Resize the flash player along with the container
            const resizeObserver = new ResizeObserver(() => {
                const newWidth = container.offsetWidth;
                const newHeight = container.offsetHeight;
                rufflePlayer.style.width = `${newWidth}px`;
                rufflePlayer.style.height = `${newHeight}px`;
            });
            resizeObserver.observe(container);
        }

        // Add drag functionality to move the window
        makeElementDraggable(swfWindow, document.getElementById('swf-embed-header'));

        // Disable default action for close and download buttons during drag with threshold
        disableButtonActionsDuringDrag(document.getElementById('closeButton'), closeSWFWindow);
        disableButtonActionsDuringDrag(document.getElementById('downloadButton'));
    }
}

function closeSWFWindow() {
    const swfWindow = document.getElementById("swfWindow");
    const embedOverlay = document.getElementById("darken-embed-screen");
    if (swfWindow || embedOverlay) {
        swfWindow.remove();
        embedOverlay.remove();
    }
}

// Function to make the window draggable
function makeElementDraggable(element, handle) {
    let pos1 = 0, pos2 = 0, pos3 = 0, pos4 = 0;
    let isDragging = false;

    handle.onmousedown = dragMouseDown;

    function dragMouseDown(e) {
        e.preventDefault();
        pos3 = e.clientX;
        pos4 = e.clientY;
        isDragging = false;

        document.onmouseup = closeDragElement;
        document.onmousemove = elementDrag;
    }

    function elementDrag(e) {
        e.preventDefault();
        pos1 = pos3 - e.clientX;
        pos2 = pos4 - e.clientY;
        pos3 = e.clientX;
        pos4 = e.clientY;
        element.style.top = (element.offsetTop - pos2) + "px";
        element.style.left = (element.offsetLeft - pos1) + "px";
        isDragging = true;
        element.style.cursor = "move";
    }

    function closeDragElement() {
        document.onmouseup = null;
        document.onmousemove = null;
        element.style.cursor = "default";
    }
}

// Function to disable button actions during drag with a threshold
function disableButtonActionsDuringDrag(button, action, threshold = 20) {
    let isDragging = false;
    let startX = 0;
    let startY = 0;

    button.onmousedown = (e) => {
        e.stopPropagation();
        isDragging = false;
        startX = e.clientX;
        startY = e.clientY;

        document.onmousemove = (moveEvent) => {
            const distanceX = Math.abs(moveEvent.clientX - startX);
            const distanceY = Math.abs(moveEvent.clientY - startY);

            if (distanceX > threshold || distanceY > threshold) {
                isDragging = true;
            }
        };
    };

    button.onmouseup = (e) => {
        document.onmousemove = null;
        const distanceX = Math.abs(e.clientX - startX);
        const distanceY = Math.abs(e.clientY - startY);

        // Trigger action if it's not a drag or drag distance is within the threshold
        if (!isDragging || (distanceX <= threshold && distanceY <= threshold)) {
            if (action) {
                action();
            }
        }
        isDragging = false;
    };
}
