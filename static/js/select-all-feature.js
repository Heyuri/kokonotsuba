// Utility to initialize select all functionality
function setupSelectAllFeature(linkId, rowId) {
	const link = document.getElementById(linkId);

	if (!link) return;

	const updateLinkText = (checkboxes) => {
		const allChecked = Array.from(checkboxes).every(checkbox => checkbox.checked);
		link.innerHTML = allChecked ? '[<a>Unselect all</a>]' : '[<a>Select all</a>]';
	};

	link.addEventListener('click', function(event) {
		const anchor = event.target.closest('a');
		if (anchor) {
			event.preventDefault();
			const row = document.getElementById(rowId);
			if (row) {
				const checkboxes = row.querySelectorAll('input[type="checkbox"]');
				const allChecked = Array.from(checkboxes).every(checkbox => checkbox.checked);
				checkboxes.forEach(checkbox => checkbox.checked = !allChecked);
				updateLinkText(checkboxes);
			}
		}
	});		

	const row = document.getElementById(rowId);
	if (row) {
		const checkboxes = row.querySelectorAll('input[type="checkbox"]');
		checkboxes.forEach(checkbox => {
			checkbox.addEventListener('change', () => {
				updateLinkText(checkboxes);
			});
		});
		updateLinkText(checkboxes);
	}
}

// Initialize select-all links (run only after DOM is ready)
document.addEventListener('DOMContentLoaded', () => {
	setupSelectAllFeature('roleselectall', 'rolerow');
	setupSelectAllFeature('boardselectall', 'boardrow');
	setupSelectAllFeature('overboardselectall', 'overboardFilterList');
});
