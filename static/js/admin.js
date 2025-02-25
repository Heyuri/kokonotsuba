(function() {
    'use strict';

    // Improved Admin Mode Check: Check if any <b> tag contains the text "Administrator mode"
    const adminModeElement = [...document.querySelectorAll('b')].some(b => b.textContent.trim().includes('Administrator mode'));
    if (!adminModeElement) return; // Exit if not in Administrator mode

    let hoverTimeout;
    let currentLink = null;

    // Create a div to hold the thumbnail
    const thumbnailDiv = document.createElement('div');
    thumbnailDiv.style.position = 'absolute';
    thumbnailDiv.style.pointerEvents = 'none';
    thumbnailDiv.style.zIndex = '1000';
    thumbnailDiv.style.display = 'none'; // Initially hidden
    document.body.appendChild(thumbnailDiv);

    // Function to show the thumbnail at the correct position
    function showThumbnail(e, src) {
        const img = new Image();
        img.src = src;

        img.onload = function() {
            thumbnailDiv.innerHTML = '';
            thumbnailDiv.appendChild(img);
            // Calculate position based on image dimensions
            thumbnailDiv.style.left = `${e.pageX - img.width}px`;
            thumbnailDiv.style.top = `${e.pageY - img.height}px`;
            thumbnailDiv.style.display = 'block';
        };
    }

    // Function to hide the thumbnail
    function hideThumbnail() {
        clearTimeout(hoverTimeout);
        hoverTimeout = setTimeout(() => {
            if (!thumbnailDiv.matches(':hover') && !currentLink?.matches(':hover')) {
                thumbnailDiv.style.display = 'none';
            }
        }, 0); // Delay can be adjusted to prevent flicker
    }

    // Function to determine the base path for images
    function determineImagePath(link) {
        const url = new URL(link.href, window.location.origin); // Ensure it handles protocol-relative URLs
        const pathParts = url.pathname.split('/');
        pathParts.pop(); // Remove the file name
        const basePath = pathParts.join('/');
        return `${url.origin}${basePath}/`; // Return the base path for images
    }

    // Attach event listeners to relevant file links
    document.querySelectorAll('td > a[href$=".gif"], td > a[href$=".mp4"], td > a[href$=".webm"], td > a[href$=".jpg"], td > a[href$=".png"]').forEach(link => {
        link.addEventListener('mouseenter', function(e) {
            currentLink = link; // Set current link for hover check
            clearTimeout(hoverTimeout);
            
            // Extract filename without extension, adjusted for non-numeric filenames
            const filename = link.href.match(/([^/]+)\.\w+$/)[1];
            const basePath = determineImagePath(link);
            const imgUrlJpg = `${link.href.startsWith('//') ? window.location.protocol : ''}${basePath}${filename}s.jpg`;
            const imgUrlPng = `${link.href.startsWith('//') ? window.location.protocol : ''}${basePath}${filename}s.png`;

            // Check if the JPG thumbnail exists
            fetch(imgUrlJpg).then(response => {
                if (response.ok) {
                    showThumbnail(e, imgUrlJpg);
                } else {
                    // Check if the PNG thumbnail exists
                    fetch(imgUrlPng).then(response => {
                        if (response.ok) {
                            showThumbnail(e, imgUrlPng);
                        }
                    });
                }
            });
        });

        link.addEventListener('mousemove', function(e) {
            if (thumbnailDiv.style.display === 'block') {
                thumbnailDiv.style.left = `${e.pageX - thumbnailDiv.offsetWidth}px`;
                thumbnailDiv.style.top = `${e.pageY - thumbnailDiv.offsetHeight}px`;
            }
        });

        link.addEventListener('mouseleave', hideThumbnail);
    });

    // Additional listeners for scroll events and document-wide mousemove
    window.addEventListener('scroll', hideThumbnail);
    document.addEventListener('mousemove', function(e) {
        if (!thumbnailDiv.matches(':hover') && !currentLink?.matches(':hover')) {
            hideThumbnail();
        }
    });

	const roleSelectAllLink = document.getElementById('roleselectall');

	if (roleSelectAllLink) {
		const updateLinkText = (link, checkboxes) => {
			const allChecked = Array.from(checkboxes).every(checkbox => checkbox.checked);
			link.innerHTML = allChecked ? '[<a>Unselect All</a>]' : '[<a>Select All</a>]';
		};

		roleSelectAllLink.addEventListener('click', function(event) {
			if (event.target.tagName === 'A') {
				event.preventDefault();
	
				const roleRow = document.getElementById('rolerow');
				if (roleRow) {
					const checkboxes = roleRow.querySelectorAll('input[type="checkbox"]');
					const allChecked = Array.from(checkboxes).every(checkbox => checkbox.checked);
	
					if (allChecked) {
						checkboxes.forEach(checkbox => checkbox.checked = false);
					} else {
						checkboxes.forEach(checkbox => checkbox.checked = true);
					}
					updateLinkText(roleSelectAllLink, checkboxes);
				}
			}
		});

		const roleRow = document.getElementById('rolerow');
		if (roleRow) {
			const checkboxes = roleRow.querySelectorAll('input[type="checkbox"]');
	
			checkboxes.forEach(checkbox => {
				checkbox.addEventListener('change', () => {
					updateLinkText(roleSelectAllLink, checkboxes);
				});
			});
	
			updateLinkText(roleSelectAllLink, checkboxes);
		}
	}

	const boardSelectAllLink = document.getElementById('boardselectall');

	if (boardSelectAllLink) {
		const updateLinkText = (link, checkboxes) => {
			const allChecked = Array.from(checkboxes).every(checkbox => checkbox.checked);
			link.innerHTML = allChecked ? '[<a>Unselect All</a>]' : '[<a>Select All</a>]';
		};

		boardSelectAllLink.addEventListener('click', function(event) {
			if (event.target.tagName === 'A') {
				event.preventDefault();
	
				const boardRow = document.getElementById('boardrow');
				if (boardRow) {
					const checkboxes = boardRow.querySelectorAll('input[type="checkbox"]');
					const allChecked = Array.from(checkboxes).every(checkbox => checkbox.checked);
	
					if (allChecked) {
						checkboxes.forEach(checkbox => checkbox.checked = false);
					} else {
						checkboxes.forEach(checkbox => checkbox.checked = true);
					}
					updateLinkText(boardSelectAllLink, checkboxes);
				}
			}
		});

		const boardRow = document.getElementById('boardrow');
		if (boardRow) {
			const checkboxes = boardRow.querySelectorAll('input[type="checkbox"]');
	
			checkboxes.forEach(checkbox => {
				checkbox.addEventListener('change', () => {
					updateLinkText(boardSelectAllLink, checkboxes);
				});
			});
	
			updateLinkText(boardSelectAllLink, checkboxes);
		}
	}
	

	
	const detailsElement = document.getElementById('filtercontainer');

	if (detailsElement) {
		const isOpen = localStorage.getItem('filtercontainer_open') === 'true';
		detailsElement.open = isOpen;
	
		detailsElement.addEventListener('toggle', function() {
			localStorage.setItem('filtercontainer_open', detailsElement.open);
		});
	}



	

})();
