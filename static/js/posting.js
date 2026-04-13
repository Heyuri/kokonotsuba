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
		if (window.resetClipboardFiles) {
			window.resetClipboardFiles();
		} else {
			var list = document.getElementById("fileListContainer");
			if (list) list.remove();

			var dz = document.getElementById("dropzoneWrap");
			if (dz) dz.style.display = "block";

			var realInput = document.querySelector("input[type='file'][name^='upfile']");
			if (realInput) realInput.value = "";
		}
		
		// also clear the quick reply form for good measure
		if(kkqr) {
			// clear the form
			kkqr.resetQRFields();
		}
	}

	/**
	 * Get the post number of the last reply currently in the DOM.
	 * Used to tell the server which posts are new.
	 */
	function getLastReplyPostNo() {
		const replies = document.querySelectorAll(".reply-container");
		if (!replies.length) return 0;
		const lastId = replies[replies.length - 1].id; // e.g. "pc1_123"
		const match = lastId.match(/(\d+)$/);
		return match ? parseInt(match[1], 10) : 0;
	}

	/**
	 * Insert an array of reply HTML strings into the thread in order.
	 * Skips any that already exist in the DOM. Returns the last inserted element.
	 */
	function insertNewPostsHtml(htmlArray) {
		const threadElement = document.querySelector(".thread");
		if (!threadElement || !htmlArray || !htmlArray.length) return null;

		let lastEl = null;
		const inserted = [];
		htmlArray.forEach(html => {
			const temp = document.createElement("div");
			temp.innerHTML = html;
			const replyEl = temp.firstElementChild;
			if (!replyEl) return;

			// Skip if this post already exists in the DOM
			if (document.getElementById(replyEl.id)) return;

			threadElement.appendChild(replyEl);
			lastEl = replyEl;
			inserted.push(replyEl);
		});

		// Initialize JS features on all newly inserted posts
		if (inserted.length) {
			initNewPosts(inserted);
		}

		return lastEl;
	}

	/**
	 * Update thread with new replies and scroll to the user's post.
	 * Inserts all new replies returned by the server in correct order.
	 */
	function updateThreadAndScroll(postId, newPostsHtml) {
		if (newPostsHtml && newPostsHtml.length) {
			// Insert all new replies in order
			insertNewPostsHtml(newPostsHtml);

			// Scroll to the user's own post
			const postEl =
				document.getElementById(postId) ||
				document.querySelector(`#p${postId}`) ||
				document.querySelector(`[id$="${postId}"]`);

			if (postEl) {
				postEl.scrollIntoView({ behavior: "smooth" });
				window.location.hash = postId;
			}
		} else {
			// Fallback: fetch everything then scroll
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

		const isNoko = ((nokoBox && nokoBox.checked) || (emailValue.includes("noko") && !emailValue.includes("nonoko"))) || (alwaysNoko && !emailValue.includes("nonoko"));
		const isDump = (dumpBox && dumpBox.checked) || emailValue.includes("dump");

		const submitButton = event.submitter;

		// Build FormData including clicked submit button
		const formData = new FormData(postForm);
		if (submitButton && submitButton.name) {
			formData.append(submitButton.name, submitButton.value);
		}

		// Send the last known post number so the server can return all newer replies
		if (isReply) {
			formData.append("lastPostNo", getLastReplyPostNo());
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
            		if (resp.status === 525) {
            		    showMessage("SSL Handshake error. Try again in a minute or so.", false);
            		}

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

				// Insert new replies inline if server returned them
				if (data.newPostsHtml && data.newPostsHtml.length) {
					insertNewPostsHtml(data.newPostsHtml);
				} else {
					fetchNewReplies();
				}
				clearForm();
				return;
			}

			// --- NOKO reply ---
			if (isNoko && isReply) {
				updateThreadAndScroll(data.postId, data.newPostsHtml);
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
