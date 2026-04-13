	// === Generic function to handle toggle actions (sticky, lock, etc.) ===
	async function handleThreadToggle(ctx, options) {
		const {
			actionName,           // e.g. "sticky"
			indicatorClass,       // e.g. "indicator-sticky"
			messageOn,             // e.g. "Thread stickied!"
			messageOff,            // e.g. "Thread un-stickied!"
			labelOn,               // e.g. "Unsticky thread"
			labelOff               // e.g. "Sticky thread"
		} = options;

		const post = ctx.post || ctx.menuItem.closest('.post');
		if (!post) return;

		const extra = post.querySelector('.postInfoExtra');
		if (!extra) return;

		const indicator = extra.querySelector(`.${indicatorClass}`);
		if (!indicator) return;

		const wasHidden = indicator.classList.contains('indicatorHidden');

		// === Show indicator with reduced opacity as loading state ===
		indicator.classList.remove('indicatorHidden');
		indicator.style.opacity = "0.5";

		const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

		try {
			const body = new URLSearchParams();
			body.append('csrf_token', csrfToken);

			// Append params from widget data attributes (e.g. post_uid)
			if (ctx.params) {
				for (const key in ctx.params) {
					body.append(key, ctx.params[key]);
				}
			}

			const response = await fetch(ctx.url, {
				method: 'POST',
				credentials: 'include',
				headers: {
					'X-Requested-With': 'XMLHttpRequest',
					'Accept': 'application/json'
				},
				body: body
			});

			if (!response.ok) throw new Error(`HTTP error ${response.status}`);

			let json;
			try {
				json = await response.json();
			} catch {
				throw new Error("Bad JSON");
			}

			// === Success: toggle indicator visibility ===
			if (json.active) {
				indicator.classList.remove('indicatorHidden');
			} else {
				indicator.classList.add('indicatorHidden');
			}
			indicator.style.opacity = "";

			const newLabel = json.active ? labelOn : labelOff;

			post.querySelectorAll(`a[data-action="${actionName}"]`).forEach(a => {
				a.textContent = newLabel;
				a.dataset.label = newLabel;
			});

			showMessage(json.active ? messageOn : messageOff, true);


		} catch (err) {
			console.error(err);

			// === Revert on failure ===
			if (wasHidden) {
				indicator.classList.add('indicatorHidden');
			}
			indicator.style.opacity = "";

			showMessage(`${actionName.charAt(0).toUpperCase() + actionName.slice(1)} operation failed.`, false);
		}
	}