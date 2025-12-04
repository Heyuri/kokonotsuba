(function() {
	'use strict';

	const ytRegex =
		/^(https?:\/\/)?(www\.)?(youtube\.com\/watch\?v=|youtu\.be\/)([A-Za-z0-9_-]{11})/;

	// Create the thumbnail block
	function makeThumb(id) {
		const box = document.createElement('div');
		box.className = 'video-container';
		box.dataset.video = id;
		box.style.pointerEvents = "none";  // div not clickable

		const img = document.createElement('img');
		img.className = 'post-image';
		img.src = '//img.youtube.com/vi/' + id + '/0.jpg';
		img.style.width = '360px';
		img.style.height = '270px';
		img.style.cursor = "pointer";
		img.style.pointerEvents = "auto"; // ONLY the image is clickable

		box.appendChild(img);
		return box;
	}

	// Create the expanded iframe block (matches MP4 expand style)
	function makeExpand(id) {
		const wrap = document.createElement('div');
		wrap.className = 'expand youtube-expand';
		wrap.dataset.videoId = id;

		// [Close] bar just like MP4
		const bracket = document.createElement('div');
		bracket.innerHTML = '[<a href="javascript:void(0)" class="yt-close">Close</a>]';

		const iframe = document.createElement('iframe');
		iframe.className = "expandimg";
		iframe.width = "560";
		iframe.height = "315";
		iframe.src = "https://www.youtube.com/embed/" + id;
		iframe.allow =
			"accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture";
		iframe.allowFullscreen = true;

		wrap.appendChild(bracket);
		wrap.appendChild(iframe);

		return wrap;
	}

	// Connect thumbnail image to open the embed
	function attachHandlers(thumb, id) {
		const img = thumb.querySelector('img'); // only image clickable

		img.addEventListener('click', function open() {
			const exp = makeExpand(id);
			thumb.replaceWith(exp);

			// Close button returns to thumbnail
			exp.querySelector('.yt-close').addEventListener('click', function close() {
				const newThumb = makeThumb(id);
				exp.replaceWith(newThumb);
				attachHandlers(newThumb, id); // reattach
			});
		});
	}

	// Process posts
	function processComment(comment) {
		const first = comment.firstChild;
		if (!first || first.nodeType !== Node.ELEMENT_NODE || first.tagName !== 'A') return;

		const href = first.getAttribute('href');
		const m = href.match(ytRegex);
		if (!m) return;

		const id = m[4];

		const thumb = makeThumb(id);
		comment.insertBefore(thumb, first);
		first.remove();

		attachHandlers(thumb, id);
	}

	// Apply to all comments and future elements
	function apply() {
		document.querySelectorAll('.comment').forEach(processComment);
	}

	new MutationObserver(apply).observe(document.body, { childList: true, subtree: true });
	apply();
})();
