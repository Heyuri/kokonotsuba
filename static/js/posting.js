document.addEventListener("DOMContentLoaded", () => {
    const postForm = document.getElementById("postform");
    if (!postForm) return;

    const subInput = document.getElementById("sub");
    const comInput = document.getElementById("com");
    const emailInput = document.getElementById("email");
    const nokoBox  = document.getElementById("noko");
    const dumpBox  = document.getElementById("dump");
    const restoField = document.querySelector("input[name='resto']");

    /**
     * Safely parse JSON from a fetch Response.
     */
    function safeJSON(response) {
        return response.text().then(text => {
            try { return JSON.parse(text); }
            catch { return null; }
        });
    }

    /**
     * Clear subject & comment fields
     */
    function clearForm() {
        // Clear text inputs
        if (subInput) subInput.value = "";
        if (comInput) comInput.value = "";

        // --- Remove file-related UI elements ---
        const filePreview = document.getElementById("file-preview");
        if (filePreview) filePreview.remove();

        const fileSizeContainer = document.getElementById("file-size-container");
        if (fileSizeContainer) fileSizeContainer.remove();

        const filenameContainer = document.getElementById("filename-container");
        if (filenameContainer) filenameContainer.remove();

        // Optionally clear the file input itself
        const fileInput = document.getElementById("upfile");
        if (fileInput) fileInput.value = "";

        // Optionally reset the animated GIF checkbox
        const anigifCheckbox = document.getElementById("anigif");
        if (anigifCheckbox) anigifCheckbox.checked = false;

        // also clear the quick reply form for good measure
        if(kkqr) {
            // clear the form
            kkqr.resetQRFields();
        }
    }

    /**
     * Update thread with new replies and scroll to post
     */
    function updateThreadAndScroll(postId) {
        fetchNewReplies().then(() => {
            const postEl =
                document.getElementById(postId) ||
                document.querySelector(`#p${postId}`) ||
                document.querySelector(`[id$="${postId}"]`);

            if (postEl) {
                postEl.scrollIntoView({ behavior: "smooth" });
                window.location.hash = postId;
            }
        });
    }

    /**
     * Main AJAX submit handler
     */
    async function submitHandler(event) {
        if (!window.fetch || !window.FormData || !window.Promise) {
            return;
        }

        event.preventDefault(); // Always AJAX

        const emailValue = emailInput?.value.trim().toLowerCase() || "";
        const threadId = restoField ? restoField.value : "0";
        const isReply = threadId !== "0";

        const isNoko = (nokoBox && nokoBox.checked) || (emailValue.includes("noko") && !emailValue.includes("nonoko"));
        const isDump = (dumpBox && dumpBox.checked) || emailValue.includes("dump");

        const submitButton = event.submitter;

        // Build FormData including clicked submit button
        const formData = new FormData(postForm);
        if (submitButton && submitButton.name) {
            formData.append(submitButton.name, submitButton.value);
        }

        if (submitButton) submitButton.disabled = true;

        function setButtonOpacity(value) {
            if (submitButton) submitButton.style.opacity = value;
        }

        // Perform AJAX POST
        async function ajaxPost() {
            setButtonOpacity(0.5);
            try {
                const resp = await fetch(postForm.action, {
                    method: "POST",
                    body: formData,
                    headers: { "X-Requested-With": "XMLHttpRequest" }
                });

                const data = await safeJSON(resp);

                if (!resp.ok) {
                    if (data?.message) showMessage(data.message, false);
                    else showMessage(`Error ${resp.status}: Post failed.`, false);
                    throw new Error(`HTTP ${resp.status}`);
                }

                if (data?.error) {
                    showMessage(data.message || "An error occurred.", false);
                    throw new Error(`Server error: ${data.code}`);
                }

                return data;
            } catch (err) {
                console.error("AJAX posting error:", err);

                // fallback for older browsers
                try {
                    postForm.submit(); // this does NOT re-trigger the submit handler
                } catch (e) {
                    console.error("Fallback submit failed:", e);
                }

                return null;
            } finally {
                setButtonOpacity(1);
                if (submitButton) submitButton.disabled = false;
                if (window.kkqrLastSubmitButton) window.kkqrLastSubmitButton.disabled = false;
            }
        }

        // Handle post response
        function handleSuccessfulPost(data) {
            if (!data) return;

            // --- DUMP: silent, clear form ---
            if (isDump) {
                fetchNewReplies();
                clearForm();
                return;
            }

            // --- NOKO reply ---
            if (isNoko && isReply) {
                updateThreadAndScroll(data.postId);
                clearForm();
                return;
            }

            // --- Otherwise, redirect if redirectUrl exists ---
            if (data.redirectUrl) {
                window.location.href = data.redirectUrl;
                return;
            }

            // fallback: still clear form if needed
            clearForm();
        }

        // Execute AJAX and handle response
        const data = await ajaxPost();
        handleSuccessfulPost(data);
    }

    // Attach submit listener only if basic AJAX APIs exist
    if (window.fetch && window.FormData && window.Promise) {
        postForm.addEventListener("submit", submitHandler);
    }
});
