(function() {
	/** Set a form field's value, if the form has one by that name. */
	function setField(form, name, value) {
		const field = form.elements[name];
		if (field) field.value = value || '';
	}

	/**
	 * Put the post's two targets into the form: hidden values to file against, and the labels
	 * beside the radio that say which is which. A post that kept no browser token offers no
	 * browser choice at all rather than one that would file against nothing.
	 */
	function fillTargets(form, data) {
		setField(form, 'ipPattern', data.ip_pattern);
		setField(form, 'visitorTokenHash', data.visitor_token_hash);

		const container = form.closest('.hostNoteFormContainer') || form;

		const hostLabel = container.querySelector('.hostNoteFormPattern');
		if (hostLabel) hostLabel.textContent = data.ip_pattern || '';

		const tokenLabel = container.querySelector('.hostNoteFormToken');
		if (tokenLabel) tokenLabel.textContent = data.visitor_token_label || '';

		const browserChoice = container.querySelector('.hostNoteBrowserChoice');
		if (browserChoice) browserChoice.hidden = !data.visitor_token_hash;
	}

	/** A browser note shows under different posts than a host note, so say so as it is picked. */
	function watchTargetChoice(form) {
		const description = (form.closest('.hostNoteFormContainer') || form).querySelector('.hostNoteVisibility');
		if (!description) return;

		form.addEventListener('change', function(e) {
			if (!e.target || e.target.name !== 'noteTarget') return;

			const key = e.target.value === 'browser' ? 'browserDescription' : 'hostDescription';
			description.textContent = description.dataset[key] || description.textContent;
		});
	}

	// Notes are attached to a host, so a fresh one may apply to several posts on the page. Only
	// the post it was filed from is updated in place; the rest catch up on the next load.
	function findOrCreateContainer(postEl) {
		let container = postEl.querySelector('.hostNotesContainer');
		if (container) return container;

		const below = postEl.querySelector('.belowComment');
		if (!below) return null;

		container = document.createElement('div');
		container.className = 'hostNotesContainer';

		const header = document.createElement('span');
		header.className = 'hostNotesHeader';
		header.textContent = 'Host notes';
		container.appendChild(header);

		below.appendChild(container);
		return container;
	}

	function appendNoteEntry(postEl, data) {
		const tmpl = document.getElementById('hostNoteEntryTemplate');
		if (!tmpl || !data) return;

		const clone = tmpl.content.cloneNode(true);
		const noteEl = clone.querySelector('.hostNoteOnPost');
		if (!noteEl) return;

		if (data.note_id) noteEl.dataset.hostNoteId = data.note_id;

		const text = noteEl.querySelector('.hostNoteText');
		if (text) text.innerHTML = (data.note || '').replace(/\n/g, '<br>');

		const addedBy = noteEl.querySelector('.hostNoteAddedBy');
		if (addedBy) {
			addedBy.style.color = data.mod_color;
			addedBy.textContent = ` - ${data.added_by}`;
		}

		const timestamp = noteEl.querySelector('.hostNoteTimestamp');
		if (timestamp) timestamp.textContent = `(${data.added_at})`;

		const deletionAnchor = noteEl.querySelector('.hostNoteDeletionAnchor');
		if (deletionAnchor && data.deletion_url) deletionAnchor.setAttribute('formaction', data.deletion_url);

		const editAnchor = noteEl.querySelector('.hostNoteEditAnchor');
		if (editAnchor && data.edit_url) editAnchor.href = data.edit_url;

		const container = findOrCreateContainer(postEl);
		if (container) container.appendChild(clone);
	}

	window.postWidget.registerActionHandler('leaveHostNote', function(ctx) {
		const postEl = ctx?.post || ctx?.arrow?.closest('.post');
		if (!postEl) return;

		PostActionUtils.openWindow({
			templateId: '#hostNoteCreateFormTemplate',
			title: 'Leave a host note',
			postEl,
			fields: ['postUid'],
			onOpen: ({ form }) => {
				// Neither target is in the page in a form the note can be filed against, so both
				// are fetched and written into the form's hidden fields. Nothing is typed in.
				if (!form || !ctx.url) return;

				watchTargetChoice(form);

				const infoUrl = ctx.url + (ctx.url.indexOf('?') === -1 ? '?' : '&') + 'modPage=hostInfo';
				fetch(infoUrl, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
					.then(res => res.ok ? res.json() : null)
					.then(data => data && fillTargets(form, data))
					.catch(() => {});
			},
			onSuccess: ({ res, postEl }) => {
				let data = res;
				if (typeof res === 'string') {
					try { data = JSON.parse(res); } catch {}
				}

				appendNoteEntry(postEl, data);
				showMessage('Note added for ' + ((data && data.ip_pattern) || '?'), true);
			},
			onFail: ({ err }) => {
				showMessage(PostActionUtils.errorMessage(err, 'There was an error while adding a host note.'), false);
			}
		});
	});

	document.addEventListener('click', function(e) {
		const editBtn = e.target.closest('.hostNoteEditFunction');
		if (!editBtn) return;

		const noteEl = editBtn.closest('.hostNoteOnPost');
		if (!noteEl) return;

		const postEl = noteEl.closest('.post');
		if (!postEl) return;

		const noteId = noteEl.dataset.hostNoteId || noteEl.getAttribute('data-host-note-id');
		if (!noteId) return;

		const noteText = noteEl.querySelector('.hostNoteText')?.textContent?.trim() || '';

		PostActionUtils.openWindow({
			templateId: '#hostNoteEditFormTemplate',
			title: 'Edit host note',
			postEl,
			fields: [],
			onOpen: ({ form }) => {
				if (!form) return;
				if (form.elements['noteId']) form.elements['noteId'].value = noteId;
				if (form.elements['noteText']) form.elements['noteText'].value = noteText;
			},
			onSuccess: ({ res }) => {
				let data = res;
				if (typeof res === 'string') {
					try { data = JSON.parse(res); } catch {}
				}

				if (data && typeof data.note === 'string') {
					const text = noteEl.querySelector('.hostNoteText');
					if (text) text.innerHTML = data.note.replace(/\n/g, '<br>');
				}
				showMessage('Host note edited.', true);
			},
			onFail: ({ err }) => {
				showMessage(PostActionUtils.errorMessage(err, 'There was an error while editing the host note.'), false);
			}
		});

		e.preventDefault();
	});

	document.addEventListener('click', function(e) {
		const deleteBtn = e.target.closest('.hostNoteDeleteFunction');
		if (!deleteBtn) return;

		const noteEl = deleteBtn.closest('.hostNoteOnPost');
		if (!noteEl) return;

		const deletionAnchor = noteEl.querySelector('.hostNoteDeletionAnchor');
		const deletionUrl = deletionAnchor
			? (deletionAnchor.getAttribute('formaction') || deletionAnchor.href || '')
			: '';
		if (!deletionUrl) return;

		noteEl.style.opacity = '0.5';

		const csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
		const body = new URLSearchParams();
		body.append('csrf_token', csrfToken);

		fetch(deletionUrl, { method: 'POST', credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body })
			.then(res => {
				if (res.ok) {
					noteEl.style.display = 'none';
					showMessage('Host note deleted!', true);
				} else {
					noteEl.style.opacity = '';
					showMessage('Failed to delete host note.');
				}
			})
			.catch(() => {
				noteEl.style.opacity = '';
				showMessage('Failed to delete host note (network error).');
			});

		e.preventDefault();
	});
})();
