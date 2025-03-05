(function() {
	'use strict';

	// Process a file (from paste, drag, or file selection) with the uniform UI
	function processImageFile(blob) {
		// Get the extension from the MIME type; if not found, try extracting from the file name
		var fileExtension = getFileExtension(blob.type);
		if (!fileExtension && blob.name && blob.name.indexOf('.') !== -1) {
			fileExtension = '.' + blob.name.split('.').pop();
		}
		var fileInput = document.querySelector('input[type="file"][name="upfile"]');
		if (fileInput) {
			var originalFilename = blob.name ? blob.name.split('.').slice(0, -1).join('.') : 'image';

			// Create or update the filename input field
			var filenameInput = document.getElementById('filename-input');
			if (!filenameInput) {
				filenameInput = document.createElement('input');
				filenameInput.type = 'text';
				filenameInput.id = 'filename-input';
				filenameInput.style.marginLeft = '10px';
				fileInput.parentNode.insertBefore(filenameInput, fileInput.nextSibling);
			}
			filenameInput.value = originalFilename;
			filenameInput.currentBlob = blob;
			filenameInput.currentExtension = fileExtension;

			// Create or update the file size element
			var fileSizeElement = document.getElementById('file-size');
			if (!fileSizeElement) {
				fileSizeElement = document.createElement('div');
				fileSizeElement.id = 'file-size';
				fileSizeElement.style.marginTop = '10px';
				fileInput.parentNode.insertBefore(fileSizeElement, filenameInput.nextSibling);
			}
			fileSizeElement.textContent = `File size: ${(blob.size / 1024).toFixed(2)} KB`;

			// Create or update the preview element only for allowed types
			var allowedPreviewTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/bmp', 'image/webp', 'image/svg+xml'];
			var preview = null;
			if (allowedPreviewTypes.includes(blob.type)) {
				preview = document.getElementById('file-preview');
				if (!preview) {
					preview = document.createElement('img');
					preview.id = 'file-preview';
					preview.style.display = 'block';
					preview.style.marginTop = '10px';
					preview.style.maxWidth = '100%';
					preview.style.maxHeight = '250px';
					preview.style.border = '1px solid #880000';
					fileInput.parentNode.insertBefore(preview, fileSizeElement.nextSibling);
				}
			} else {
				// Remove any existing preview if the file type is not allowed
				var existingPreview = document.getElementById('file-preview');
				if (existingPreview) {
					existingPreview.remove();
				}
			}

			// If the file is a WebP, add a "Convert WebP to PNG" button
			if (blob.type === 'image/webp') {
				createConvertButton(blob, fileInput, filenameInput, preview, fileSizeElement);
			} else {
				removeConvertButton();
			}

			// Update the file input when the filename input changes
			filenameInput.addEventListener('input', function() {
				updateFileInput(filenameInput.currentBlob, fileInput, filenameInput.value, filenameInput.currentExtension, preview);
			});

			// Set the initial file input value
			updateFileInput(blob, fileInput, filenameInput.value, fileExtension, preview);
		}
	}

	// Handle pasted files
	function handlePaste(event) {
		var items = (event.clipboardData || event.originalEvent.clipboardData).items;
		for (var index in items) {
			var item = items[index];
			if (item.kind === 'file') {
				var blob = item.getAsFile();
				processImageFile(blob);
			}
		}
	}

	// Handle dropped files on the file input
	function handleDrop(event) {
		event.preventDefault();
		var files = event.dataTransfer.files;
		for (var i = 0; i < files.length; i++) {
			var file = files[i];
			if (file.type.startsWith('image/')) {
				processImageFile(file);
				break; // Process only the first image file dropped
			}
		}
	}

	// Attach drag-and-drop events to the existing file input
	function attachDragAndDropToFileInput() {
		var fileInput = document.querySelector('input[type="file"][name="upfile"]');
		if (fileInput) {
			fileInput.addEventListener('dragover', function(e) {
				e.preventDefault();
				e.stopPropagation();
				fileInput.style.backgroundColor = '#f0f0f0';
			});
			fileInput.addEventListener('dragleave', function(e) {
				e.preventDefault();
				e.stopPropagation();
				fileInput.style.backgroundColor = '';
			});
			fileInput.addEventListener('drop', function(e) {
				e.preventDefault();
				e.stopPropagation();
				fileInput.style.backgroundColor = '';
				handleDrop(e);
			});
		}
	}

	// Attach a change listener to process files selected via the file system
	function attachFileInputChangeListener() {
		var fileInput = document.querySelector('input[type="file"][name="upfile"]');
		if (fileInput) {
			fileInput.addEventListener('change', function(e) {
				// Skip if the change event was triggered programmatically
				if (fileInput.ignoreChange) return;
				if (fileInput.files && fileInput.files.length > 0) {
					processImageFile(fileInput.files[0]);
				}
			});
		}
	}

	// Create the "Convert WebP to PNG" button
	function createConvertButton(blob, fileInput, filenameInput, preview, fileSizeElement) {
		var convertButton = document.getElementById('convert-to-png-button');
		if (!convertButton) {
			convertButton = document.createElement('div');
			convertButton.id = 'convert-to-png-button';
			convertButton.textContent = 'Convert WebP to PNG';
			convertButton.style.border = '1px solid #ccc';
			convertButton.style.display = 'inline-block';
			convertButton.style.marginTop = '10px';
			convertButton.style.padding = '5px 10px';
			convertButton.style.cursor = 'pointer';
			convertButton.style.transition = 'opacity 0.3s';
			convertButton.style.clear = 'both';

			// Wrap the button for proper block-level display
			var buttonWrapper = document.createElement('div');
			buttonWrapper.style.marginTop = '5px';
			buttonWrapper.style.textAlign = 'left';
			buttonWrapper.appendChild(convertButton);

			filenameInput.parentNode.insertBefore(buttonWrapper, filenameInput.nextSibling);

			convertButton.addEventListener('click', function(e) {
				e.preventDefault();
				fadeOutAndConvert(filenameInput.currentBlob, fileInput, filenameInput, preview, fileSizeElement, convertButton);
			});
		}
	}

	// Fade out the convert button before conversion
	function fadeOutAndConvert(blob, fileInput, filenameInput, preview, fileSizeElement, button) {
		button.style.opacity = '0.5';
		button.style.pointerEvents = 'none';

		setTimeout(() => {
			convertWebPToPNG(blob, fileInput, filenameInput, preview, fileSizeElement, button);
		}, 300);
	}

	// Convert a WebP image to PNG
	function convertWebPToPNG(blob, fileInput, filenameInput, preview, fileSizeElement, button) {
		var canvas = document.createElement('canvas');
		var ctx = canvas.getContext('2d');
		var img = new Image();

		img.onload = function() {
			canvas.width = img.width;
			canvas.height = img.height;
			ctx.drawImage(img, 0, 0);
			canvas.toBlob(function(pngBlob) {
				var newFileName = filenameInput.value || 'image';
				filenameInput.currentBlob = pngBlob;
				filenameInput.currentExtension = '.png';
				updateFileInput(pngBlob, fileInput, newFileName, '.png', preview);
				fileSizeElement.textContent = `File size: ${(pngBlob.size / 1024).toFixed(2)} KB`;
				removeConvertButton();
			}, 'image/png');
		};

		var reader = new FileReader();
		reader.onload = function(e) {
			img.src = e.target.result;
		};
		reader.readAsDataURL(blob);
	}

	// Update the file input with the renamed file and update the preview
	function updateFileInput(blob, fileInput, fileName, fileExtension, preview) {
		var renamedFile = new File([blob], fileName + fileExtension, { type: blob.type });
		var dataTransfer = new DataTransfer();
		dataTransfer.items.add(renamedFile);
		fileInput.files = dataTransfer.files;

		// Set a flag to avoid re-triggering the change event handler
		fileInput.ignoreChange = true;
		var changeEvent = new Event('change');
		fileInput.dispatchEvent(changeEvent);
		fileInput.ignoreChange = false;

		var reader = new FileReader();
		reader.onload = function(e) {
			if (preview) {
				preview.src = e.target.result;
			}
		};
		reader.readAsDataURL(blob);
	}

	// Helper: get file extension from MIME type
	function getFileExtension(mimeType) {
		var extension = "";
		switch (mimeType) {
			case "image/jpeg":
				extension = ".jpg";
				break;
			case "image/png":
				extension = ".png";
				break;
			case "image/gif":
				extension = ".gif";
				break;
			case "image/bmp":
				extension = ".bmp";
				break;
			case "image/webp":
				extension = ".webp";
				break;
			case "image/svg+xml":
				extension = ".svg";
				break;
			default:
				extension = "";
		}
		return extension;
	}

	// Remove the WebP-to-PNG conversion button
	function removeConvertButton() {
		var convertButton = document.getElementById('convert-to-png-button');
		if (convertButton) {
			convertButton.remove();
		}
	}

	// Clear all added elements and reset the file input
	function handleClearButtonClick() {
		var fileInput = document.querySelector('input[type="file"][name="upfile"]');
		if (fileInput) {
			fileInput.value = '';
		}
		var filenameInput = document.getElementById('filename-input');
		if (filenameInput) {
			filenameInput.remove();
		}
		var fileSizeElement = document.getElementById('file-size');
		if (fileSizeElement) {
			fileSizeElement.remove();
		}
		var preview = document.getElementById('file-preview');
		if (preview) {
			preview.remove();
		}
		removeConvertButton();
	}

	// Attach event listeners
	document.addEventListener('paste', handlePaste);
	attachDragAndDropToFileInput();
	attachFileInputChangeListener();

	// Try to find the clear button immediately
	var clearButton = document.querySelector('a[href="javascript:void(0);"][onclick*="$id(\'upfile\').value=\'\';"]');
	if (clearButton) {
		// If the clear button exists, attach the click event listener immediately
		clearButton.addEventListener('click', handleClearButtonClick);
	} else {
		// If not found, use a MutationObserver to wait for it to be added to the DOM
		var observer = new MutationObserver(function(mutations, obs) {
			var clearButton = document.querySelector('a[href="javascript:void(0);"][onclick*="$id(\'upfile\').value=\'\';"]');
			if (clearButton) {
				clearButton.addEventListener('click', handleClearButtonClick);
				obs.disconnect(); // Stop observing after the clear button is found
			}
		});
		observer.observe(document, { childList: true, subtree: true });
	}
})();