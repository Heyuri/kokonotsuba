/* 
 * LOL HEYURI
 */

(function() {
	'use strict';

	window.attachmentExpander = window.attachmentExpander || {};
	var attachmentExpander = window.attachmentExpander;

	attachmentExpander.imageExtensions = Array("png","jpg","jpeg","gif","giff","bmp","jfif");
	attachmentExpander.videoExtensions = Array("webm","mp4");
	attachmentExpander.audioExtensions = Array("mp3","ogg","wav", "flac", "m4a");
	attachmentExpander.flashExtensions = Array("swf");

	// some koko js settings
	// Register settings function
	window.settingsHooks.push(function(tab, div) { 
		if (tab!="general") return;
		div.innerHTML+= `
			<label><input type="checkbox" onchange="localStorage.setItem('imgexpand',this.checked);attachmentExpander.resetAttachmentExpansion();attachmentExpander.startUpimageExpanding();"`+(localStorage.getItem("imgexpand")=="true"?' checked="checked"':'')+`>Inline image expansion</label>
			<label><input type="checkbox" onchange="localStorage.setItem('imghover',this.checked);document.getElementById('hoverimg').src='';"`+(localStorage.getItem("imghover")=="true"?' checked="checked"':'')+`>Image hover</label>`;
	});

	attachmentExpander.startUpimageExpanding = function () {
		// declare its localstorage value if its non-existant
		if (!localStorage.getItem("imgexpand")) {
			localStorage.setItem("imgexpand", "true");
		}

		// fetch all post images
		let postAttachmentAnchors = document.getElementsByClassName("attachmentAnchor");
		
		// loop through anchors so we can add listeners for all of them
		Array.from(postAttachmentAnchors).forEach(anchor => {
			// Store the click handler so it can be removed later
			anchor._expandHandler = function(event) {
				// Call the expander logic for this anchor
				attachmentExpander.expandAttachment(anchor, event);
			};

			// Store the mouseover handler
			anchor._hoverOnHandler = function() {
				// Show hover preview for this anchor
				attachmentExpander.hoverMouseOn(anchor);
			};

			// Store the mouseout handler
			anchor._hoverOffHandler = function() {
				// Hide hover preview
				attachmentExpander.hoverMouseOut();
			};

			// Add listeners
			anchor.addEventListener("click", anchor._expandHandler);
			anchor.addEventListener("mouseover", anchor._hoverOnHandler);
			anchor.addEventListener("mouseout", anchor._hoverOffHandler);
		});

		// append image hover element 
		document.body.insertAdjacentHTML("beforeend",
			'<img id="hoverimg" src="" alt="Full Image" '
			+ 'onerror="this.style.display=\'\';" '
			+ 'onload="this.style.display=\'inline-block\';" border="1">'
		);
	};

	attachmentExpander.expandAttachmentError = function(anchor) {
		// create error container
		let errorElement = document.createElement("span");
		errorElement.className = "attachmentError";
		errorElement.title = "There was an error while expanding this attachment!";
		errorElement.classList.add('error');

		attachmentExpander.addCloseButton(errorElement, errorElement, anchor);

		// insert it where the expanded element would go
		anchor.insertAdjacentElement("afterend", errorElement);
	};

	attachmentExpander.addCloseButton = function(appendElement, targetElement, anchor) {
		let closeButton = document.createElement("a");
		closeButton.href = "#";
		closeButton.textContent = "Close";
		closeButton.className = "attachmentCloseButton";

		// now append it
		appendElement.appendChild(document.createTextNode("["));
		appendElement.appendChild(closeButton);
		appendElement.appendChild(document.createTextNode("]"));

		// make the close button contract like normal
		closeButton.addEventListener("click", function(event) {
			event.preventDefault();
			targetElement.remove();
			anchor.style.display = ""; // show the thumbnail again
		});
	};

	attachmentExpander.hoverMouseOn = function (anchor) {
		// exit if hover previews are disabled in user settings
		if (localStorage.getItem("imghover") != "true") return;

		// fetch the hover image
		let hoverImage = document.getElementById("hoverimg");

		// set preview image source to the hovered link's URL
		// this triggers the image's onload handler to display it
		hoverImage.src = anchor.href;
	};

	attachmentExpander.hoverMouseOut = function () {
		// get the shared hover preview image
		let hoverImage = document.getElementById("hoverimg");

		// hide the preview image
		hoverImage.style.display = "";

		// clear the image source to stop displaying the preview
		hoverImage.src = "";
	};

	attachmentExpander.resetAttachmentExpansion = function () {
		// get all attachment anchors
		let postAttachmentAnchors = document.getElementsByClassName("attachmentAnchor");

		Array.from(postAttachmentAnchors).forEach(anchor => {
			// get post element so we can get the post uid
			let post = anchor.closest('.post[id^="p"]');
			if (post) {
				// get index data-* attribute
				let index = anchor.dataset.attachmentIndex;
				
				// then contract the attachment
				attachmentExpander.contractAttachment(post, index);
			}

			// remove listener attributes
			anchor.removeEventListener("click", anchor._expandHandler);
			anchor.removeEventListener("mouseover", anchor._hoverOnHandler);
			anchor.removeEventListener("mouseout", anchor._hoverOffHandler);
		});

		// get hover img element
		let hoverImage = document.getElementById("hoverimg");

		// delete it
		hoverImage.remove();
	};

	attachmentExpander.expandAttachment = function (anchor, event) {
		let post = anchor.closest('.post[id^="p"]');

		if (!post) return;
		let no = post.id.substr(1);
		if (localStorage.getItem("imgexpand")!="true") return;

		if (localStorage.getItem("galmode")=="true") {
			kkgal.expand(no);
			event.preventDefault();
			return;
		}

		let index = anchor.dataset.attachmentIndex;

		if (attachmentExpander.handleExpansion(post, no, anchor, index)) {
			event.preventDefault();
		}
	};

	attachmentExpander.handleExpansion = function (post, no, anchor, index) {
		// no anchor - return
		if (!anchor) return;

		// get extension from the href link
		let extension = anchor.dataset.extension;

		// hide anchor
		anchor.style.display = "none";

		// scroll the post into view
		if (post.getBoundingClientRect().top < 0) post.scrollIntoView();

		// add expanded image html
		if (attachmentExpander.imageExtensions.includes(extension)) {
			return attachmentExpander.createExpandable(anchor, post, index, true, function() {
				var img = document.createElement("img");
				img.className = "expandImage";
				img.src = anchor.href;
				img.alt = "Full image";
				img.title = "Click to contract";
				return img;
			});
		}
		else if (attachmentExpander.videoExtensions.includes(extension)) {
			return attachmentExpander.createExpandable(anchor, post, index, false, function() {
				var video = document.createElement("video");
				video.className = "expandVideo";
				video.controls = true;
				video.loop = true;
				video.autoplay = true;
				video.src = anchor.href;
				return video;
			});
		}
		else if(attachmentExpander.audioExtensions.includes(extension)) {
			return attachmentExpander.createExpandable(anchor, post, index, false, function() {
				var audio = document.createElement("audio");
				audio.className = "expandAudio";
				audio.controls = true;
				audio.autoplay = true;
				audio.src = anchor.href;
				return audio;
			});
		}
 		else if (attachmentExpander.flashExtensions.includes(extension)) {
			return attachmentExpander.handleFlashExpansion(anchor, no, index);
		}
		return false;
	};

	attachmentExpander.handleFlashExpansion = function (anchor, no, index) {
		// pull native dims from the existing filesize text (leave it visible)
		var fsNode = anchor.parentNode.querySelector(".filesize");
		if (!fsNode) return false;
			var re     = /(\d+)\s*x\s*(\d+)/ig,
				m      = null, tmp;

		// grab the *last* NxM in the text so filenames like " 0x40 Hues of Halloween.swf" don't confuse us
		while ((tmp = re.exec(fsNode.textContent)) !== null) m = tmp;
		var	realW  = m ? parseInt(m[1],10) : 550,
			realH  = m ? parseInt(m[2],10) : 400;
		
		// cap at 95%
		var vw    = document.documentElement.clientWidth,
			vh    = document.documentElement.clientHeight,
			maxW  = vw * 0.95,
			maxH  = vh * 0.95,
			ratio = realW / realH,
			dispW = Math.min(realW, maxW),
			dispH = dispW / ratio;

		if (dispH > maxH) {
			dispH = maxH;
			dispW = dispH * ratio;
		}

		// build container
		var hdr       = 20,
			container = document.createElement("div"),
			header    = document.createElement("div");

		// add close button
		attachmentExpander.addCloseButton(header, container, anchor);

		container.className      = "expand swf-expand";
		container.setAttribute('data-attachment-index', index);
		container.style.width    = dispW + "px";
		container.style.height   = (dispH + hdr) + "px";
		container.style.resize   = "both";
		container.style.overflow = "hidden";
		// ensure children absolute‐fill works
		container.style.position = "relative";

		// close header
		header.className    = "swf-expand-header";
		header.style.height = hdr + "px";
		container.appendChild(header);

		// insert & hide original link
		anchor.insertAdjacentElement("afterend", container);

		// build a host that fills the container and holds either native Flash or Ruffle
		// using CSS absolute fill ensures resizing always works
		var host      = document.createElement("div"),
			useNative = !!(navigator.mimeTypes['application/x-shockwave-flash'] ||
						   navigator.plugins['Shockwave Flash']);

		host.className      = "ruffleContainer";
		host.style.position = "absolute";
		host.style.top      = hdr + "px";
		host.style.left     = "0";
		host.style.right    = "0";
		host.style.bottom   = "0";
	
		container.appendChild(host);

		if (useNative) {
			// native Flash via <object>, fill host
			var obj = document.createElement("object");
			obj.data   = anchor.href;
			obj.type   = "application/x-shockwave-flash";
			obj.style.width  = "100%";
			obj.style.height = "100%";
			host.appendChild(obj);
		} else {
			// ruffle emulator, fill host
			var ruffle = window.RufflePlayer.newest(),
			player = ruffle.createPlayer();
			player.style.width  = "100%";
			player.style.height = "100%";
			host.appendChild(player);
			player.load(anchor.href);
		}
		
		return true;
	};

	attachmentExpander.createExpandable = function(anchor, post, index, isImage, contentBuilder) {
		// main container
		var expandDiv = document.createElement("div");
		expandDiv.className = "expand";
		expandDiv.setAttribute("data-attachment-index", index);

		if(!isImage) {
			// header with close link
			let header = document.createElement("div");

			attachmentExpander.addCloseButton(expandDiv, expandDiv, anchor);

			expandDiv.appendChild(header);
		}
		else {
			expandDiv.addEventListener("click", function(event) {
				event.preventDefault();
				event.stopPropagation();
				attachmentExpander.contractAttachment(expandDiv, post);
			});

			expandDiv.style.cursor = "pointer";
		}

		// custom content (img / video / etc.)
		let content = contentBuilder();

		// if media fails to load, remove expansion and show error instead
		content.addEventListener("error", function() {
			expandDiv.remove();
			attachmentExpander.expandAttachmentError(anchor);
		});

		expandDiv.appendChild(content);

		// insert into DOM
		anchor.insertAdjacentElement("afterend", expandDiv);


		return true;
	};

	attachmentExpander.contractAttachment = function (expandedAnchor, post) {
		if (!expandedAnchor) return;

		// Restore the original <a>
		var a = expandedAnchor.previousElementSibling;
		if (expandedAnchor) a.style.display = "";

		// Remove the expanded anchor
		expandedAnchor.remove();

		// Scroll into view if needed
		if (post.getBoundingClientRect().top < 0) post.scrollIntoView();
	}
	document.addEventListener("DOMContentLoaded", () => {
		attachmentExpander.startUpimageExpanding();
	});
})();