//chatgpt-sensei & hachichigo

const staticURL = "https://yuisuki.net/flashtemp/kokonotsuba-portable/static/";

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

		swfWindow.style.width = `${Winw}px`;
		swfWindow.style.height = `${Winh}px`;

		swfWindow.innerHTML = `
			<div id="swf-embed-header">
			<img src="${staticURL}image/cross2embed.png" id="closeButton" onclick="closeSWFWindow()" style="float: right; cursor: pointer;">
			<div id="embed-swf-details">  ${name},  ${w}x${h}</div>
			</div>
			<div id="ruffleContainer"></div>
		`;

		document.body.appendChild(swfWindow);

		const container = document.getElementById('ruffleContainer');

		if (container) {
			container.style.width = `${Winw}px`;
			container.style.height = `${Winh}px`;

			const ruffle = window.RufflePlayer.newest();
			const rufflePlayer = ruffle.createPlayer();

			rufflePlayer.style.width = `${Winw}px`;
			rufflePlayer.style.height = `${Winh}px`;

			container.appendChild(rufflePlayer);

			rufflePlayer.load(file).then(() => {
				console.log("Flash file loaded successfully");
			}).catch((err) => {
				console.error("Failed to load Flash file:", err);
			});
		}
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
