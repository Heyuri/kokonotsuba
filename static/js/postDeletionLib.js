// Utility: show a deletion indicator
// For 'post': toggles the always-present .indicator-deleted container
// For 'file': toggles the always-present .indicator-fileDeleted container
// scopeEl: optional element to scope the .filesize query for multi-attachment posts
function showDeletionIndicator(postEl, type, scopeEl) {
	if (!postEl) return null;

	if (type === 'post') {
		const infoExtra = postEl.querySelector('.postInfoExtra');
		if (!infoExtra) return null;

		const indicator = infoExtra.querySelector('.indicator-deleted');
		if (!indicator) return null;

		// Already visible
		if (!indicator.classList.contains('indicatorHidden')) return null;

		indicator.classList.remove('indicatorHidden');
		return { indicator: indicator };

	} else if (type === 'file') {
		const filesizeEl = (scopeEl || postEl).querySelector('.filesize');
		if (!filesizeEl) return null;

		const indicator = filesizeEl.querySelector('.indicator-fileDeleted');
		if (!indicator) return null;

		// Already visible
		if (!indicator.classList.contains('indicatorHidden')) return null;

		indicator.classList.remove('indicatorHidden');
		return { indicator: indicator };
	}
	return null;
}


// Utility: remove specific widget items from this post's widgetRefs
function removeWidgetActions(postEl, actions) {
	if (!postEl || !actions || !actions.length) return;
	const refs = postEl.querySelector('.widgetRefs');
	if (!refs) return;
	actions.forEach(function (act) {
		refs.querySelectorAll('a[data-action="' + act + '"]').forEach(function (a) {
			a.remove();
		});
	});
}

/**
 * Prepare attachment deletion UI state: mark as deleted, hide buttons, add warning.
 * Returns an object with revertUI() and addViewFileButton(deletedLink) methods.
 *
 * @param {Element} attachmentEl  The .attachmentContainer or .file element
 * @param {Element} postEl        The containing .post element
 * @param {string[]} buttonSelectors  CSS selectors for buttons to hide within the attachment
 */
function prepareAttachmentDeletion(attachmentEl, postEl, buttonSelectors) {
	var hiddenControls = [];
	var warningResult = null;

	attachmentEl.classList.add('deletedFile');

	for (var i = 0; i < buttonSelectors.length; i++) {
		var btn = attachmentEl.querySelector(buttonSelectors[i]);
		if (btn) {
			btn.classList.add('indicatorHidden');
			hiddenControls.push(btn);
		}
	}

	warningResult = showDeletionIndicator(postEl, 'file', attachmentEl);

	return {
		revertUI: function () {
			attachmentEl.classList.remove('deletedFile');
			for (var j = 0; j < hiddenControls.length; j++) {
				hiddenControls[j].classList.remove('indicatorHidden');
			}
			if (warningResult && warningResult.indicator) {
				warningResult.indicator.classList.add('indicatorHidden');
			}
		},
	};
}

/**
 * Send a module action via AJAX POST with CSRF token.
 *
 * @param {string} url  The action URL
 * @param {Object} options
 * @param {Function} [options.revertUI]        Called on failure to undo UI changes
 * @param {string}   [options.successMessage]  Shown on success
 * @param {string}   [options.errorMessage]    Fallback error message
 * @param {Function} [options.onSuccess]       Called with parsed JSON data on success
 * @param {Object} [extraParams]   Additional key-value pairs to include in the POST body
 */
function sendModuleAction(url, options, extraParams) {
	var csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
	var body = new URLSearchParams();
	body.append('csrf_token', csrfToken);

	if (extraParams) {
		for (var key in extraParams) {
			if (extraParams.hasOwnProperty(key)) {
				body.append(key, extraParams[key]);
			}
		}
	}

	fetch(url, {
		method: 'POST',
		credentials: 'same-origin',
		headers: { 'X-Requested-With': 'XMLHttpRequest' },
		body: body
	})
	.then(async function (response) {
		var data = null;
		try { data = await response.json(); } catch (_) {}

		if (!response.ok) {
			if (options.revertUI) options.revertUI();
			var message = (data && data.message) ? data.message : (options.errorMessage || 'Request failed.');
			if (typeof showMessage === 'function') showMessage(message, false);
			return;
		}

		if (options.onSuccess) options.onSuccess(data);

		if (typeof showMessage === 'function') {
			showMessage(options.successMessage || 'Action complete.', true);
		}
	})
	.catch(function () {
		if (options.revertUI) options.revertUI();
		if (typeof showMessage === 'function') showMessage(options.errorMessage || 'Network error.', false);
	});
}