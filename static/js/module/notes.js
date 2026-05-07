(function() {
	window.postWidget.registerActionHandler('leaveNote', function(ctx) {
		const postEl = ctx?.post || ctx?.arrow?.closest('.post');
		if (!postEl) return;

		PostActionUtils.openWindow({
			templateId: '#noteCreateFormTemplate',
			title: 'Leave a note',
			postEl,
			fields: ['postUid', 'noteId', 'noteText', 'post_number'],
			onSuccess: ({ res, form, postEl }) => {
				// Parse JSON if needed
				let data = res;
				if (typeof res === 'string') {
					try { data = JSON.parse(res); } catch {}
				}

				// Use the template to create the note entry
				const tmpl = document.getElementById('noteEntryTemplate');
				const container = postEl.querySelector('.staffNotesContainer');
				if (tmpl && container && data) {
					const clone = tmpl.content.cloneNode(true);

					// Fill in the template fields
					const noteOnPost = clone.querySelector('.noteOnPost');

					// Fill note text in a span with class "noteText"
					let noteTextSpan = noteOnPost.querySelector('.noteText');
					if (!noteTextSpan) {
						noteTextSpan = document.createElement('span');
						noteTextSpan.className = 'noteText';
						// Insert as the first child of noteOnPost
						noteOnPost.insertBefore(noteTextSpan, noteOnPost.firstChild);
					}
					noteTextSpan.innerHTML = data.note.replace(/\n/g, '<br>');

					const addedBy = clone.querySelector('.noteAddedBy');
					if (addedBy) {
						addedBy.style.color = data.mod_color;
						addedBy.textContent = ` - ${data.added_by}`;
					}

					const timestamp = clone.querySelector('.noteTimestamp');
					if (timestamp) {
						timestamp.textContent = `(${data.added_at})`;
					}

					const deletionAnchor = clone.querySelector('.noteDeletionAnchor');
					if(deletionAnchor) {
						deletionAnchor.href = data.deletion_url;
					}

					const editAnchor = clone.querySelector('.noteEditAnchor');
					if(editAnchor) {
						editAnchor.href = data.edit_url;
					}

					// After: const noteOnPost = clone.querySelector('.noteOnPost');
					if (noteOnPost && data.note_id) {
						noteOnPost.dataset.noteId = data.note_id;
					}

					container.appendChild(clone);
				}

				// Show message with post number from JSON
				showMessage("Note added to post No. " + (data && data.post_number ? data.post_number : '?'), true);
			},
			onFail: ({ err, form, postEl }) => {
				showMessage("There was an error while adding a note.", false);
			}
		});
	})

	document.addEventListener('click', function(e) {
		// Check if the clicked element or its parent is a noteEditFunction
		let editBtn = e.target.closest('.noteEditFunction');
		if (!editBtn) return;

		// Find the closest .noteOnPost (the note container)
		const noteOnPost = editBtn.closest('.noteOnPost');
		if (!noteOnPost) return;

		// Find the parent post element (with .post)
		const postEl = noteOnPost.closest('.post');
		if (!postEl) return;

		// Get the note ID from data-note-id (could be data-noteid or data-note-id)
		const noteId = noteOnPost.dataset.noteId || noteOnPost.getAttribute('data-note-id');
		if (!noteId) return;

		// Optionally, get the postUid and post_number from postEl or noteOnPost if needed
		const postUid = postEl.dataset.postUid;
		const postNumber = postEl.querySelector('.postnum .qu')?.textContent?.trim();

		// Get the note text (from the noteOnPost)
		const noteText = noteOnPost.querySelector('.noteText')?.textContent?.trim() || '';

		// Open the edit window
		PostActionUtils.openWindow({
			templateId: '#noteEditFormTemplate',
			title: 'Edit note',
			postEl,
			fields: ['post_number'], // We'll assign manually below
			onOpen: ({ form, win }) => {
				// Fill fields manually since we have the data
				if (form) {
					if (form.elements['noteId']) form.elements['noteId'].value = noteId;
					if (form.elements['postUid'] && postUid) form.elements['postUid'].value = postUid;
					if (form.elements['noteText'] && noteText) form.elements['noteText'].value = noteText;
				}
			},
			onSuccess: ({ res, form, postEl }) => {
				// Parse JSON if needed
				let data = res;
				if (typeof res === 'string') {
					try { data = JSON.parse(res); } catch {}
				}
				// Update the note text in the original noteOnPost
				if (data && typeof data.note === 'string') {
					// Update the first text node (note text)
					const noteTextSpan = noteOnPost.querySelector('.noteText');
					if (noteTextSpan) {
						noteTextSpan.textContent = data.note;
						noteTextSpan.innerHTML = data.note.replace(/\n/g, '<br>');
					}
				}
				showMessage("Note edited for post No. " + (postNumber || '?'), true);
			},
			onFail: ({ err, form, postEl }) => {
				showMessage("There was an error while editing note.", false);
			}
		});

		e.preventDefault();
	});

	

	// Listener for deleting notes
	document.addEventListener('click', function(e) {
		let deleteBtn = e.target.closest('.noteDeleteFunction');
		if (!deleteBtn) return;

		const noteOnPost = deleteBtn.closest('.noteOnPost');
		if (!noteOnPost) return;

		// Find the deletion URL — .noteDeletionAnchor is a <button>, so read formaction attribute
		const deletionAnchor = noteOnPost.querySelector('.noteDeletionAnchor');
		const deletionUrl = deletionAnchor
			? (deletionAnchor.getAttribute('formaction') || deletionAnchor.href || '')
			: '';
		if (!deletionUrl) return;

		// Reduce opacity to indicate pending deletion
		noteOnPost.style.opacity = '0.5';

		const csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
		const body = new URLSearchParams();
		body.append('csrf_token', csrfToken);

		fetch(deletionUrl, { method: 'POST', credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body })
			.then(res => {
				if (res.status === 200) {
					noteOnPost.style.display = 'none';
					showMessage('Note deleted!', true);
				} else if (res.status === 500) {
					noteOnPost.style.opacity = '';
					showMessage('Failed to delete note.');
				} else {
					noteOnPost.style.opacity = '';
					showMessage('Failed to delete note.');
				}
			})
			.catch(() => {
				noteOnPost.style.opacity = '';
				showMessage('Failed to delete note (network error).');
			});

		e.preventDefault();
	});

})();