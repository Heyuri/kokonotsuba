(function() {
	'use strict';

	function initializePostEvents(post) {
		const deleteBtn = post.querySelector('.adminDeleteFunction a');
		const deleteMuteBtn = post.querySelector('.adminDeleteMuteFunction a');
		const deleteFileBtn = post.querySelector('.adminDeleteFileFunction a');
		const autosageBtn = post.querySelector('.adminAutosageFunction a');
		const lockBtn = post.querySelector('.adminLockFunction a');
		const stickyBtn = post.querySelector('.adminStickyFunction a');

		function reloadPostElement() {
			const delform = document.getElementById('delform');
			if (!delform) {
				console.warn('Could not locate #delform.');
				showMessage('Reload failed: no delform container.', false);
				return;
			}

			fetch(window.location.href, { credentials: 'same-origin' })
				.then(res => res.text())
				.then(html => {
					const parser = new DOMParser();
					const doc = parser.parseFromString(html, 'text/html');
					const updatedForm = doc.getElementById('delform');

					if (!updatedForm) {
						console.warn('No #delform found in fetched HTML.');
						showMessage('Reload failed: delform not found.', false);
						return;
					}

					delform.innerHTML = updatedForm.innerHTML;

					delform.querySelectorAll('.post').forEach(function (updatedPost) {
						initializePostEvents(updatedPost); // reattach events
					});
				})
				.catch(err => {
					console.error('Fetch or DOM parse failed:', err);
					showMessage('Failed to reload form.', false);
				});
		}


		function handleRequest(url, onSuccess, onFailure, targetElement) {
			fetch(url, { method: 'GET', credentials: 'same-origin' })
						.then(response => {
									if (response.ok) {
												onSuccess();
									} else {
												onFailure();
									}
						})
						.catch(error => {
									console.error('Request failed:', error);
									onFailure();
						});
		}

		function showMessage(text, isSuccess) {
			let stackContainer = document.getElementById('messageStackContainer');
			if (!stackContainer) {
						stackContainer = document.createElement('div');
						stackContainer.id = 'messageStackContainer';
						stackContainer.style.position = 'fixed';
						stackContainer.style.top = '0';
						stackContainer.style.left = '0';
						stackContainer.style.right = '0';
						stackContainer.style.display = 'flex';
						stackContainer.style.flexDirection = 'column';
						stackContainer.style.alignItems = 'center';
						stackContainer.style.zIndex = '1000';
						document.body.prepend(stackContainer);
			}

			const messageContainer = document.createElement('div');
			messageContainer.className = isSuccess ? 'theading3' : 'theading';
			messageContainer.style.margin = '4px';
			messageContainer.style.padding = '10px';
			messageContainer.style.display = 'flex';
			messageContainer.style.justifyContent = 'space-between';
			messageContainer.style.alignItems = 'center';
			messageContainer.style.width = '90%';
			messageContainer.style.maxWidth = '600px';
			messageContainer.style.boxSizing = 'border-box';

			const messageText = document.createElement('span');
			messageText.textContent = text;

			const closeBtn = document.createElement('span');
			closeBtn.textContent = '✖';
			closeBtn.style.cursor = 'pointer';
			closeBtn.addEventListener('click', function () {
						messageContainer.remove();
			});

			messageContainer.appendChild(messageText);
			messageContainer.appendChild(closeBtn);
			stackContainer.appendChild(messageContainer);

			setTimeout(() => {
						if (messageContainer.parentNode) {
									messageContainer.remove();
						}
			}, 5000);
		}

		if (deleteBtn) {
			deleteBtn.addEventListener('click', function (e) {
						e.preventDefault();

						const isOp = post.classList.contains('op');
						const hasResto = document.querySelector('input[name="resto"]') !== null;

						let containerToHide = null;

						if (isOp) {
									containerToHide = post.closest('.thread');
						} else {
									containerToHide = post.closest('.reply-container');
						}

						if (containerToHide) containerToHide.style.display = 'none';

						handleRequest(deleteBtn.href, function () {
									reloadPostElement();
									showMessage('Post deleted successfully.', true);
						}, function () {
									if (containerToHide) containerToHide.style.display = '';
									showMessage('Failed to delete post.', false);
						}, post);
			});
		}
		
		if (deleteMuteBtn) {
			deleteMuteBtn.addEventListener('click', function (e) {
						e.preventDefault();

						const isOp = post.classList.contains('op');
						const hasResto = document.querySelector('input[name="resto"]') !== null;

						let containerToHide = null;

						if (isOp) {
									containerToHide = post.closest('.thread');
						} else {
									containerToHide = post.closest('.reply-container');
						}

						if (containerToHide) containerToHide.style.display = 'none';

						handleRequest(deleteMuteBtn.href, function () {
									reloadPostElement();
									showMessage('Post deleted and user muted.', true);
						}, function () {
									if (containerToHide) containerToHide.style.display = '';
									showMessage('Failed to delete and mute post.', false);
						}, post);
			});
		}

		if (deleteFileBtn) {
			deleteFileBtn.addEventListener('click', function (e) {
				e.preventDefault();
				handleRequest(deleteFileBtn.href, function () {
					reloadPostElement();
					showMessage('File deleted successfully.', true);
				}, function () {
					showMessage('Failed to delete file.', false);
				});
			});
		}

		if (autosageBtn) {
			autosageBtn.addEventListener('click', function (e) {
				e.preventDefault();
				handleRequest(autosageBtn.href, function () {
					reloadPostElement();
					showMessage('Autosage status updated.', true);
				}, function () {
					showMessage('Failed to update autosage status.', false);
				});
			});
		}

		if (lockBtn) {
			lockBtn.addEventListener('click', function (e) {
				e.preventDefault();
				handleRequest(lockBtn.href, function () {
					reloadPostElement();
					showMessage('Lock status updated.', true);
				}, function () {
					showMessage('Failed to update lock status.', false);
				});
			});
		}

		if (stickyBtn) {
			stickyBtn.addEventListener('click', function (e) {
				e.preventDefault();
				handleRequest(stickyBtn.href, function () {
					reloadPostElement();
					showMessage('Sticky status updated.', true);
				}, function () {
					showMessage('Failed to update sticky status.', false);
				});
			});
		}
	}


	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('.post').forEach(function (post) {
			initializePostEvents(post);
		});
	});


})();
