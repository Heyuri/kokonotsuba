(function() {
	'use strict';

	const ytRegex =
		/^(https?:\/\/)?(www\.)?(youtube\.com\/watch\?v=|youtu\.be\/)([A-Za-z0-9_-]{11})/;

	// Create the thumbnail with a real <a> link
	function makeThumb(id) {
		const box = document.createElement('div');
		box.className = 'video-container';
		box.dataset.video = id;
		box.style.pointerEvents = "none"; // div itself is not clickable

		const link = document.createElement('a');
		link.href = "https://youtu.be/" + id;
		link.target = "_blank"; // middle-click opens properly, left-click overridden
		link.style.pointerEvents = "auto";

		const img = document.createElement('img');
		img.className = 'post-image';
		img.src = '//img.youtube.com/vi/' + id + '/0.jpg';
		img.style.width = '360px';
		img.style.height = '270px';
		img.style.cursor = "pointer";
		img.style.pointerEvents = "auto"; // only this is clickable

		link.appendChild(img);
		box.appendChild(link);

		return box;
	}

	// Expanded iframe
	function makeExpand(id) {
		const wrap = document.createElement('div');
		wrap.className = 'expand youtube-expand';
		wrap.dataset.videoId = id;

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

	// Only left-click on the IMAGE opens the embed
	function attachHandlers(thumb, id) {
		const img = thumb.querySelector('img');
		const link = thumb.querySelector('a');

		img.addEventListener('click', function(evt) {
			// Only left-click should expand
			if (evt.button !== 0) return;

			// Prevent navigating to YouTube for left-click only
			evt.preventDefault();

			const exp = makeExpand(id);
			thumb.replaceWith(exp);

			// Close → restore thumbnail
			exp.querySelector('.yt-close').addEventListener('click', () => {
				const newThumb = makeThumb(id);
				exp.replaceWith(newThumb);
				attachHandlers(newThumb, id);
			});
		});
	}

	// Detect first-YouTube-link posts
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

	function apply() {
		document.querySelectorAll('.comment').forEach(processComment);
	}

	new MutationObserver(apply).observe(document.body, { childList: true, subtree: true });
	apply();
})();
