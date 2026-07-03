document.addEventListener('DOMContentLoaded', function () {
    const checkboxes = document.querySelectorAll('.deletionCheckbox');
    const userDeleteDiv = document.getElementById('userdelete');

    // Anchor for shift-click range selection: the last checkbox the user toggled.
    let lastChecked = null;

    // Function to toggle the fixed position class based on checkbox status
    function updateUserDeletePosition() {
        if (!userDeleteDiv) {
            return;
        }

        let anyChecked = Array.from(checkboxes).some(checkbox => checkbox.checked);

        if (anyChecked) {
            // Add the class that applies fixed position, padding, etc.
            userDeleteDiv.classList.add('fixedPosition');
        } else {
            // Remove the class to reset the div to its original position
            userDeleteDiv.classList.remove('fixedPosition');
        }
    }

    // Shift-click "fill in": set every checkbox between the anchor and the
    // clicked checkbox to the clicked checkbox's new state.
    function handleCheckboxClick(event) {
        const target = event.target;

        if (event.shiftKey && lastChecked && lastChecked !== target) {
            const list = Array.from(checkboxes);
            const start = list.indexOf(lastChecked);
            const end = list.indexOf(target);

            if (start !== -1 && end !== -1) {
                const [from, to] = start < end ? [start, end] : [end, start];
                for (let i = from; i <= to; i++) {
                    list[i].checked = target.checked;
                }
            }
        }

        lastChecked = target;
        updateUserDeletePosition();
    }

    // Set up event listeners for all checkboxes
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('click', handleCheckboxClick);
    });

    // Initial check in case some checkboxes are already checked
    updateUserDeletePosition();
});
