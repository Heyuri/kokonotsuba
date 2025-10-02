(function () {
	// Utility: find the containing .post element
	function getPostEl(el) {
		return el.closest('.post');
	}

	// Utility: append a warning span + [VD] placeholder
	function appendWarning(postEl, type) {
		if (!postEl) return null;
		const infoExtra = postEl.querySelector('.postInfoExtra');
		if (!infoExtra) return null;

		const existsDeleted = infoExtra.querySelector('.warning[title="This post was deleted"]');
		const existsFileDel = infoExtra.querySelector('.warning[title="This post\'s file was deleted"]');

		function createVDPlaceholder() {
			const span = document.createElement('span');
			span.className = 'adminFunctions adminViewDeletedPostFunction';
			span.innerHTML = '[<a href="#" title="View deleted post">VD</a>]';
			return span;
		}

		if (type === 'post' && !existsDeleted) {
			const warn = document.createElement('span');
			warn.className = 'warning';
			warn.title = 'This post was deleted';
			warn.textContent = '[DELETED]';

			const spacer1 = document.createTextNode(' ');
			const vd = createVDPlaceholder();
			const spacer2 = document.createTextNode(' ');
			infoExtra.appendChild(spacer1);
			infoExtra.appendChild(vd);
			infoExtra.appendChild(spacer2);
			infoExtra.appendChild(warn);
			return { warn, spacer1, vd, spacer2 };
		} else if (type === 'file' && !existsFileDel) {
			const warn = document.createElement('span');
			warn.className = 'warning';
			warn.title = "This post's file was deleted";
			warn.textContent = '[FILE DELETED]';

			const spacer1 = document.createTextNode(' ');
			const vd = createVDPlaceholder();
			const spacer2 = document.createTextNode(' ');
			infoExtra.appendChild(spacer1);
			infoExtra.appendChild(vd);
			infoExtra.appendChild(spacer2);
			infoExtra.appendChild(warn);
			return { warn, spacer1, vd, spacer2 };
		}
		return null;
	}

	function hideDeleteControls(postEl, type) {
		if (!postEl) return [];
		const deleteSpans = postEl.querySelectorAll('.adminDeleteFunction, #adminDeleteFunction, .adminDeleteMuteFunction, #adminDeleteMuteFunction');
		const fileDeleteSpans = postEl.querySelectorAll('.adminDeleteFileFunction, #adminDeleteFileFunction');
		const hidden = [];
		if (type === 'post') {
			deleteSpans.forEach(x => { x.classList.add('hidden'); hidden.push(x); });
			fileDeleteSpans.forEach(x => { x.classList.add('hidden'); hidden.push(x); });
		} else if (type === 'file') {
			fileDeleteSpans.forEach(x => { x.classList.add('hidden'); hidden.push(x); });
		}
		return hidden;
	}

	document.addEventListener('click', function (e) {
		const control = e.target.closest(
			'.adminDeleteFunction, #adminDeleteFunction, ' +
			'.adminDeleteMuteFunction, #adminDeleteMuteFunction, ' +
			'.adminDeleteFileFunction, #adminDeleteFileFunction'
		);
		if (!control) return;

		const postEl = getPostEl(control);
		if (!postEl) return;

		const addedClasses = [];
		const appendedNodes = [];
		const hiddenControls = [];

		function addClassAndTrack(el, cls) {
			if (!el) return;
			el.classList.add(cls);
			addedClasses.push({ el, cls });
		}

		let vdNode = null; // keep track of placeholder

		if (
			control.matches('.adminDeleteFunction, #adminDeleteFunction') ||
			control.matches('.adminDeleteMuteFunction, #adminDeleteMuteFunction')
		) {
			if (postEl.classList.contains('op')) {
				const thread = postEl.closest('.thread');
				if (thread) {
					addClassAndTrack(thread, 'deletedPost');
					thread.querySelectorAll('.post').forEach(p => {
						const res = appendWarning(p, 'post');
						if (res) {
							if (res.spacer1) appendedNodes.push(res.spacer1);
							if (res.vd) { vdNode = res.vd; appendedNodes.push(res.vd); }
							if (res.spacer2) appendedNodes.push(res.spacer2);
							if (res.warn) appendedNodes.push(res.warn);
						}
					});
				}
			} else {
				addClassAndTrack(postEl, 'deletedPost');
				const res = appendWarning(postEl, 'post');
				if (res) {
					if (res.spacer1) appendedNodes.push(res.spacer1);
					if (res.vd) { vdNode = res.vd; appendedNodes.push(res.vd); }
					if (res.spacer2) appendedNodes.push(res.spacer2);
					if (res.warn) appendedNodes.push(res.warn);
				}
			}
			hiddenControls.push(...hideDeleteControls(postEl, 'post'));
		}

		if (control.matches('.adminDeleteFileFunction, #adminDeleteFileFunction')) {
			const imgContainer = postEl.querySelector('.imageSourceContainer');
			if (imgContainer) addClassAndTrack(imgContainer, 'deletedFile');
			const res = appendWarning(postEl, 'file');
			if (res) {
				if (res.spacer1) appendedNodes.push(res.spacer1);
				if (res.vd) { vdNode = res.vd; appendedNodes.push(res.vd); }
				if (res.spacer2) appendedNodes.push(res.spacer2);
				if (res.warn) appendedNodes.push(res.warn);
			}
			hiddenControls.push(...hideDeleteControls(postEl, 'file'));
		}

		const href = (control.tagName === 'A' && control.href) ? control.href :
			(control.getAttribute && control.getAttribute('href')) ||
			(function () { const a = control.querySelector && control.querySelector('a[href]'); return a ? a.href : null; })();

		function onFail() { showMessage("Post deletion failed!", false); }
		function onSuccess() { showMessage("Post deleted!", true); }

		function revertUI() {
			addedClasses.forEach(x => x.el.classList.remove(x.cls));
			appendedNodes.forEach(n => n && n.parentNode && n.parentNode.removeChild(n));
			hiddenControls.forEach(x => x.classList.remove('hidden'));
		}

		if (href) {
			e.preventDefault();
			fetch(href, {
				method: 'GET',
				credentials: 'same-origin',
				headers: { 'X-Requested-With': 'XMLHttpRequest' },
				cache: 'no-store'
			}).then(res => {
				if (!res.ok) { onFail(); revertUI(); return; }
				res.json().then(data => {
					if (data && data.success && data.deleted_link && vdNode) {
						const a = vdNode.querySelector('a');
						if (a) a.href = data.deleted_link;
					}
					onSuccess();
				}).catch(() => { onSuccess(); });
			}).catch(() => { onFail(); revertUI(); });
		}
	});
})();
