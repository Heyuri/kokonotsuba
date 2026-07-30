/**
 * report.js — the user-facing report window.
 *
 * The [Report] entry in a post's dropdown opens a window built from the #reportFormTemplate
 * <template> the module injects into the page head. The template is deliberately inert: it has
 * no post attached and no CSRF token, so this file fills in the post details from the page and
 * fetches a fresh token when the window opens. Without JS the same entry is a plain link to the
 * server-rendered form, so nothing here is load-bearing.
 *
 * Depends on: postWidget.js, windowLibrary.js, message.js
 */
(function () {
	'use strict';

	var moduleUrlMeta = document.querySelector('meta[name="reportModuleUrl"]');
	var moduleUrl = moduleUrlMeta ? moduleUrlMeta.getAttribute('content') : '';

	/**
	 * Fill in which post is being reported, and drop the parts of the template the window
	 * doesn't need.
	 *
	 * The template is shared with the server-rendered page, where both the heading and a
	 * preview of the post are the only things identifying it. In the window neither earns its
	 * space: the window chrome already carries the title, and the reader is looking straight at
	 * the post they just clicked.
	 */
	function fillReportForm(form, postEl, params) {
		if (!form) return;

		var postUid = params.postuid || (postEl && postEl.dataset ? postEl.dataset.postUid : '');
		var postNumber = params.postnumber || (postEl && postEl.dataset ? postEl.dataset.postNumber : '');

		var postUidField = form.querySelector('.reportPostUidField');
		if (postUidField) postUidField.value = postUid || '';

		var postNumberValue = form.querySelector('.reportPostNumberValue');
		if (postNumberValue) postNumberValue.textContent = postNumber || '?';

		var boardTitle = form.querySelector('.reportBoardTitle');
		if (boardTitle) boardTitle.textContent = params.boardtitle || '';

		var previewRow = form.querySelector('.reportPreviewRow');
		if (previewRow) previewRow.remove();

		// The heading lives outside the <form>, so reach for it from the window body.
		var container = form.closest('.reportFormContainer') || form.parentNode;
		var title = container ? container.querySelector('.reportFormTitle') : null;
		if (title) title.remove();
	}

	/** Ask the module for a CSRF token; the <template> can't carry one (it is cached in static HTML). */
	function loadCsrfToken(form) {
		if (!form || !moduleUrl) return;

		var tokenField = form.querySelector('input[name="csrf_token"]');
		if (!tokenField) return;

		fetch(moduleUrl + '&pageName=token', {
			credentials: 'same-origin',
			headers: { 'X-Requested-With': 'XMLHttpRequest' }
		})
			.then(function (res) { return res.json(); })
			.then(function (data) {
				if (data && data.token) tokenField.value = data.token;
			})
			.catch(function () { /* the submit will fail CSRF validation and say so */ });
	}

	if (!window.postWidget) return;

	window.postWidget.registerActionHandler('reportPost', function (ctx) {
		var postEl = (ctx && ctx.post) || (ctx && ctx.arrow ? ctx.arrow.closest('.post') : null);
		var params = (ctx && ctx.params) || {};

		if (!postEl) return;

		PostActionUtils.openWindow({
			templateId: '#reportFormTemplate',
			title: 'Report post',
			postEl: postEl,
			onOpen: function (opened) {
				fillReportForm(opened.form, postEl, params);
				loadCsrfToken(opened.form);
			},
			onSuccess: function (result) {
				var data = result.res;
				if (typeof data === 'string') {
					try { data = JSON.parse(data); } catch (e) { data = null; }
				}

				showMessage(data && data.message ? data.message : 'Report submitted.', true);
			},
			onFail: function () {
				// openWindow() leaves the window standing on failure, so the reader can fix the
				// reason and retry. It only hands us the HTTP status, not the endpoint's JSON
				// message, hence the generic wording.
				showMessage('Your report could not be filed.', false);
			}
		});
	});
})();
