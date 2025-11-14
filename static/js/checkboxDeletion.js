document.addEventListener('DOMContentLoaded', function () {
    const checkboxes = document.querySelectorAll('.deletionCheckbox');
    const userDeleteDiv = document.getElementById('userdelete');

    // Function to toggle the fixed position class based on checkbox status
    function updateUserDeletePosition() {
        let anyChecked = Array.from(checkboxes).some(checkbox => checkbox.checked);
        
        if (anyChecked) {
            // Add the class that applies fixed position, padding, etc.
            userDeleteDiv.classList.add('fixedPosition');
        } else {
            // Remove the class to reset the div to its original position
            userDeleteDiv.classList.remove('fixedPosition');
        }
    }

    // Set up event listeners for all checkboxes
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateUserDeletePosition);
    });

    // Initial check in case some checkboxes are already checked
    updateUserDeletePosition();
});
