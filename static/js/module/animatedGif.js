document.addEventListener('DOMContentLoaded', function () {
    // Add event listener for all the admin GIF toggle links
    document.querySelectorAll('.adminGIFFunction a').forEach(function (anchor) {
        anchor.addEventListener('click', handleGifToggle);
    });

    // Function to handle the GIF toggle request
    function handleGifToggle(e) {
        e.preventDefault(); // Prevent the default anchor redirect behavior

        const anchor = e.target; // The clicked anchor
        const anchorUrl = anchor.href; // Get the URL from the href attribute
        const attachmentContainer = anchor.closest('.attachmentContainer'); // Find the parent container for the attachment
        const imageElement = attachmentContainer.querySelector('img'); // Find the associated image
        const filesizeDiv = attachmentContainer.querySelector('.filesize'); // Find the filesize div

        // Set opacity to 0.5 while the request is being processed
        imageElement.style.opacity = 0.5;

        // Perform the fetch request
        fetch(anchorUrl, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest' // Indicate that this is an AJAX request
            }
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                // Reset the opacity of the image
                imageElement.style.opacity = 1;

                // Show a success message based on whether the GIF is animated
                const message = data.active
                    ? 'GIF animated!'
                    : 'GIF animation disabled';

                showMessage(message, true);

                // Update the image source to the new attachmentUrl from the response
                imageElement.src = data.attachmentUrl;

                // Replace the old GIF toggle button with the new one
                const oldGifButtonContainer = anchor.closest('.adminGIFFunction');
                oldGifButtonContainer.innerHTML = data.newGifButton;

                // Reattach the event listener to the new anchor (in case it has been replaced)
                const newAnchor = oldGifButtonContainer.querySelector('a');
                newAnchor.addEventListener('click', handleGifToggle);

                // Add or remove the "[Animated GIF]" label
                if (data.active) {
                    // Add the label if it's not already there
                    if (!filesizeDiv.querySelector('.animatedGIFLabel')) {
                        const label = document.createElement('span');
                        label.className = 'animatedGIFLabel imageOptions';
                        label.textContent = '[Animated GIF]';
                        filesizeDiv.appendChild(label);
                    }
                } else {
                    // Remove the label if it exists
                    const label = filesizeDiv.querySelector('.animatedGIFLabel');
                    if (label) {
                        filesizeDiv.removeChild(label);
                    }
                }
            })
            .catch(error => {
                // Reset the opacity if there's an error
                imageElement.style.opacity = 1;

                // Show an error message
                showMessage('Error while toggling GIF animate status', false);
                console.error(error);
            });
    }
});
