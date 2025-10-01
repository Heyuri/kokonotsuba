function showMessage(text, isSuccess) {
	let stackContainer = document.getElementById('messageStackContainer');
	if (!stackContainer) {
		stackContainer = document.createElement('div');
		stackContainer.id = 'messageStackContainer';
		stackContainer.style.position = 'fixed';
		stackContainer.style.top = '0';
		stackContainer.style.left = '0';
		stackContainer.style.right = '0';
		stackContainer.style.width = '100%';
		stackContainer.style.zIndex = '1000';
		stackContainer.style.pointerEvents = 'none'; // Prevent layout interference
		document.body.prepend(stackContainer);
	}

	const messageContainer = document.createElement('div');
	messageContainer.className = isSuccess ? 'theading3' : 'theading';
	messageContainer.style.margin = '10px auto';
	messageContainer.style.padding = '10px';
	messageContainer.style.width = '90%';
	messageContainer.style.maxWidth = '600px';
	messageContainer.style.boxSizing = 'border-box';
	messageContainer.style.display = 'flex';
	messageContainer.style.justifyContent = 'space-between';
	messageContainer.style.alignItems = 'center';
	messageContainer.style.pointerEvents = 'auto'; // Enable close button clicks

	const messageText = document.createElement('span');
	messageText.textContent = text;

	const closeBtn = document.createElement('span');
	closeBtn.textContent = '✖';
	closeBtn.style.cursor = 'pointer';
	closeBtn.style.marginLeft = '10px';
	closeBtn.addEventListener('click', function () {
		messageContainer.remove();
	});

	messageContainer.appendChild(messageText);
	messageContainer.appendChild(closeBtn);
	stackContainer.appendChild(messageContainer);

	setTimeout(() => {
		if (messageContainer.parentNode) {
			messageContainer.remove();
		}
	}, 5000);
}