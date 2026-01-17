function fetchNewReplies() {
	// Fetch the page content but don't update any UI elements
	return new Promise((resolve, reject) => {
		fetch(window.location.href).then(data => {
			if (data.status !== 200) {
				console.log("Error: Thread has been pruned or deleted");
				return reject("Thread is pruned or deleted"); // Reject if the thread is not found
			} else {
				data.text().then(text => {
					var tmp = document.createElement("div");
					tmp.innerHTML = text;
					
					var threadHTML = tmp.querySelector(".thread");
					var frs = threadHTML.querySelectorAll(".reply-container");


					// Get the current last reply id in the current page
					var rs = document.querySelectorAll(".reply-container");
					var lid = 0;
					if (rs.length) lid = rs[rs.length - 1].id.slice(1);

					// Get the new replies from the fetched HTML
					var newReplies = [];
					var i;

					for (i = frs.length - 1; i >= 0; i--) {
						if (frs[i].id.slice(1) <= lid) break;
					}

					i++; // Skip to the first new reply
					newReplies = Array.from(frs).slice(i); // Collect the new replies

					// Append new replies to the page
					const threadElement = document.querySelector(".thread"); // Assuming you want to insert them here
					newReplies.forEach((reply) => {						
						// get post num quote elements
						const postQuote = reply.querySelectorAll(".qu");

						// add kkqu clink events
						postQuote.forEach(q => {
							q.addEventListener("click", kkqu._evquote);
						});

						threadElement.appendChild(reply); // Append each new reply
					});

					// re add scroll listeners
					if(kkqr) kkqr.addScrollListener();

					// start up image expanding js
					if (attachmentExpander) attachmentExpander.startUpimageExpanding();

					// restart inline js
					if (kkinline) kkinline.startup();

					// Return the new replies for further use if necessary
					resolve(newReplies);
				}).catch(err => {
					console.error("Error processing response text:", err);
					reject(err); // Reject if there's an error processing the HTML
				});
			}
		}).catch(err => {
			console.error("Network error:", err);
			reject(err); // Reject if there's a network error
		});
	});
}
