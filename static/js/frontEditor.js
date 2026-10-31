/**
 * frontEditor.js - the front page editor: a live preview of the body in a sandboxed iframe
 * (no scripts run there), drawn with the page's own stylesheets, and confirmation on delete.
 */
(function () {
	'use strict';

	var input = document.querySelector('.frontBodyInput');
	var frame = input && document.getElementById(input.getAttribute('data-preview'));

	function escapeAttr(value) {
		return value.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
	}

	/** The stylesheets this page applies, so the preview follows the chosen style. */
	function styleLinks() {
		var html = '';
		document.querySelectorAll('link[rel~="stylesheet"]').forEach(function (link) {
			// an untouched alternate style reads link.disabled false, but its sheet is off
			var applied = link.sheet ? !link.sheet.disabled : !link.disabled;
			if (applied && link.href) {
				html += '<link rel="stylesheet" href="' + escapeAttr(link.href) + '">';
			}
		});
		return html;
	}

	function render() {
		frame.srcdoc = '<!DOCTYPE html><html><head><meta charset="utf-8"><base target="_blank">' + styleLinks()
			+ '</head><body id="img" class="frontpage ' + escapeAttr(document.body.className) + '"><div id="content">'
			+ '<div class="' + escapeAttr(frame.getAttribute('data-wrap')) + '">' + input.value + '</div></div></body></html>';
	}

	if (frame) {
		var timer = null;
		input.addEventListener('input', function () {
			clearTimeout(timer);
			timer = setTimeout(render, 250);
		});
		render();
	}

	document.querySelectorAll('[data-confirm]').forEach(function (button) {
		button.addEventListener('click', function (event) {
			if (!window.confirm(button.getAttribute('data-confirm'))) {
				event.preventDefault();
			}
		});
	});
})();
