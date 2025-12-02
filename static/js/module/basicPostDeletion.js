(function () {
    /**
     * Mark a post/thread as deleted based on type (post or file)
     */
    function markAsDeleted(postEl, type) {
        if (!postEl) {
            console.error("Error: Post element not found!");
            return;
        }

        if (type === 'file') {
            // Target the filesize section for appending the "[FILE DELETED]" message
            const filesizeEl = postEl.querySelector('.filesize');
            if (!filesizeEl) {
                console.error("Error: Filesize element not found!");
                return;
            }

            // Create the warning element
            const warn = document.createElement('span');
            warn.className = 'warning';
            warn.title = "This post's file was deleted";
            warn.textContent = '[FILE DELETED]';

            // Append the warning message after the file size info inside the .filesize container
            filesizeEl.appendChild(warn);
            return { warn, spacer1: null, vd: null, spacer2: null };
        } else {
            // Handle post deletion (not file)
            if (postEl.classList.contains('op')) {
                const thread = postEl.closest('.thread');
                if (thread) {
                    thread.classList.add('deletedPost');
                    thread.querySelectorAll('.post').forEach(p => appendWarning(p, 'post'));
                }
            } else {
                postEl.classList.add('deletedPost');
                appendWarning(postEl, 'post');
            }
        }
    }

    /**
     * Handle the deletion of a file attachment (admin delete action)
     */
    function handleFileDeletion(event, dfAnchor) {
        event.preventDefault();  // Prevent the default link redirection

        const postEl = dfAnchor.closest('.post');
        if (!postEl) {
            console.error("Error: Post element not found for DF link.");
            return;
        }

        // Mark the post's file as deleted
        markAsDeleted(postEl, 'file');

        // Optionally send a fetch request to delete the attachment (you can adjust this to suit your backend)
        const url = dfAnchor.href;
        fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            cache: 'no-store'
        })
        .then((response) => {
            if (!response.ok) {
                console.error(`Error: Failed to delete the attachment (HTTP ${response.status}).`);
                showMessage("Failed to delete the attachment.", false); // Show failure message
                return;
            }
            showMessage("Attachment deleted successfully.", true); // Show success message

            // Remove the [DF] button after successful deletion
            dfAnchor.closest('.adminDeleteFileFunction').remove(); // Remove the DF button
        })
        .catch((err) => {
            console.error("Error: Network error during file deletion:", err);
            showMessage("Network error during deletion.", false); // Show failure message
        });
    }
	
    /**
     * Unified deletion/mute/attachment handler
     */
    async function handleWidgetDeletion(action, ctx) {
        const postEl = ctx?.post || ctx?.arrow?.closest('.post');
        if (!postEl || !ctx?.url) {
            console.error("Error: Invalid context or post element.");
            return;
        }

        const type = action === 'deleteAttachment' ? 'file' : 'post';

        const successMessages = {
            delete: 'Post deleted!',
            mute: 'Post deleted and user muted!',
            deleteAttachment: 'Attachment deleted!'
        };

        const failMessages = {
            delete: 'Failed to delete post.',
            mute: 'Failed to delete and mute.',
            deleteAttachment: 'Failed to delete attachment.'
        };

        try {
            markAsDeleted(postEl, type);

            const res = await fetch(ctx.url, {
                method: 'GET',
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                cache: 'no-store'
            });

            if (!res.ok) {
                throw new Error('Server returned an error (HTTP ' + res.status + ')');
            }

            let data = {};
            try {
                data = await res.json();
            } catch (_) {
                // non-JSON ok
            }

            if (data?.success && data.deleted_link) {
                postEl.dataset.deletedLink = data.deleted_link;
            }

            showMessage(successMessages[action] || 'Action complete.', true);

            if (type === 'file') {
                removeWidgetActions(postEl, ['deleteAttachment']);
                await reloadAttachment(postEl);
            } else {
                removeWidgetActions(postEl, ['delete', 'mute', 'deleteAttachment']);
                // hide entire post after deletion
                postEl.style.transition = 'opacity 0.3s ease';
                postEl.style.opacity = '0';
                
                setTimeout(() => {
                    const parent = postEl.closest('.reply-container');
                    if (parent) {
                        parent.remove();
                    } else {
                        postEl.remove();
                    }
                }, 300);
            }
        } catch (err) {
            console.error("Error: " + (failMessages[action] || 'Failed to process deletion.') + " Reason: " + err.message);
            showMessage(failMessages[action] || 'Failed to process deletion.', false);
        }
    }

    /**
     * Adds the “View deleted post” option dynamically if deleted_link exists
     */
    function addViewDeletedMenu() {
        window.postWidget.registerMenuAugmenter(ctx => {
            const post = ctx?.post || ctx?.arrow?.closest('.post');
            if (!post || post.querySelector('[data-action="viewdeleted"]')) return [];
            const link = post.dataset.deletedLink;
            if (!link) return [];
            return [
                {
                    href: link,
                    action: 'viewdeleted',
                    label: 'View deleted post',
                    subMenu: ''
                }
            ];
        });
    }

    // --- Register the widget handlers ---
    if (window.postWidget) {
        window.postWidget.registerActionHandler('delete', ctx =>
            handleWidgetDeletion('delete', ctx)
        );
        window.postWidget.registerActionHandler('mute', ctx =>
            handleWidgetDeletion('mute', ctx)
        );
        window.postWidget.registerActionHandler('viewdeleted', ctx => {
            if (ctx?.url && ctx.url !== '#') window.location.assign(ctx.url);
        });
        addViewDeletedMenu();
    }

    // ====== ADMIN DELETE FILE FUNCTION ======
    // Handle the admin file deletion and append [FILE DELETED] to the filesize
    document.addEventListener('click', function (e) {
        const dfAnchor = e.target.closest('.adminDeleteFileFunction a[title="Delete attachment"]');
        if (!dfAnchor) return;  // Only proceed if DF anchor is clicked

        handleFileDeletion(e, dfAnchor);  // Handle the file deletion logic
    });

})();
