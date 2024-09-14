(function() {
    'use strict';

    const MIN_WIDTH = 440; // Minimum width of the preview box
    const OFFSET_X = 10;   // Offset to the left from the mouse cursor
    const RIGHT_MARGIN = 30; // Margin from the right edge of the viewport
    let hoverTimeout;
    let activeBoxes = [];
    let hoverAreas = new Set();

    // Create and manage preview boxes
    function createPreviewBox(noMinWidth = false) {
        const box = document.createElement('div');
        box.style.position = 'absolute';
        box.style.zIndex = '1000';
        box.style.display = 'none';
        if (!noMinWidth) {
            box.style.minWidth = `${MIN_WIDTH}px`;  // Set minimum width only if not showing "Quote source not found"
        }
        box.style.maxWidth = '90%'; // Max width to prevent exceeding viewport
        box.style.boxSizing = 'border-box'; // Ensure padding is included in width

        // Check if the box has a background color; if not, apply the body's background color
        const computedStyle = window.getComputedStyle(box);
        if (!computedStyle.backgroundColor || computedStyle.backgroundColor === 'rgba(0, 0, 0, 0)') {
            const bodyBackgroundColor = window.getComputedStyle(document.body).backgroundColor;
            box.style.backgroundColor = bodyBackgroundColor;
        }

        document.body.appendChild(box);
        return box;
    }

    // Function to show the preview after 0.4 seconds
    function startHover(event) {
        const target = event.target;
        hoverTimeout = setTimeout(() => {
            const quotedText = target.textContent.slice(1).trim(); // Remove ">" and trim whitespace
            const currentPostId = target.closest('.post').id; // Get the ID of the current post
            const matchingPostId = findMatchingPostId(quotedText, currentPostId);

            const post = document.getElementById(matchingPostId);
            const previewBox = createPreviewBox(matchingPostId === "notFound");

            if (post && matchingPostId !== "notFound") {
                previewBox.innerHTML = ''; // Clear any existing content

                // Clone the entire post and append it to the preview box
                const clonedPost = post.cloneNode(true);
                clonedPost.style.margin = '0';  // Remove any margins
                previewBox.appendChild(clonedPost);

                // Apply the hover event listeners to the quotes within the preview box
                applyHoverListeners(clonedPost);
            } else {
                // Display a styled "Quote source not found" message without min-width
                previewBox.innerHTML = `
                    <div class="post reply">
                        <div class="comment">Quote source not found</div>
                    </div>
                `;
            }

            // Position the preview box according to the mouse position and viewport constraints
            positionPreviewBox(previewBox, event);

            previewBox.style.display = 'block';
            activeBoxes.push({ box: previewBox, trigger: target });
            hoverAreas.add(previewBox);
            hoverAreas.add(target);

            previewBox.addEventListener('mouseleave', () => {
                setTimeout(() => {
                    if (![...hoverAreas].some(element => element.matches(':hover'))) {
                        clearActiveBoxes();
                    }
                }, 10);
            });
        }, 400); // 0.4 seconds delay
    }

    // Function to position the preview box based on mouse and viewport
    function positionPreviewBox(previewBox, event) {
        const mouseX = event.clientX;
        const viewportWidth = window.innerWidth;
        const boxWidth = Math.max(previewBox.offsetWidth, MIN_WIDTH);
        let left = mouseX - OFFSET_X;

        // Ensure the box stays within the viewport and at least 5px from the right edge
        if (left + boxWidth > viewportWidth - RIGHT_MARGIN) {
            left = viewportWidth - boxWidth - RIGHT_MARGIN;  // Reposition to fit within the viewport
        }

        previewBox.style.left = `${Math.max(0, left)}px`; // Prevent box from exceeding left viewport boundary

        // Position under the quote line
        const rect = event.target.getBoundingClientRect();
        previewBox.style.top = `${rect.bottom + window.scrollY}px`; // Directly below the quote
    }

    // Function to clear all active boxes
    function clearActiveBoxes() {
        activeBoxes.forEach(({ box }) => {
            box.style.display = 'none';
            box.remove();
        });
        activeBoxes = [];
        hoverAreas.clear();
    }

    // Function to stop hovering and clear timeout
    function stopHover(event) {
        const target = event.relatedTarget;

        if (![...hoverAreas].some(element => element.contains(target))) {
            clearTimeout(hoverTimeout);
            clearActiveBoxes();
        }
    }

    // Function to find matching post ID only above the current post
    function findMatchingPostId(quotedText, currentPostId) {
        let isPostNumber = false;
        let postId = '';

        // Check if the quoted text matches the "No. xxx" or "No.xxx" format
        const noMatch = quotedText.match(/^No\. ?(\d+)$/);
        if (noMatch) {
            postId = `p${noMatch[1]}`; // Extract the post number and prepend "p"
            isPostNumber = true; // Mark as a valid post number quote
        }

        if (!isPostNumber) {
            // If it's just a number without "No.", or a filename, do not treat it as a post number
            return findMatchingPostByFilenameOrComment(quotedText, currentPostId);
        }

        // Now try to find the post by ID
        const post = document.getElementById(postId);
        if (post && isPostAboveCurrent(post, currentPostId)) {
            return postId;
        }

        return 'notFound';
    }

    // Function to find matching post by filename or partial comment match
    function findMatchingPostByFilenameOrComment(quotedText, currentPostId) {
        // Get all previous posts in the thread up to the current post
        let posts = document.querySelectorAll('.post.reply, .post.op');
        posts = Array.from(posts).reverse(); // Reverse to start searching from the latest

        let foundCurrent = false;
        for (let post of posts) {
            if (post.id === currentPostId) {
                foundCurrent = true;
                continue; // Stop searching after passing the current post
            }
            if (foundCurrent && isPostAboveCurrent(post, currentPostId)) {
                const comment = post.querySelector('.comment');
                const filenameLink = post.querySelector('.filesize a');

                // Match by full comment text (allows partial matches)
                if (comment && comment.textContent.includes(quotedText)) {
                    return post.id;
                }

                // Match by exact filename
                if (filenameLink && filenameLink.textContent.trim() === quotedText) {
                    return post.id;
                }
            }
        }

        // Return a default that will not match anything
        return 'notFound';
    }

    // Function to check if a post is above the current post
    function isPostAboveCurrent(post, currentPostId) {
        const postPosition = document.getElementById(post.id).getBoundingClientRect().top;
        const currentPosition = document.getElementById(currentPostId).getBoundingClientRect().top;
        return postPosition < currentPosition;
    }

    // Function to attach hover event listeners to elements with class "unkfunc"
    function applyHoverListeners(element) {
        element.querySelectorAll('.unkfunc').forEach(quoted => {
            quoted.addEventListener('mouseover', startHover);
            quoted.addEventListener('mouseout', stopHover);
        });
    }

    // Initially apply hover listeners to the main document
    applyHoverListeners(document);

})();
