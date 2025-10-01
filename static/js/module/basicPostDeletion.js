(function() {
	'use strict';

	function initializePostEvents(post) {
		const deleteBtn = post.querySelector('.adminDeleteFunction a');
		const deleteMuteBtn = post.querySelector('.adminDeleteMuteFunction a');
		const deleteFileBtn = post.querySelector('.adminDeleteFileFunction a');

		function handleRequest(url, onSuccess, onFailure, targetElement) {
			fetch(url, {
				method: 'GET',
				credentials: 'same-origin',
				headers: {
					'X-Requested-With': 'XMLHttpRequest'
				}
			})
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

		function reloadPostImgElement(post) {
			if (!post) return;

			const postId = post.id;
			if (!postId) return;

			fetch(window.location.href, {
				method: 'GET',
				credentials: 'same-origin',
				headers: {
					'X-Requested-With': 'XMLHttpRequest'
				}
			})
				.then(response => response.text())
				.then(html => {
					const parser = new DOMParser();
					const doc = parser.parseFromString(html, 'text/html');
					const newPost = doc.querySelector(`#${postId}`);
					if (!newPost) return;

					// Replace .filesize content
					const newFilesize = newPost.querySelector('.filesize');
					const oldFilesize = post.querySelector('.filesize');
					if (newFilesize && oldFilesize) {
						oldFilesize.innerHTML = newFilesize.innerHTML;
					}

					// Replace the thumbnail anchor (sibling of .filesize)
					const oldThumbAnchor = post.querySelector('a > img.postimg')?.parentElement;
					const newThumbAnchor = newPost.querySelector('a > img.postimg')?.parentElement;

					if (oldThumbAnchor) {
						oldThumbAnchor.remove();
					}

					if (newThumbAnchor) {
						const insertAfter = post.querySelector('.filesize');
						if (insertAfter) {
							insertAfter.insertAdjacentElement('afterend', newThumbAnchor.cloneNode(true));
						}
					}
				})
				.catch(error => console.error('Failed to reload post image and filesize:', error));
		}

		function removeDeleteFileButton(post) {
			if (!post) return;

			const deleteFileContainer = post.querySelector('.adminDeleteFileFunction');
			if (deleteFileContainer) {
				deleteFileContainer.remove();
			}
		}

		if (deleteBtn) {
			deleteBtn.addEventListener('click', function (e) {
				e.preventDefault();

				const isOp = post.classList.contains('op');

				let containerToHandle = null;
				let separatorToHandle = null;

				if (isOp) {
					containerToHandle = post.closest('.thread');
					if (containerToHandle && containerToHandle.nextElementSibling?.classList.contains('threadSeparator')) {
						separatorToHandle = containerToHandle.nextElementSibling;
					}
				} else {
					containerToHandle = post.closest('.reply-container');
				}

				if (containerToHandle) containerToHandle.classList.add('pendingDeletion');
				if (separatorToHandle) separatorToHandle.classList.add('pendingDeletion');

				handleRequest(deleteBtn.href, function () {
					const hasResto = document.querySelector('input[name="resto"]') !== null;

					if (hasResto && isOp) {
						window.location.href = window.location.pathname.split('/').slice(0, -1).join('/') + '/';
						return;
					}

					if (containerToHandle) {
						containerToHandle.classList.remove('pendingDeletion');
						containerToHandle.classList.add('hidden');
					}
					if (separatorToHandle) {
						separatorToHandle.classList.remove('pendingDeletion');
						separatorToHandle.classList.add('hidden');
					}

					showMessage('Post deleted successfully.', true);
				}, function () {
					if (containerToHandle) containerToHandle.classList.remove('pendingDeletion');
					if (separatorToHandle) separatorToHandle.classList.remove('pendingDeletion');
					showMessage('Failed to delete post.', false);
				}, post);
			});
		}

		if (deleteMuteBtn) {
			deleteMuteBtn.addEventListener('click', function (e) {
				e.preventDefault();

				const isOp = post.classList.contains('op');

				let containerToHandle = null;
				let separatorToHandle = null;

				if (isOp) {
					containerToHandle = post.closest('.thread');
					if (containerToHandle && containerToHandle.nextElementSibling?.classList.contains('threadSeparator')) {
						separatorToHandle = containerToHandle.nextElementSibling;
					}
				} else {
					containerToHandle = post.closest('.reply-container');
				}

				if (containerToHandle) containerToHandle.classList.add('pendingDeletion');
				if (separatorToHandle) separatorToHandle.classList.add('pendingDeletion');

				handleRequest(deleteMuteBtn.href, function () {
					if (containerToHandle) {
						containerToHandle.classList.remove('pendingDeletion');
						containerToHandle.classList.add('hidden');
					}
					if (separatorToHandle) {
						separatorToHandle.classList.remove('pendingDeletion');
						separatorToHandle.classList.add('hidden');
					}

					showMessage('Post deleted and user muted.', true);
				}, function () {
					if (containerToHandle) containerToHandle.classList.remove('pendingDeletion');
					if (separatorToHandle) separatorToHandle.classList.remove('pendingDeletion');
					showMessage('Failed to delete and mute post.', false);
				}, post);
			});
		}


		if (deleteFileBtn) {
			deleteFileBtn.addEventListener('click', function (e) {
				e.preventDefault();
				const img = post.querySelector('img.postimg');
				if (img) img.classList.add('pendingDeletion');
				handleRequest(deleteFileBtn.href, function () {
					reloadPostImgElement(post);
					removeDeleteFileButton(post);
					showMessage('File deleted successfully.', true);
				}, function () {
					if (img) img.classList.remove('pendingDeletion');
					showMessage('Failed to delete file.', false);
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
