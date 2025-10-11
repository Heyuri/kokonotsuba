//chatgpt-sensei & hachichigo
var scriptUrl = document.currentScript.src; // Get the current script's URL
const closebuttan = scriptUrl.substring(0, scriptUrl.lastIndexOf('/')).replace('/js', '/image/cross2embed.png'); // Construct the URL for close button

function openFlashEmbedWindow(file, name, extension, w, h) {
	if (!document.getElementById("swfWindow")) {
		const darkenoverlay = document.createElement("div");
		darkenoverlay.id = "darken-embed-screen";
		document.body.appendChild(darkenoverlay);

		const swfWindow = document.createElement("div");
		swfWindow.id = "swfWindow";

		// New 95%-max sizing logic
		const maxWidth  = window.innerWidth  * 0.95;
		const maxHeight = window.innerHeight * 0.95;
		const aspect    = w / h;

		let Winw = w;
		let Winh = h;

		if (Winw > maxWidth) {
			Winw = maxWidth;
			Winh = Winw / aspect;
		}

		if (Winh > maxHeight) {
			Winh = maxHeight;
			Winw = Winh * aspect;
		}

		swfWindow.style.width  = `${Winw}px`;
		swfWindow.style.height = `${Winh + 20}px`;

		// Enable resizing for the window and hide scrollbars
		swfWindow.style.resize  = "both";
		swfWindow.style.overflow = "hidden";

		swfWindow.innerHTML = `
			<div id="swf-embed-header" style="cursor: move;">
				<img src="${closebuttan}" id="closeButton" style="float: right; cursor: pointer;">
				<div id="embed-swf-details">${name}, ${w}x${h} <a href="${file}" download="${name}${extension}" id="downloadButton"><div class="download"></div></a></div>
			</div>
			<div id="ruffleContainer" style="width: 100%; height: calc(100% - 20px); position: relative;"></div>
		`;

		document.body.appendChild(swfWindow);

		const container = document.getElementById('ruffleContainer');

		if (container) {
			// Load and resize the flash player inside the container
			const ruffle        = window.RufflePlayer.newest();
			const rufflePlayer  = ruffle.createPlayer();
			container.appendChild(rufflePlayer);

			// --- ADD LOADER UI ---
			const overlay = document.createElement("div");
			overlay.id = "ruffleLoadingOverlay";
			overlay.textContent = "Loading 0%";
			container.appendChild(overlay);

			function setStatus(msg) {
				overlay.textContent = msg;
			}
			function hideStatus() {
				overlay.remove();
			}

			// Hide player until fully loaded
			rufflePlayer.style.visibility = "hidden";

			// --- Streamed fetch for progress display ---
			async function streamedFetch(url) {
				setStatus("Loading 0%");
				const resp = await fetch(url);
				const totalBytes = +resp.headers.get("Content-Length") || 0;
				const reader = resp.body.getReader();
				let loadedBytes = 0;
				const chunks = [];

				while (true) {
					const { done, value } = await reader.read();
					if (done) break;
					chunks.push(value);
					loadedBytes += value.byteLength;
					if (totalBytes) {
						const percent = Math.min(100, Math.floor((loadedBytes / totalBytes) * 100));
						setStatus(`Loading ${percent}%`);
					}
				}
				setStatus("Preparing…");
				const blob = new Blob(chunks);
				return blob.arrayBuffer();
			}

			// --- Load SWF data with streaming loader ---
			(async () => {
				try {
					const data = await streamedFetch(file);
					const settings = { autoplay: true, letterbox: "on", data: new Uint8Array(data) };
					setStatus("Loading Ruffle…");
					await rufflePlayer.load(settings);
					hideStatus();
					rufflePlayer.style.visibility = "visible";
					console.log("Flash file loaded successfully");
				} catch (err) {
					setStatus("Failed to load file");
					console.error("Failed to load Flash file:", err);
				}
			})();

			// Resize the flash player along with the container
			const resizeObserver = new ResizeObserver(() => {
				const newWidth  = container.offsetWidth;
				const newHeight = container.offsetHeight;
				rufflePlayer.style.width  = `${newWidth}px`;
				rufflePlayer.style.height = `${newHeight}px`;
			});
			resizeObserver.observe(container);

			// Save reference for cleanup
			rufflePlayer._resizeObserver = resizeObserver;
		}

		// Add drag functionality to move the window
		makeElementDraggable(swfWindow, document.getElementById('swf-embed-header'));

		// Disable default action for close and download buttons during drag with threshold
		disableButtonActionsDuringDrag(document.getElementById('closeButton'), closeSWFWindow);
		disableButtonActionsDuringDrag(document.getElementById('downloadButton'));
	}
}

function closeSWFWindow() {
	const swfWindow    = document.getElementById("swfWindow");
	const embedOverlay = document.getElementById("darken-embed-screen");

	// Cleanup ResizeObserver if it exists
	const container = document.getElementById("ruffleContainer");
	const player = container?.firstChild;
	if (player?._resizeObserver) {
		player._resizeObserver.disconnect();
		delete player._resizeObserver;
	}

	if (swfWindow || embedOverlay) {
		swfWindow?.remove();
		embedOverlay?.remove();
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

		document.onmouseup   = closeDragElement;
		document.onmousemove = elementDrag;
	}

	function elementDrag(e) {
		e.preventDefault();
		pos1 = pos3 - e.clientX;
		pos2 = pos4 - e.clientY;
		pos3 = e.clientX;
		pos4 = e.clientY;
		element.style.top  = (element.offsetTop  - pos2) + "px";
		element.style.left = (element.offsetLeft - pos1) + "px";
		isDragging = true;
		element.style.cursor = "move";
	}

	function closeDragElement() {
		document.onmouseup   = null;
		document.onmousemove = null;
		element.style.cursor = "default";
	}
}

// Function to disable button actions during drag with a threshold
function disableButtonActionsDuringDrag(button, action, threshold = 20) {
	let isDragging = false;
	let startX     = 0;
	let startY     = 0;

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

// fix filenames with apostrophes in inline onclick
window.addEventListener("DOMContentLoaded", () => {
	// Find every <a class="flashboardEmbedText" onclick="openFlashEmbedWindow('…','…','…',w,h)">
	const embedLinks = document.querySelectorAll(".flashboardEmbedText");
	embedLinks.forEach((el) => {
		const rawOnclick = el.getAttribute("onclick");
		if (!rawOnclick) return;

		// Match: openFlashEmbedWindow('FILE_URL', 'ESCAPED_FILE_NAME', 'EXTENSION', WIDTH, HEIGHT)
		const re = /openFlashEmbedWindow\(\s*'(.+?)'\s*,\s*'(.+?)'\s*,\s*'(.+?)'\s*,\s*(\d+)\s*,\s*(\d+)\s*\)/;
		const match = rawOnclick.match(re);
		if (!match) return;

		let [_, fileUrl, rawName, ext, w, h] = match;

		// Decode any HTML‐escaped apostrophes (&#039;) into actual apostrophes
		const decodedName = rawName.replace(/&#039;/g, "'");

		// Remove the broken inline onclick
		el.removeAttribute("onclick");

		// Add a proper click handler that calls openFlashEmbedWindow(...) with the correct arguments
		el.addEventListener("click", (evt) => {
			evt.preventDefault();
			openFlashEmbedWindow(fileUrl, decodedName, ext, parseInt(w, 10), parseInt(h, 10));
		});
	});
});
