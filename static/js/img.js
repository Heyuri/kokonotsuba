/* 
 * LOL HEYURI
 */

(function() {
	// declare namespace
	var attachmentExpander = attachmentExpander || {};

	attachmentExpander.startUpimageExpanding = function () {
		// declare its localstorage value if its non-existant
		if (!localStorage.getItem("imgexpand")) {
			localStorage.setItem("imgexpand", "true");
		}

		// fetch all post images
		let postAttachmentAnchors = document.getElementsByClassName("attachmentAnchor");
		
		// loop through anchors so we can add listeners for all of them
		postAttachmentAnchors.forEach(anchor => {
			// image expand listener
			anchor.addEventListener("click",    attachmentExpander.expandAttachment(anchor));
			
			// image hover expansion listener
			anchor.addEventListener("mouseover",attachmentExpander.hoverMouseOver(anchor));
			
			// clear image hover listener
			anchor.addEventListener("mouseout", attachmentExpander.hoverMouseOut());
		});

		// append image hover element 
		document.body.insertAdjacentHTML("beforeend",
			'<img id="hoverimg" src="" alt="Full Image" '
			+ 'onerror="this.style.display=\'\';" '
			+ 'onload="this.style.display=\'inline-block\';" border="1">'
		);
	},

	attachmentExpander.expandAttachmentError = function([no]) {
		// get the post element by its id
		let postElement = document.getElementById("p"+no);
		
		// then select the expanded element so we can display an error within it
		let expandedAttachment = postElement.getElementsByClassName("expand")[0];
		
		// append error span
		expandedAttachment.innerHTML = '<span class="error">Error loading file!</span> [<a class="attachmentCloseButton">Close</a>]';
	},

	attachmentExpander.hoverMouseOn = function (anchor) {
		// exit if hover previews are disabled in user settings
		if (localStorage.getItem("imghover") != "true") return;

		// fetch the hover image
		let hoverImage = document.getElementById("hoverimg");

		// set preview image source to the hovered link's URL
		// this triggers the image's onload handler to display it
		hoverImage.src = anchor.href;
	},

	attachmentExpander.hoverMouseOut = function () {
		// get the shared hover preview image
		let hoverImage = document.getElementById("hoverimg");

		// hide the preview image
		hoverImage.style.display = "";

		// clear the image source to stop displaying the preview
		hoverImage.src = "";
	},

	attachmentExpander.resetAttachmentExpansion = function () {
		// get all attachment anchors
		let postAttachmentAnchors = document.getElementsByClassName("attachmentAnchor");

		postAttachmentAnchors.forEach(anchor => {
			// get post element so we can get the post uid
			let post = anchor.closest('.post[id^="p"]');
			if (post) {
				// get index data-* attribute
				let index = anchor.dataset.attachmentIndex;
				
				// then contract the attachment
				attachmentExpander.contractAttachment(post, index);
			}

			// remove image expand listener
			anchor.removeEventListener("click", attachmentExpander.expandAttachment(anchor));
			
			// remove image hover expansion listener
			anchor.removeEventListener("mouseover",attachmentExpander.hoverMouseOver(anchor));
			
			// remove clear image hover listener
			anchor.removeEventListener("mouseout", attachmentExpander.hoverMouseOut());
		});

		// get hover img element
		let hoverImage = document.getElementById("hoverimg");

		// delete it
		hoverImage.remove();
	},

	attachmentExpander.appendSettings = function(tab, div) { if (tab!="general") return;
		div.innerHTML+= `
			<label><input type="checkbox" onchange="localStorage.setItem('imgexpand',this.checked);kkimg.reset();kkimg.startup();"`+(localStorage.getItem("imgexpand")=="true"?' checked="checked"':'')+`>Inline image expansion</label>
			<label><input type="checkbox" onchange="localStorage.setItem('imghover',this.checked);$id('hoverimg').src='';"`+(localStorage.getItem("imghover")=="true"?' checked="checked"':'')+`>Image hover</label>
			<label><input type="checkbox" onchange="localStorage.setItem('galmode',this.checked);kkgal.reset();kkgal.startup();"`+(localStorage.getItem("galmode")=="true"?' checked="checked"':'')+`>Gallery mode</label>`;
	},

	attachmentExpander.expandAttachment = function (anchor) {
		let post = anchor.closest('.post[id^="p"]');

		if (!post) return;
		let no = postEl.id.substr(1);

		if (localStorage.getItem("imgexpand")!="true") return;

		let index = anchor.dataset.attachmentIndex;

		if (attachmentExpander.handleExpansion(post, no, anchor, index)) {
			anchor.preventDefault();
		}
	}

	attachmentExpander.handleExpansion = function (post, no, anchor, index) {
		// select the attachment container at the index
		var container = post.querySelector('.attachmentContainer[data-attachment-index="'+index+'"]');
		
		// if the container doesn't exist then return early
		if (!container) return;

		// no anchor - return
		if (!anchor) return;

		// get extension from the href link
		let ext  = anchor.href.split(".").pop().toLowerCase();

		// hide anchor
		anchor.style.display = "none";

		// scroll the post into view
		if (post.getBoundingClientRect().top < 0) post.scrollIntoView();

		// add expanded image html
		if (attachmentExpander.imageExtensions.includes(ext)) {
			a.insertAdjacentHTML("afterend", '<div class="expand" data-attachment-index="'+index+'">'+
				'<a href="'+a.href+'" onclick="event.preventDefault();kkimg.contract(\''+no+'\', '+index+');">'+
				'<img src="'+a.href+'" alt="Full image" onerror="kkimg.error(\''+no+'\');" class="expandimg" title="Click to contract" border="0">'+
				'</a></div>');
			return true;
		} else if (kkimg.vidext.includes(ext)) {
			a.insertAdjacentHTML("afterend", '<div class="expand" data-attachment-index="'+index+'">'+
				'<div>[<a href="javascript:kkimg.contract(\''+no+'\', '+index+');">Close</a>]</div>'+
				'<video class="expandimg" controls="controls" loop="loop" autoplay="autoplay" src="'+a.href+'"></video>'+
				'</div>');
			return true;
		} else if (kkimg.swfext.includes(ext)) {
		// pull native dims from the existing filesize text (leave it visible)
		var fsNode = $q("#p"+no+" .filesize")[0],
			re     = /(\d+)\s*x\s*(\d+)/ig,
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
			header.innerHTML    = '[<a>Close</a>]';
			container.appendChild(header);

			// insert & hide original link
			a.insertAdjacentElement("afterend", container);

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
				obj.data   = a.href;
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
				player.load(a.href);
			}

			return true;
		}
		return false;
	}

	attachmentExpander.contractAttachment = function () {
		var anchor = p.querySelector('.expand[data-attachment-index="'+index+'"]');
		if (!exp) return;

		// Restore the original <a>
		var a = exp.previousElementSibling;
		if (anchor) a.style.display = "";

		// Remove the expanded container
		exp.remove();

		// Scroll into view if needed
		if (p.getBoundingClientRect().top < 0) p.scrollIntoView();
	}

	attachmentExpander.startUpimageExpanding();
})