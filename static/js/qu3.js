(function () {
    'use strict';

    const MIN_WIDTH = 500;
    const OFFSET_X = 10;
    const RIGHT_MARGIN = 30;
    let hoverTimeout;
    let activeBoxes = [];
    let hoverAreas = new Set();

    const style = document.createElement('style');
    style.innerHTML = `
        /* Other styling can go here if needed */
    `;
    document.head.appendChild(style);

    function createPreviewBox(notFound = false, isOP = false) {
        const box = document.createElement('div');
        box.style.position = 'absolute';
        box.style.zIndex = '1000';
        box.style.display = 'none';
        box.style.minWidth = `${MIN_WIDTH}px`;
        box.style.maxWidth = '90%';
        box.style.boxSizing = 'border-box';

        if (notFound) {
            box.innerHTML = `
                <div class="post reply">
                    <div class="comment">Quote source not found</div>
                </div>
            `;
        }

        if (isOP) {
            const computedStyle = window.getComputedStyle(box);
            if (!computedStyle.backgroundColor || computedStyle.backgroundColor === 'rgba(0, 0, 0, 0)') {
                const bodyBackgroundColor = window.getComputedStyle(document.body).backgroundColor;
                box.style.backgroundColor = bodyBackgroundColor;
            }
        }

        document.body.appendChild(box);
        return box;
    }

    function startHover(event) {
        const target = event.target.closest('.unkfunc');
        if (!target) return;

        hoverTimeout = setTimeout(() => {
            const quotedText = target.textContent.slice(1).trim(); // Remove ">" and trim whitespace
            const isDoubleQuote = quotedText.startsWith('>');
            const currentPostId = target.closest('.post').id;
            const matchingPostId = isDoubleQuote
                ? findMatchingPostId(quotedText.slice(1), currentPostId, true) // Remove extra ">" for double >>
                : findMatchingPostId(quotedText, currentPostId, false);

            const post = document.getElementById(matchingPostId);

            const isOP = post && post.classList.contains('op');

            const previewBox = createPreviewBox(matchingPostId === 'notFound', isOP);

            if (post && matchingPostId !== 'notFound') {
                previewBox.innerHTML = '';
                const clonedPost = post.cloneNode(true);
                clonedPost.style.margin = '0';
                previewBox.appendChild(clonedPost);
                applyHoverListeners(clonedPost);
            }

            positionPreviewBox(previewBox, event);
            previewBox.style.display = 'block';
            activeBoxes.push({ box: previewBox, trigger: target });
            hoverAreas.add(previewBox);
            hoverAreas.add(target);

            previewBox.addEventListener('mouseleave', () => {
                setTimeout(() => {
                    if (![...hoverAreas].some((element) => element.matches(':hover'))) {
                        clearActiveBoxes();
                    }
                }, 10);
            });
        }, 400);
    }

    function positionPreviewBox(previewBox, event) {
        const mouseX = event.clientX;
        const viewportWidth = window.innerWidth;
        const boxWidth = Math.max(previewBox.offsetWidth, MIN_WIDTH);
        let left = mouseX - OFFSET_X;

        if (left + boxWidth > viewportWidth - RIGHT_MARGIN) {
            left = viewportWidth - boxWidth - RIGHT_MARGIN;
        }

        previewBox.style.left = `${Math.max(0, left)}px`;

        const rect = event.target.getBoundingClientRect();
        previewBox.style.top = `${rect.bottom + window.scrollY}px`;
    }

    function clearActiveBoxes() {
        activeBoxes.forEach(({ box }) => {
            box.style.display = 'none';
            box.remove();
        });
        activeBoxes = [];
        hoverAreas.clear();
    }

    function stopHover(event) {
        const target = event.relatedTarget;
        if (![...hoverAreas].some((element) => element.contains(target))) {
            clearTimeout(hoverTimeout);
            clearActiveBoxes();
        }
    }

    function findMatchingPostId(quotedText, currentPostId, includeUnkfunc) {
        const noMatch = quotedText.match(/^No\. ?(\d+)$/);
        if (noMatch) {
            const postId = `p${noMatch[1]}`;
            const post = document.getElementById(postId);
            if (post && isPostAboveCurrent(post, currentPostId)) {
                return postId;
            }
            return 'notFound';
        }

        const posts = Array.from(document.querySelectorAll('.post.reply, .post.op')).reverse();
        let foundCurrent = false;

        for (const post of posts) {
            if (post.id === currentPostId) {
                foundCurrent = true;
                continue;
            }

            if (foundCurrent && isPostAboveCurrent(post, currentPostId)) {
                const comment = post.querySelector('.comment');
                const filenameLink = post.querySelector('.filesize a');

                if (includeUnkfunc || !post.querySelector('.unkfunc')) {
                    if (comment && comment.textContent.includes(quotedText)) {
                        return post.id;
                    }

                    if (filenameLink) {
                        const visibleFilename = filenameLink.textContent.trim();
                        const fullFilename = filenameLink.getAttribute('onmouseover')
                            ?.match(/this\.textContent='([^']+)'/)?.[1] || visibleFilename;

                        if (fullFilename === quotedText) {
                            return post.id;
                        }
                    }
                }
            }
        }

        return 'notFound';
    }

    function isPostAboveCurrent(post, currentPostId) {
        const postPosition = post.getBoundingClientRect().top;
        const currentPosition = document.getElementById(currentPostId).getBoundingClientRect().top;
        return postPosition < currentPosition;
    }

    function applyHoverListeners(element) {
        element.querySelectorAll('.unkfunc').forEach((quoted) => {
            quoted.addEventListener('mouseover', startHover);
            quoted.addEventListener('mouseout', stopHover);
        });
    }

    applyHoverListeners(document);
})();
