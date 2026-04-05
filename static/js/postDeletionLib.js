// Utility: append a warning span
// scopeEl: optional element to scope the .filesize query for multi-attachment posts
function appendWarning(postEl, type, scopeEl) {
	if (!postEl) return null;

	if (type === 'post') {
		const infoExtra = postEl.querySelector('.postInfoExtra');
		if (!infoExtra) return null;

		const existsDeleted = infoExtra.querySelector('.warning[title="This post was deleted"]');
		if (existsDeleted) return null;

		const warn = document.createElement('span');
		warn.className = 'warning';
		warn.title = 'This post was deleted';
		warn.textContent = '[DELETED]';

		const spacer1 = document.createTextNode(' ');
		infoExtra.appendChild(spacer1);
		infoExtra.appendChild(warn);
		return { warn, spacer1, vd: null, spacer2: null };

	} else if (type === 'file') {
		const filesizeEl = (scopeEl || postEl).querySelector('.filesize');
		if (!filesizeEl) return null;

		const existsFileDel = filesizeEl.querySelector('.warning[title="This post\'s file was deleted"]');
		if (existsFileDel) return null;

		const warn = document.createElement('span');
		warn.className = 'warning';
		warn.title = "This post's file was deleted";
		warn.textContent = '[FILE DELETED]';
		filesizeEl.appendChild(warn);
		return { warn, spacer1: null, vd: null, spacer2: null };
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

function createViewFileButton(url) {
	const span = document.createElement('span');
	span.className = 'adminFunctions attachmentButton viewDeletedFileButton';

	// leading bracket
	const left = document.createTextNode('[');

	// the actual link
	const a = document.createElement('a');
	a.href = url;
	a.target = '_blank';
	a.textContent = 'VF';
	a.title = 'View deleted attachment';

	// trailing bracket
	const right = document.createTextNode(']');

	span.appendChild(left);
	span.appendChild(a);
	span.appendChild(right);

	return span;
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

	warningResult = appendWarning(postEl, 'file', attachmentEl);

	return {
		revertUI: function () {
			attachmentEl.classList.remove('deletedFile');
			for (var j = 0; j < hiddenControls.length; j++) {
				hiddenControls[j].classList.remove('indicatorHidden');
			}
			if (warningResult && warningResult.warn && warningResult.warn.parentNode) {
				warningResult.warn.parentNode.removeChild(warningResult.warn);
			}
			if (warningResult && warningResult.spacer1 && warningResult.spacer1.parentNode) {
				warningResult.spacer1.parentNode.removeChild(warningResult.spacer1);
			}
		},
		addViewFileButton: function (deletedLink) {
			if (!deletedLink) return;
			var vf = createViewFileButton(deletedLink);
			if (warningResult && warningResult.warn && warningResult.warn.parentNode) {
				warningResult.warn.parentNode.insertBefore(document.createTextNode(' '), warningResult.warn.nextSibling);
				warningResult.warn.nextSibling.after(vf);
			}
		}
	};
}

/**
 * Send a module action via AJAX GET and handle success/failure with revert and messages.
 *
 * @param {string} url  The action URL
 * @param {Object} options
 * @param {Function} [options.revertUI]        Called on failure to undo UI changes
 * @param {string}   [options.successMessage]  Shown on success
 * @param {string}   [options.errorMessage]    Fallback error message
 * @param {Function} [options.onSuccess]       Called with parsed JSON data on success
 */
function sendModuleAction(url, options) {
	fetch(url, {
		method: 'GET',
		credentials: 'same-origin',
		headers: { 'X-Requested-With': 'XMLHttpRequest' },
		cache: 'no-store'
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