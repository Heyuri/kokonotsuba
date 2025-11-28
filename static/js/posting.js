document.addEventListener("DOMContentLoaded", () => {
    const postForm = document.getElementById("postform");
    if (!postForm) return;

    const restoField = document.querySelector("input[name='resto']");
    const isReply = restoField && restoField.value !== "0";

    // ------------------------------------------------------
    // Only apply AJAX posting for replies.
    // New thread posts should submit normally.
    // ------------------------------------------------------
    if (!isReply) return;

    /**
     * Safely parse JSON from a fetch Response.
     * Returns null if not valid JSON.
     */
    function safeJSON(response) {
        return response.text().then(text => {
            try { return JSON.parse(text); }
            catch { return null; }
        });
    }

    /**
     * Main AJAX reply handler
     */
    function submitHandler(event) {
        const emailInput = document.getElementById("email");
        const emailValue = emailInput?.value.trim().toLowerCase() || "";

        // ----------------------------------------------------------
        // Submit normally if email is empty or contains "nonoko"
        // ----------------------------------------------------------
        if (!emailValue || emailValue.includes("nonoko")) {
            // Allow normal form submission
            return;
        }

        // Prevent default only if we are doing AJAX
        event.preventDefault();

        const subInput   = document.getElementById("sub");
        const comInput   = document.getElementById("com");
        const nokoBox    = document.getElementById("noko") || null;
        const dumpBox    = document.getElementById("dump") || null;

        const isNoko = (nokoBox && nokoBox.checked) || (emailValue.includes("noko") && !emailValue.includes("nonoko"));
        const isDump = (dumpBox && dumpBox.checked) || emailValue.includes("dump");

        const submitButton = event.submitter;

        // Build FormData including clicked submit button
        const formData = new FormData(postForm);
        if (submitButton && submitButton.name) {
            formData.append(submitButton.name, submitButton.value);
        }

        function setButtonOpacity(value) {
            if (submitButton) submitButton.style.opacity = value;
        }

        // ----------------------------------------------------------
        // Perform AJAX POST
        // ----------------------------------------------------------
        function ajaxPost() {
            setButtonOpacity(0.5);

            return fetch(postForm.action, {
                method: "POST",
                body: formData,
                headers: {
                    "X-Requested-With": "XMLHttpRequest"
                }
            })
            .then(async resp => {
                const data = await safeJSON(resp);

                // ---- Handle HTTP errors (400, 403, 500, etc.) ----
                if (!resp.ok) {
                    // if JSON contains message, use it
                    if (data?.message) {
                        showMessage(data.message, false);
                    } else {
                        showMessage(`Error ${resp.status}: Post failed.`, false);
                    }
                    throw new Error(`HTTP ${resp.status}`);
                }

                // ---- Handle server JSON errors ----
                if (data?.error) {
                    showMessage(data.message || "An error occurred.", false);
                    throw new Error(`Server error: ${data.code}`);
                }

                return data;
            })
            .finally(() => {
                setButtonOpacity(1);
            })
            .catch(err => {
                console.error("AJAX posting error:", err);
            });
        }

        // ----------------------------------------------------------
        // Handle Noko / Dump submission types
        // ----------------------------------------------------------

        // Noko reply → update thread and scroll to new post
        if (isNoko) {
            ajaxPost().then(data => {
                if (!data?.postId) return;

                if (typeof kkupdate !== "undefined") {
                    fetchNewReplies().then(() => {
                        const postEl = document.getElementById(data.postId);
                        if (postEl) {
                            postEl.scrollIntoView({ behavior: "smooth" });
                            window.location.hash = data.postId;
                        }
                    });
                }

                // Clear subject and comment
                if (subInput) subInput.value = "";
                if (comInput) comInput.value = "";
            });
            return;
        }

        // Dump reply → silent, no scrolling
        if (isDump) {
            ajaxPost().then(data => {
                // Clear subject and comment
                if (subInput) subInput.value = "";
                if (comInput) comInput.value = "";
            });
            return;
        }

        // Regular reply → normal submission
        postForm.removeEventListener("submit", submitHandler);
        postForm.submit();
    }

    // Attach listener for REPLY posts only
    postForm.addEventListener("submit", submitHandler);
});
