// Utility: append a warning span
function appendWarning(postEl, type) {
	if (!postEl) return null;
	const infoExtra = postEl.querySelector('.postInfoExtra');
	if (!infoExtra) return null;

	const existsDeleted = infoExtra.querySelector('.warning[title="This post was deleted"]');
	const existsFileDel = infoExtra.querySelector('.warning[title="This post\'s file was deleted"]');

	if (type === 'post' && !existsDeleted) {
		const warn = document.createElement('span');
		warn.className = 'warning';
		warn.title = 'This post was deleted';
		warn.textContent = '[DELETED]';

		const spacer1 = document.createTextNode(' ');
		infoExtra.appendChild(spacer1);
		infoExtra.appendChild(warn);
		return { warn, spacer1, vd: null, spacer2: null };

	} else if (type === 'file' && !existsFileDel) {
		// Target the filesize section for appending the "[FILE DELETED]" message
		const filesizeEl = postEl.querySelector('.filesize');  // This assumes .filesize is where the file size is displayed
		if (filesizeEl) {
			const warn = document.createElement('span');
			warn.className = 'warning';
			warn.title = "This post's file was deleted";
			warn.textContent = '[FILE DELETED]';
			filesizeEl.appendChild(warn);
			return { warn, spacer1: null, vd: null, spacer2: null };
		}
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