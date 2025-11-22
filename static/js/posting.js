document.addEventListener("DOMContentLoaded", function() {
    const postForm = document.getElementById("postform");
    const emailInput = document.getElementById("email");
    
    // Function to handle the AJAX form submission
    function submitPostForm(event) {
        event.preventDefault();  // Prevent the default form submission
        
        const emailValue = emailInput.value.trim().toLowerCase();
        const isReply = document.querySelector("input[name='resto']");
        const isNoko = emailValue.includes("noko");
        const isDump = emailValue.includes("dump");
        
        // If it's a reply and email contains 'noko'
        if (isReply && isNoko) {
            // Perform AJAX fetch request
            fetch(postForm.action, {
                method: 'POST',
                body: new FormData(postForm)
            })
            .then(response => response.json())
            .then(data => {
                // Ensure that the 'newPostId' exists in the response
                if (data.newPostId) {
                    // Run kkupdate.update()
                    kkupdate.update();

                    // Scroll to the new post ID
                    window.location.hash = '#p' + data.newPostId;
                }
            })
            .catch(error => console.error("Error during form submission:", error));

        } else if (isDump) {
            // If the email contains 'dump', just submit the form via AJAX and do nothing else
            fetch(postForm.action, {
                method: 'POST',
                body: new FormData(postForm)
            })
            .then(() => {
                // We don't need to do anything else after submitting the form for 'dump'
                console.log("Form submitted with 'dump' in email, but no further action.");
            })
            .catch(error => console.error("Error during form submission:", error));

        } else {
            // Allow normal form submission (when no 'noko' or 'dump' in email)
            postForm.submit();
        }
    }

    // Add the submit event listener to handle the form logic
    postForm.addEventListener('submit', submitPostForm);
});
