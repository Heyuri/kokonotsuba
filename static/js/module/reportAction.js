/**
 * reportAction.js — the [Action] window on the report tables, and the "post has been reported"
 * link under posts on the live frontend.
 *
 * Both windows are built from <template> blocks the module puts in the page head — the very same
 * blocks the server renders for the no-JS pages — so there is one set of markup either way. The
 * report list fills its clone from the postReportsApi payload, which is preloaded for reported
 * posts as they scroll into view.
 *
 * Depends on: koko.js (kkwmWindow), windowLibrary.js, message.js
 */
(function () {
	'use strict';

	/** Endpoint payloads and in-flight requests, keyed by URL. */
	var reportCache = {};
	var inFlight = {};

	/**
	 * The CSRF token for this page.
	 *
	 * Read off a form already on the page rather than baked into the <template>: that markup is
	 * emitted from the ModuleHeader hook, which also runs while static HTML is generated, and a
	 * token cached into a static page would be wrong for whoever reads it later.
	 */
	function currentCsrfToken() {
		var field = document.querySelector('form input[name="csrf_token"]');
		if (field && field.value) return field.value;

		var meta = document.querySelector('meta[name="csrf-token"]');
		return meta ? meta.content : '';
	}

	/**
	 * Fill in which report the [Action] form is about.
	 *
	 * The form is cloned from a <template> in the page head, so it arrives blank — everything
	 * identifying the report comes from the reportApi payload.
	 */
	function fillActionForm(form, report) {
		var set = function (selector, value) {
			var el = form.querySelector(selector);
			if (el) el.textContent = value || '';
		};

		set('.reportPostNumberValue', report.postNumber);
		set('.reportBoardTitle', report.boardTitle);
		set('.reportActionReason', report.reason);

		var postLink = form.querySelector('.reportActionPostLink');
		if (postLink) postLink.href = report.postUrl || '#';

		// dateHtml is server-built date markup, not user input.
		var dateCell = form.querySelector('.reportActionDate');
		if (dateCell) dateCell.innerHTML = report.dateHtml || '';
	}

	/** Open a window holding a clone of a <template>, with the report's id and token filled in. */
	function openActionWindow(link) {
		var reportId = link.getAttribute('data-report-id') || '';
		var dataUrl = link.getAttribute('data-report-url');

		var win = PostActionUtils.openWindow({
			templateId: '#reportActionFormTemplate',
			title: link.textContent.replace(/[\[\]]/g, '') || 'Action report',
			postEl: document.body,
			onOpen: function (opened) {
				var form = opened.form;
				if (!form) return;

				var idField = form.querySelector('.reportActionIdField');
				if (idField) idField.value = reportId;

				var tokenField = form.querySelector('input[name="csrf_token"]');
				if (tokenField) tokenField.value = currentCsrfToken();

				// The row this was opened from already shows the post.
				var previewRow = form.querySelector('.reportPreviewRow');
				if (previewRow) previewRow.remove();

				var title = form.closest('.reportFormContainer');
				title = title ? title.querySelector('.reportFormTitle') : null;
				if (title) title.remove();

				if (!dataUrl) return;

				loadReports(dataUrl)
					.then(function (report) { fillActionForm(form, report); })
					.catch(function (err) {
						showMessage(PostActionUtils.errorMessage(err, 'Could not load this report.'), false);
					});
			},
			onSuccess: function () {
				// The decision changes the table underneath, so take the page with it.
				window.location.reload();
			},
			onFail: function (result) {
				showMessage(
					PostActionUtils.errorMessage(result.err, 'The report could not be actioned.'),
					false
				);
			}
		});

		return win;
	}

	/** Clone a <template> by id, or null when the module didn't emit it. */
	function cloneTemplate(id) {
		var template = document.getElementById(id);
		return template ? template.content.cloneNode(true) : null;
	}

	/** Build one row of the reports table from an API entry. */
	function buildReportRow(report) {
		var fragment = cloneTemplate('reportWindowRowTemplate');
		if (!fragment) return null;

		var row = fragment.querySelector('tr');
		if (row && report.statusClass) row.classList.add(report.statusClass);

		var set = function (selector, value) {
			var el = fragment.querySelector(selector);
			if (el) el.textContent = value || '';
		};
		var setHref = function (selector, value) {
			var el = fragment.querySelector(selector);
			if (el) el.href = value || '#';
		};

		set('.reportRowReason', report.reason);
		set('.reportRowIpLink', report.ip);
		setHref('.reportRowIpLink', report.ipReportsUrl);
		setHref('.reportRowActionLink', report.actionUrl);
		setHref('.reportRowViewLink', report.viewUrl);

		// dateHtml is server-built date markup (spans for date/weekday/time), not user input.
		var dateCell = fragment.querySelector('.reportRowDate');
		if (dateCell) dateCell.innerHTML = report.dateHtml || '';

		// Rows here are built from JSON rather than the template's placeholders, so the [Action]
		// link needs both attributes set by hand or its window opens with nothing to show.
		var actionLink = fragment.querySelector('.reportRowActionLink');
		if (actionLink) {
			actionLink.setAttribute('data-report-id', report.reportId);
			actionLink.setAttribute('data-report-url', report.actionDataUrl || '');
		}

		return fragment;
	}

	/** Fill the cloned page block with the API payload. */
	function renderReports(shell, data) {
		var stats = data.stats || {};
		var counts = {
			'.reportStatTotal': stats.report_count,
			'.reportStatPending': stats.pending_count,
			'.reportStatApproved': stats.approved_count,
			'.reportStatDismissed': stats.dismissed_count
		};
		Object.keys(counts).forEach(function (selector) {
			var cell = shell.querySelector(selector);
			if (cell) cell.textContent = counts[selector] || 0;
		});

		var reports = data.reports || [];
		var tbody = shell.querySelector('.reportWindowRows');
		var empty = shell.querySelector('.reportWindowEmpty');
		var wrapper = shell.querySelector('.reportWindowTableWrapper');

		if (!reports.length) {
			if (wrapper) wrapper.classList.add('reportTableHidden');
			return;
		}

		if (empty) empty.remove();
		if (wrapper) wrapper.classList.remove('reportTableHidden');
		if (!tbody) return;

		reports.forEach(function (report) {
			var row = buildReportRow(report);
			if (row) tbody.appendChild(row);
		});
	}

	/**
	 * Fetch from one of the module's data endpoints, at most once per URL.
	 *
	 * Serves the preloader, the reports window and the [Action] form alike, so a notice that has
	 * already been preloaded opens instantly and a click mid-flight joins the request in progress
	 * rather than starting a second one.
	 */
	function loadReports(url) {
		if (reportCache[url]) return Promise.resolve(reportCache[url]);
		if (inFlight[url]) return inFlight[url];

		inFlight[url] = fetch(url, {
			credentials: 'same-origin',
			headers: { 'X-Requested-With': 'XMLHttpRequest' }
		})
			.then(function (res) {
				// Same treatment as a form submit: keep whatever the endpoint said went wrong.
				if (!res.ok) return PostActionUtils.responseError(res).then(function (err) { throw err; });
				return res.json();
			})
			.then(function (data) {
				reportCache[url] = data;
				delete inFlight[url];
				return data;
			})
			.catch(function (err) {
				delete inFlight[url];
				throw err;
			});

		return inFlight[url];
	}

	/**
	 * Warm the cache for reported posts as they come into view.
	 *
	 * A board page can carry a lot of reported posts and a moderator will open few of them, so
	 * the fetch waits until the notice is near the viewport rather than firing for the whole
	 * page at once. The endpoint marks nothing read, so preloading has no side effects.
	 */
	function preloadVisibleReports() {
		var links = document.querySelectorAll('.reportedPostLink[data-reports-url]');
		if (!links.length) return;

		var warm = function (link) {
			loadReports(link.getAttribute('data-reports-url')).catch(function () {});
		};

		if (!('IntersectionObserver' in window)) {
			links.forEach(warm);
			return;
		}

		var observer = new IntersectionObserver(function (entries) {
			entries.forEach(function (entry) {
				if (!entry.isIntersecting) return;
				observer.unobserve(entry.target);
				warm(entry.target);
			});
		}, { rootMargin: '300px' });

		links.forEach(function (link) { observer.observe(link); });
	}

	/**
	 * Open a window listing the reports on a post.
	 *
	 * The markup is a clone of the same per-post reports page block the server renders, filled
	 * from the postReportsApi payload; .reportWindowBody drops the columns and page chrome a
	 * window doesn't need, so one set of templates serves both.
	 *
	 * kkwm keys a window by its title, so reopening the same post's reports focuses the window
	 * already on screen instead of stacking duplicates: the constructor flashes the existing one
	 * and returns without building a div, which the guard below detects.
	 */
	function openPostReportsWindow(link) {
		var dataUrl = link.getAttribute('data-reports-url');
		if (!dataUrl || typeof kkwmWindow !== 'function') return;

		var shell = cloneTemplate('reportWindowTemplate');
		if (!shell) return;

		var win = new kkwmWindow(link.textContent.trim(), { w: 900, h: 480 });
		if (!win || !win.div) return;

		// .window is overflow:hidden and sizes to its content, so the body bounds itself against
		// the viewport and scrolls internally — the same shape .configChangesBody uses.
		var body = document.createElement('div');
		body.className = 'reportWindowBody centerText';
		win.div.appendChild(body);
		body.appendChild(shell);

		loadReports(dataUrl)
			.then(function (data) { renderReports(body, data); })
			.catch(function (err) {
				showMessage(
					PostActionUtils.errorMessage(err, 'Could not load the reports for this post.'),
					false
				);
			});
	}

	document.addEventListener('click', function (event) {
		var actionLink = event.target.closest('.reportActionLink');
		if (actionLink && document.querySelector('#reportActionFormTemplate')) {
			event.preventDefault();
			openActionWindow(actionLink);
			return;
		}

		var reportsLink = event.target.closest('.reportedPostLink');
		if (reportsLink) {
			event.preventDefault();
			openPostReportsWindow(reportsLink);
		}
	});
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', preloadVisibleReports);
	} else {
		preloadVisibleReports();
	}
})();
