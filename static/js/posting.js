document.addEventListener("DOMContentLoaded", () => {
	const postForm = document.getElementById("postform");
	if (!postForm) return;

	const subInput = document.getElementById("sub");
	const comInput = document.getElementById("com");
	const emailInput = document.getElementById("email");
	const nokoBox  = document.getElementById("noko");
	const dumpBox  = document.getElementById("dump");
	const restoField = document.querySelector("input[name='resto']");
	const alwaysNoko = postForm.dataset.alwaysnoko;

	const oldBrowser = (
		!window.fetch ||
		!window.FormData ||
		!window.Promise ||
		!window.ReadableStream ||
		!Response.prototype.text
	);

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
		// Clear text fields
		if (subInput) subInput.value = "";
		if (comInput) comInput.value = "";

		// File Cleanup
		var list = document.getElementById("userscript-file-list-container");
		if (list) list.remove();
	
		var dz = document.getElementById("userscript-dropzone-wrap");
		if (dz) dz.style.display = "block";

		var realInput = document.querySelector("input[type='file'][name^='upfile']");
		if (realInput) realInput.value = "";
		
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
		if (oldBrowser) {
			// allow normal submission
			return;
		}

		event.preventDefault(); // Always AJAX

		const emailValue = emailInput?.value.trim().toLowerCase() || "";
		const threadId = restoField ? restoField.value : "0";
		const isReply = threadId !== "0";

		const isNoko = (nokoBox && nokoBox.checked) || (emailValue.includes("noko") && !emailValue.includes("nonoko")) || alwaysNoko;
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
				if (!isReply && data.redirectUrl) {
					window.location.href = data.redirectUrl;
					return;
				}

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
			if (data.redirectUrl || emailValue.includes("nonoko")) {
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
	if (!oldBrowser) {
		postForm.addEventListener("submit", submitHandler);
	}
});
