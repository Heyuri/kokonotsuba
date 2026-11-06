/** Remembers per thread whether its deleted post stubs are shown. The toggle itself is css. */
(function () {
	const KEY = 'shownStubThreads';

	function load() {
		try {
			return new Set(JSON.parse(localStorage.getItem(KEY) || '[]'));
		} catch (e) {
			return new Set();
		}
	}

	function save(set) {
		try {
			localStorage.setItem(KEY, JSON.stringify([...set]));
		} catch (e) {}
	}

	const shown = load();

	function restore(box) {
		if (shown.has(box.id)) box.checked = true;
	}

	document.addEventListener('change', function (e) {
		const box = e.target;
		if (!box.classList || !box.classList.contains('stubToggle')) return;
		if (box.checked) shown.add(box.id); else shown.delete(box.id);
		save(shown);
	});

	// loaded in the head without defer: tick each toggle as the parser inserts it, before it is painted
	if (shown.size > 0 && document.readyState === 'loading') {
		const observer = new MutationObserver(function (mutations) {
			mutations.forEach(function (m) {
				m.addedNodes.forEach(function (node) {
					if (node.nodeType !== 1) return;
					if (node.classList.contains('stubToggle')) restore(node);
					else node.querySelectorAll('.stubToggle').forEach(restore);
				});
			});
		});
		observer.observe(document.documentElement, { childList: true, subtree: true });
		document.addEventListener('DOMContentLoaded', function () {
			observer.disconnect();
			document.querySelectorAll('.stubToggle').forEach(restore);
		});
	} else {
		document.querySelectorAll('.stubToggle').forEach(restore);
	}
})();
