(function () {
	'use strict';

	window.soudane = window.soudane || {};
	var soudane = window.soudane;
    
	soudane.vote = function(postUid, type, url) {
		var xmlhttp = new XMLHttpRequest();
		xmlhttp.open("GET", url + "&postUid=" + encodeURIComponent(postUid) + "&type=" + type);
		var elem = document.getElementById("vote_" + type + "_" + postUid);
		elem.innerHTML = "&hellip;";
		xmlhttp.onreadystatechange = function() {
			if (xmlhttp.readyState == 4) {
				elem.innerHTML = xmlhttp.responseText;
				soudane.updateScore(postUid, url);

				// Check and update class from noVotes to hasVotes if needed
				if (elem.classList.contains("noVotes")) {
					elem.classList.remove("noVotes");
					elem.classList.add("hasVotes");
				}
			}
		};
		xmlhttp.send(null);
	};

	soudane.updateScore = function(postUid, url) {
		var scoreElem = document.getElementById("vote_score_" + postUid);
		var xmlhttp = new XMLHttpRequest();
		xmlhttp.open("GET", url + "&postUid=" + encodeURIComponent(postUid) + "&type=score");
		xmlhttp.onreadystatechange = function() {
			if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
				scoreElem.innerHTML = xmlhttp.responseText;
			}
		};
		xmlhttp.send(null);
	};

	soudane.updatePosts = function () {
		// fetch all post elements on the page
		const posts = document.getElementsByClassName("post");

		// init the post uid array
		const postUids = [];

		// convert HTMLCollection to Array so we can forEach
		Array.from(posts).forEach(postElement => {
			// get the post uid from the post
			const pUid = postElement.dataset.postUid;

			// push if the post uid exists
			if (pUid) {
				postUids.push(pUid);
			}
		});

		// get the base url
		const urlMeta = document.getElementsByName("soudaneUrl")[0];
		const url = urlMeta ? urlMeta.content : null;

		if (!url || postUids.length === 0) {
			return;
		}

		// handle vote updating
		soudane.handleVoteUpdating(postUids, url);
	};

	soudane.handleVoteUpdating = function (postUids, url) {
		// Build the API URL by appending all post UIDs as a '+'-separated list
		// Example: baseUrl&posts=123+456+789
		const apiUrl = url + '&posts=' + postUids.join('+');

		// Fetch vote data for all posts in a single request
		fetch(apiUrl)
			// Parse the JSON response
			.then(response => response.json())
			.then(data => {
				// 'data' is expected to be an object keyed by postUid
				// Example:
				// {
				//   "123": { yeah: 5, nope: 1, score: "4" }
				// }

				// Loop over each post UID returned by the API
				Object.keys(data).forEach(postUid => {
					// get vote json data for the post uid
					const postData = data[postUid];
					
					// Replace "yeah" element
					const yeahElOld = document.getElementById("vote_yeah_" + postUid);
					if (yeahElOld && postData.yeah) {
						soudane.replaceElement(yeahElOld, postData.yeah);
					}

					// Replace "nope" element
					const nopeElOld = document.getElementById("vote_nope_" + postUid);
					if (nopeElOld && postData.nope) {
						soudane.replaceElement(nopeElOld, postData.nope);
					}

					// Replace "score" element
					const scoreElOld = document.getElementById("vote_score_" + postUid);
					if (scoreElOld && postData.score) {
						soudane.replaceElement(scoreElOld, postData.score);
					}

				});
			})
			// Log any network or parsing errors
			.catch(err => {
				console.error("Vote update failed:", err);
			});
	};

	soudane.replaceElement = function(targetElement, newContent) {
		const targetVoteContainer = targetElement.parentNode;
		const soudaneContainer = targetVoteContainer.parentNode;
		if (parent) {
			const temp = document.createElement('div');
			temp.innerHTML = newContent.trim();
			const newElement = temp.firstElementChild;
			if (newElement) {
				soudaneContainer.replaceChild(newElement, targetVoteContainer);
			}
		}
	}

	// once DOM content is loaded, update posts every 10 seconds
	document.addEventListener("DOMContentLoaded", function () {
		// initial load
		soudane.updatePosts();

		// repeat every 10 seconds
		setInterval(soudane.updatePosts, 10000);
	});

})();