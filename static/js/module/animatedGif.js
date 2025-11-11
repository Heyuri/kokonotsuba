(function () {
	const gifStates = new WeakMap();

	// Helper: detect initial GIF state
	function detectGifState(post) {
		if (!post) return false;
		const img = post.querySelector('.postimg');
		if (!img) return false;
		// if current src ends with .gif → it's animated
		const isGif = /\.gif$/i.test(new URL(img.src, location.href).pathname);
		gifStates.set(post, isGif);
		return isGif;
	}

	window.postWidget.registerActionHandler('animateGif', async ({ url, post }) => {
		if (!post) return;
		const img = post.querySelector('.postimg');
		if (!img) {
			showMessage('No image found in post.', false);
			return;
		}

		// detect current state if we haven't yet
		if (!gifStates.has(post)) detectGifState(post);
		const isEnabled = gifStates.get(post) === true;

		let newSrc = null;
		let preloadImg = null;

		// Find full and thumbnail URLs
		const fullLink = post.querySelector('.imageSourceContainer a[href$=".gif"]');
		const thumbBase = fullLink ? fullLink.href.replace(/\.gif$/i, '') : null;
		const thumbCandidates = thumbBase
			? [thumbBase + 's.png', thumbBase + 's.jpg']
			: [];

		if (isEnabled) {
			// Disable animation: restore thumbnail
			newSrc = thumbCandidates.find(src => src !== img.src) || img.src;
		} else if (fullLink) {
			// Enable animation: use full GIF
			newSrc = fullLink.href;
		} else {
			showMessage('GIF URL not found.', false);
			return;
		}

		// visual feedback
		img.style.transition = 'opacity 0.25s ease';
		img.style.opacity = '0.5';

		// preload new image
		preloadImg = new Image();
		const preloadPromise = new Promise(resolve => {
			preloadImg.onload = () => resolve(true);
			preloadImg.onerror = () => resolve(false);
			preloadImg.src = newSrc;
		});

		try {
			// run preload and fetch together
			const [preloadSuccess, res] = await Promise.all([
				preloadPromise,
				fetch(url, { method: 'GET', credentials: 'same-origin' })
			]);

			if (!res.ok) throw new Error('Server returned ' + res.status);

			if (isEnabled) {
				img.src = newSrc;
				gifStates.set(post, false);
				showMessage('GIF animation disabled', true);
			} else {
				if (!preloadSuccess) console.warn('GIF preload failed, loading anyway.');
				img.src = newSrc;
				gifStates.set(post, true);
				showMessage('GIF animation enabled', true);
			}

			// restore opacity when image is ready
			img.onload = () => (img.style.opacity = '1');
			if (img.complete) img.style.opacity = '1';

		} catch (err) {
			console.error('animateGif error:', err);
			showMessage('Failed to toggle GIF animation', false);
			img.style.opacity = '1';
		}
	});

	window.postWidget.registerLabelProvider('animateGif', ({ post }) => {
		if (!gifStates.has(post)) detectGifState(post);
		const isEnabled = gifStates.get(post) === true;
		return isEnabled ? 'Disable gif animation' : 'Animate gif';
	});
})();
