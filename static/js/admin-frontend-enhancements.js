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

		function showMessage(text, isSuccess) {
			let stackContainer = document.getElementById('messageStackContainer');
			if (!stackContainer) {
				stackContainer = document.createElement('div');
				stackContainer.id = 'messageStackContainer';
				stackContainer.style.position = 'fixed';
				stackContainer.style.top = '0';
				stackContainer.style.left = '0';
				stackContainer.style.right = '0';
				stackContainer.style.width = '100%';
				stackContainer.style.zIndex = '1000';
				stackContainer.style.pointerEvents = 'none'; // Prevent layout interference
				document.body.prepend(stackContainer);
			}

			const messageContainer = document.createElement('div');
			messageContainer.className = isSuccess ? 'theading3' : 'theading';
			messageContainer.style.margin = '10px auto';
			messageContainer.style.padding = '10px';
			messageContainer.style.width = '90%';
			messageContainer.style.maxWidth = '600px';
			messageContainer.style.boxSizing = 'border-box';
			messageContainer.style.display = 'flex';
			messageContainer.style.justifyContent = 'space-between';
			messageContainer.style.alignItems = 'center';
			messageContainer.style.pointerEvents = 'auto'; // Enable close button clicks

			const messageText = document.createElement('span');
			messageText.textContent = text;

			const closeBtn = document.createElement('span');
			closeBtn.textContent = '✖';
			closeBtn.style.cursor = 'pointer';
			closeBtn.style.marginLeft = '10px';
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

						let containerToHide = null;
						let separatorToHide = null;

						if (isOp) {
							containerToHide = post.closest('.thread');
							if (containerToHide && containerToHide.nextElementSibling?.classList.contains('threadSeparator')) {
								separatorToHide = containerToHide.nextElementSibling;
							}
						} else {
							containerToHide = post.closest('.reply-container');
						}

						if (containerToHide) containerToHide.style.display = 'none';
						if (separatorToHide) separatorToHide.style.display = 'none';

						handleRequest(deleteBtn.href, function () {
							const hasResto = document.querySelector('input[name="resto"]') !== null;

							if (hasResto && isOp) {
								window.location.href = window.location.pathname.split('/').slice(0, -1).join('/') + '/';
								return;
							}

							showMessage('Post deleted successfully.', true);
						}, function () {
							if (containerToHide) containerToHide.style.display = '';
							if (separatorToHide) separatorToHide.style.display = '';
							showMessage('Failed to delete post.', false);
						}, post);

			});
		}

		if (deleteMuteBtn) {
			deleteMuteBtn.addEventListener('click', function (e) {
						e.preventDefault();

						const isOp = post.classList.contains('op');

						let containerToHide = null;
						let separatorToHide = null;

						if (isOp) {
							containerToHide = post.closest('.thread');
							if (containerToHide && containerToHide.nextElementSibling?.classList.contains('threadSeparator')) {
								separatorToHide = containerToHide.nextElementSibling;
							}
						} else {
							containerToHide = post.closest('.reply-container');
						}

						if (containerToHide) containerToHide.style.display = 'none';
						if (separatorToHide) separatorToHide.style.display = 'none';

						handleRequest(deleteMuteBtn.href, function () {
									showMessage('Post deleted and user muted.', true);
						}, function () {
									if (containerToHide) containerToHide.style.display = '';
									if (separatorToHide) separatorToHide.style.display = '';
									showMessage('Failed to delete and mute post.', false);
						}, post);
			});
		}


		if (deleteFileBtn) {
			deleteFileBtn.addEventListener('click', function (e) {
				e.preventDefault();
				handleRequest(deleteFileBtn.href, function () {
					reloadPostImgElement(post);
					removeDeleteFileButton(post);
					showMessage('File deleted successfully.', true);
				}, function () {
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
