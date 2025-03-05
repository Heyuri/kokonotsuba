(function () {
    'use strict';

    const MIN_WIDTH = 0; // Minimum width of the preview box
    const OFFSET_X = 10; // Offset to the left from the mouse cursor
    const RIGHT_MARGIN = 30; // Margin from the right edge of the viewport
    const PREVIEW_DELAY = 300; // delay before showing preview (ms)
    const REMOVAL_DELAY = 50; // delay before removal check (ms)

    // Each preview object contains:
    // { box, trigger, parent, contextPost }
    let previewStack = [];

    // Creates and appends a preview box.
    function createPreviewBox(notFound = false) {
        const box = document.createElement('div');
        box.classList.add('previewBox');
        box.style.minWidth = `${MIN_WIDTH}px`;  // Set minimum width only if not showing "Quote source not found"
        if (notFound) {
            box.innerHTML = `
                <div class="post reply">
                    Quote source not found
                </div>
            `;
        }
        document.body.appendChild(box);
        return box;
    }

    // Called when hovering over a .unkfunc element.
    function startHover(event) {
        const trigger = event.currentTarget;
        if (trigger.hoverTimeout) return;

        // Capture updated mouse position
        let latestMouseEvent = event;
        function updateMouse(e) {
            latestMouseEvent = e;
        }
        document.addEventListener('mousemove', updateMouse);

        trigger.hoverTimeout = setTimeout(() => {
            trigger.hoverTimeout = null;
            // Remove the mousemove listener now that we have the updated mouse event
		    document.removeEventListener('mousemove', updateMouse);

            const rawText = trigger.textContent;
            if (!rawText.startsWith('>')) return;
            let quotedText = rawText.slice(1).trim();  // Remove ">" and trim whitespace
            const isDoubleQuote = quotedText.startsWith('>');
            if (isDoubleQuote) {
                quotedText = quotedText.slice(1).trim(); // Remove extra ">" for double >>
            }

            // Determine proper lookup context:
            // If inside a preview, use the original post that was previewed (stored as contextPost).
            // Otherwise, use the closest .post element.
            let contextElement;
            let parentPreviewObj = null;
            const parentBox = trigger.closest('.previewBox');
            if (parentBox) {
                parentPreviewObj = previewStack.find(obj => obj.box === parentBox) || null;
            }
            if (parentPreviewObj && parentPreviewObj.contextPost) {
                contextElement = parentPreviewObj.contextPost;
            } else {
                contextElement = trigger.closest('.post');
                if (!contextElement) return;
            }

            // Record the parent preview (if any)
            let parentPreview = parentPreviewObj || null;

            const matchingPostId = findMatchingPostId(quotedText, contextElement, isDoubleQuote);
            const post = document.getElementById(matchingPostId);
            const isOP = post && post.classList.contains('op');
            const previewBox = createPreviewBox(matchingPostId === 'notFound', isOP);

            // Create a preview object.
            // Note: contextPost is the original post element from which the preview was made.
            let previewObj = {
                box: previewBox,
                trigger: trigger,
                parent: parentPreview,
                contextPost: post ? post : contextElement
            };
            previewStack.push(previewObj);

            if (post && matchingPostId !== 'notFound') {
                previewBox.innerHTML = ''; // Clear any existing content

                // Clone the entire post and append it to the preview box
                const clonedPost = post.cloneNode(true);
                clonedPost.removeAttribute('id');
                clonedPost.style.margin = '0'; // Remove any margins
                previewBox.appendChild(clonedPost);
            }

            attachPreviewHoverHandlers(previewObj);

            // Apply the hover event listeners to the quotes within the preview box
            applyHoverListeners(previewBox); 

            // Position the preview box according to the mouse position and viewport constraints
            positionPreviewBox(previewBox, latestMouseEvent);
            previewBox.style.display = 'block';
        }, PREVIEW_DELAY);
    }

    // Clears the hover timeout on the trigger.
    function stopHover(event) {
        const trigger = event.currentTarget;
        if (trigger.hoverTimeout) {
            clearTimeout(trigger.hoverTimeout);
            trigger.hoverTimeout = null;
        }
        // Also trigger a global check shortly after the mouse leaves
        setTimeout(checkPreviews, REMOVAL_DELAY);
    }

    // Attach mouseenter/mouseleave handlers to both preview box and trigger.
    function attachPreviewHoverHandlers(previewObj) {
        const { box, trigger } = previewObj;
        box.addEventListener('mouseenter', () => { /* no-op */ });
        box.addEventListener('mouseleave', () => {
            setTimeout(checkPreviews, REMOVAL_DELAY);
        });
        trigger.addEventListener('mouseenter', () => { /* no-op */ });
        trigger.addEventListener('mouseleave', () => {
            setTimeout(checkPreviews, REMOVAL_DELAY);
        });
        // Update preview position as the mouse moves
        trigger.addEventListener('mousemove', (event) => {
			positionPreviewBox(box, event);
		});
    }

    // Global removal check:
    // Iterate through all previews and remove any (and their descendants)
    // that aren’t hovered (or do not have a descendant that is hovered).
    function checkPreviews() {
        previewStack.slice().forEach(previewObj => {
            if (!isPreviewOrDescendantHovered(previewObj)) {
                removePreviewAndDescendants(previewObj);
            }
        });
    }

    // Recursively checks if this preview or any of its descendants are hovered.
    function isPreviewOrDescendantHovered(previewObj) {
        if (previewObj.box.matches(':hover') || previewObj.trigger.matches(':hover')) {
            return true;
        }
        const children = previewStack.filter(obj => obj.parent === previewObj);
        for (const child of children) {
            if (isPreviewOrDescendantHovered(child)) {
                return true;
            }
        }
        return false;
    }

    // Recursively remove the preview and its descendants.
    function removePreviewAndDescendants(previewObj) {
        const children = previewStack.filter(obj => obj.parent === previewObj);
        for (const child of children) {
            removePreviewAndDescendants(child);
        }
        if (previewObj.box.parentNode) {
            previewObj.box.parentNode.removeChild(previewObj.box);
        }
        previewStack = previewStack.filter(obj => obj !== previewObj);
    }

    // Positions the preview box near the mouse.
    function positionPreviewBox(previewBox, event) {
        const viewportWidth = window.innerWidth;
        const viewportHeight = window.innerHeight;
        previewBox.style.maxWidth = `${viewportWidth - RIGHT_MARGIN}px`; // Ensure the preview box never exceeds the viewport width (accounting for RIGHT_MARGIN)
        previewBox.style.display = 'block'; // Ensure the preview box is displayed for correct measurement
        const boxWidth = Math.max(previewBox.offsetWidth, MIN_WIDTH);
        let left = event.clientX - OFFSET_X;
        // Adjust if the box would extend beyond the right edge
		if (left + boxWidth > viewportWidth - RIGHT_MARGIN) {
			left = viewportWidth - boxWidth - RIGHT_MARGIN;  // Reposition to fit within the viewport
        }
        previewBox.style.left = `${Math.max(0, left)}px`; // Prevent box from exceeding left viewport boundary

        // Determine vertical position
        const rect = event.target.getBoundingClientRect();
        const previewHeight = previewBox.offsetHeight;
        const topBelow = rect.bottom + window.scrollY;	// Position below the quote
        const topAbove = rect.top + window.scrollY - previewHeight;	// Position above the quote

        // Check if there's enough space below; if not, use the position above (ensuring it doesn't go off the top)
        if (rect.bottom + previewHeight > viewportHeight) {
            previewBox.style.top = `${Math.max(topAbove, window.scrollY)}px`;
        } else {
            previewBox.style.top = `${topBelow}px`;
        }
    }

    // Searches for the matching post based on quoted text.
    function findMatchingPostId(quotedText, contextElement, includeUnkfunc) {
        // Check if the quoted text matches the "No. xxx" or "No.xxx" format
        const noMatch = quotedText.match(/^No\. ?(\d+)$/);
        if (noMatch) {
            const postId = `p${noMatch[1]}`; // Extract the post number and prepend "p"

            // Now try to find the post by ID
            const post = document.getElementById(postId);
            if (post && isPostAboveContext(post, contextElement)) {
                return postId;
            }
            return 'notFound';
        }
        const contextRect = contextElement.getBoundingClientRect();

        // Find matching post by filename or partial comment match
        const posts = Array.from(document.querySelectorAll('.post.reply, .post.op'))
            .filter(post => {
                const postRect = post.getBoundingClientRect();
                return postRect.top < contextRect.top;
            })
            .reverse(); // Reverse to start searching from the latest
        for (const post of posts) {
            const comment = post.querySelector('.comment');
            const filenameLink = post.querySelector('.filesize a');
            if (comment) {
                let textToCheck;
                if (!includeUnkfunc) {
                    const clone = comment.cloneNode(true);
                    clone.querySelectorAll('.unkfunc').forEach(el => el.remove());
                    textToCheck = clone.textContent;
                } else {
                    textToCheck = comment.textContent; // Match by full comment text (allows partial matches)
                }
                if (textToCheck.includes(quotedText)) {
                    return post.id;
                }
            }

            // Match by exact filename
            if (filenameLink) {
                const visibleFilename = filenameLink.textContent.trim();
                const fullFilename = filenameLink.getAttribute('onmouseover')
                    ?.match(/this\.textContent='([^']+)'/)?.[1] || visibleFilename;
                if (fullFilename === quotedText) {
                    return post.id;
                }
            }
        }

        // Return a default that will not match anything
        return 'notFound';
    }

    // Returns true if the post is above the context element.
    function isPostAboveContext(post, contextElement) {
        const postRect = post.getBoundingClientRect();
        const contextRect = contextElement.getBoundingClientRect();
        return postRect.top < contextRect.top;
    }

    // Attach hover listeners to all .unkfunc elements within the given element.
    function applyHoverListeners(element) {
        element.querySelectorAll('.unkfunc').forEach((quoted) => {
            quoted.addEventListener('mouseover', startHover);
            quoted.addEventListener('mouseout', stopHover);
        });
    }

    // Also run a global check on mousemove.
    let mousemoveTimeout;
    document.addEventListener('mousemove', () => {
        clearTimeout(mousemoveTimeout);
        mousemoveTimeout = setTimeout(checkPreviews, REMOVAL_DELAY);
    });

    applyHoverListeners(document);
})();

