	// === Generic function to handle toggle actions (sticky, lock, etc.) ===
	async function handleThreadToggle(ctx, options) {
		const {
			templateId,           // e.g. "stickyIconTemplate"
			actionName,           // e.g. "sticky"
			iconClass,				// e.g. "stickyIcon"
			messageOn,             // e.g. "Thread stickied!"
			messageOff,            // e.g. "Thread un-stickied!"
			labelOn,               // e.g. "Unsticky thread"
			labelOff               // e.g. "Sticky thread"
		} = options;

		const template = document.getElementById(templateId);
		if (!template) {
			console.error(`Template not found: ${templateId}`);
			return;
		}

		const post = ctx.post || ctx.menuItem.closest('.post');
		if (!post) return;

		const extra = post.querySelector('.postInfoExtra');
		if (!extra) return;

		let icon = extra.querySelector(`.${iconClass}`);
		let iconWasExisting = !!icon;

		// === Gray out current icon or create temporary one ===
		if (icon) {
			icon.style.opacity = "0.5";
		} else {
			icon = template.content.firstElementChild.cloneNode(true);
			icon.style.opacity = "0.5";
			extra.appendChild(icon);
			iconWasExisting = false;
		}

		try {
			const response = await fetch(ctx.url, {
				credentials: 'include',
				headers: {
					'X-Requested-With': 'XMLHttpRequest',
					'Accept': 'application/json'
				}
			});

			if (!response.ok) throw new Error(`HTTP error ${response.status}`);

			let json;
			try {
				json = await response.json();
			} catch {
				throw new Error("Bad JSON");
			}

			// === Success: update icon & labels ===
			if (json.active) {
				extra.querySelectorAll(`.${iconClass}`).forEach(el => el.remove());
				const newIcon = template.content.firstElementChild.cloneNode(true);
				newIcon.style.opacity = "1";
				extra.appendChild(newIcon);
			} else {
				extra.querySelectorAll(`.${iconClass}`).forEach(el => el.remove());
			}

			const newLabel = json.active ? labelOn : labelOff;

			post.querySelectorAll(`a[data-action="${actionName}"]`).forEach(a => {
				a.textContent = newLabel;
				a.dataset.label = newLabel;
			});

			showMessage(json.active ? messageOn : messageOff, true);


		} catch (err) {
			console.error(err);

			// === Revert on failure ===
			if (icon && iconWasExisting) {
				icon.style.opacity = "1";
			} else if (icon && !iconWasExisting) {
				icon.remove();
			}

			showMessage(`${actionName.charAt(0).toUpperCase() + actionName.slice(1)} operation failed.`, false);
		}
	}