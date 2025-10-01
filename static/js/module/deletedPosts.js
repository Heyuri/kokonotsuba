(function() {
	'use strict';

	// Function to handle request
	function handleRequest(url, formData, onSuccess, onFailure) {
		const urlEncoded = new URLSearchParams();
		for (const [k, v] of formData.entries()) {
			urlEncoded.append(k, v);
		}

		fetch(url, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'X-Requested-With': 'XMLHttpRequest',
				'Content-Type': 'application/x-www-form-urlencoded'
			},
			body: urlEncoded
		})
			.then(response => {
				if (response.ok || (response.status >= 200 && response.status < 300)) {
					return response.text();
				} else {
					throw new Error('Non-2xx response received');
				}
			})
			.then(() => {
				onSuccess();
			})
			.catch(() => {
				onFailure();
			});
	}

	// Function to collect form data
	function createFormData(button) {
		const form = button.closest('form');
		const formData = new FormData(form);

		const deletedPostIdInput = form.querySelector('input[name="deletedPostId"]');
		if (deletedPostIdInput) {
			formData.delete('deletedPostId');
			formData.append('deletedPostId', deletedPostIdInput.value);
		}

		if (button.name) {
			formData.append(button.name, button.value || '');
		}

		return formData;
	}

	function hideDeletedPost(postContainer) {
		if (postContainer) {
			// also remove a trailing threadSeparator <hr> if it follows this container
			const nextEl = postContainer.nextElementSibling;
			if (nextEl && nextEl.matches('hr.threadSeparator')) {
				// clear pending state on success
				nextEl.classList.remove('pendingDeletion');
				nextEl.style.display = 'none';
			}

			// clear pending state on success
			postContainer.classList.remove('pendingDeletion');
			postContainer.style.display = 'none';
		}
	}

	// Ensure the script applies to any 'deletedPostContainer'
	if (document.querySelector('.deletedPostContainer')) {
		const purgeBtnList = document.querySelectorAll('.adminPurgeFunction');
		const restoreBtnList = document.querySelectorAll('.adminRestoreFunction');

		// Handle Purge Button Click
		purgeBtnList.forEach(function(purgeBtn) {
			purgeBtn.addEventListener('click', function (e) {
				e.preventDefault();
				const formData = createFormData(purgeBtn);

				// Use the form's action ATTRIBUTE to avoid RadioNodeList shadowing
				const form = purgeBtn.closest('form');
				const url = form ? form.getAttribute('action') : purgeBtn.formAction;

				// mark as pending (container + possible trailing separator)
				const postContainer = purgeBtn.closest('.deletedPostContainer');
				const sep = postContainer && postContainer.nextElementSibling && postContainer.nextElementSibling.matches('hr.threadSeparator')
					? postContainer.nextElementSibling
					: null;
				if (postContainer) postContainer.classList.add('pendingDeletion');
				if (sep) sep.classList.add('pendingDeletion');

				handleRequest(url, formData, function () {
					// hide it with css
					hideDeletedPost(postContainer);
					showMessage('Post purged successfully.', true);
				}, function () {
					// revert pending state on failure
					if (postContainer) postContainer.classList.remove('pendingDeletion');
					if (sep) sep.classList.remove('pendingDeletion');
					showMessage('Failed to purge post.', false);
				});
			});
		});

		// Handle Restore Button Click
		restoreBtnList.forEach(function(restoreBtn) {
			restoreBtn.addEventListener('click', function (e) {
                e.preventDefault();
				const formData = createFormData(restoreBtn);

				// Use the form's action ATTRIBUTE to avoid RadioNodeList shadowing
				const form = restoreBtn.closest('form');
				const url = form ? form.getAttribute('action') : restoreBtn.formAction;

				// mark as pending (container + possible trailing separator)
				const postContainer = restoreBtn.closest('.deletedPostContainer');
				const sep = postContainer && postContainer.nextElementSibling && postContainer.nextElementSibling.matches('hr.threadSeparator')
					? postContainer.nextElementSibling
					: null;
				if (postContainer) postContainer.classList.add('pendingDeletion');
				if (sep) sep.classList.add('pendingDeletion');

				handleRequest(url, formData, function () {
					// hide it with css (restore should also hide)
					hideDeletedPost(postContainer);
					showMessage('Post restored successfully.', true);
				}, function () {
					// revert pending state on failure
					if (postContainer) postContainer.classList.remove('pendingDeletion');
					if (sep) sep.classList.remove('pendingDeletion');
					showMessage('Failed to restore post.', false);
				});
			});
		});
	}
})();
