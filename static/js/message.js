function showMessage(text, isSuccess) {
	let stackContainer = document.getElementById('messageStackContainer');
	if (!stackContainer) {
		stackContainer = document.createElement('div');
		stackContainer.id = 'messageStackContainer';
		stackContainer.classList.add('messageStackContainer');
		document.body.prepend(stackContainer);
	}

	const messageContainer = document.createElement('div');
	messageContainer.classList.add(
		'messageContainer',
		isSuccess ? 'messageSuccess' : 'messageFailure'
	);

	const messageText = document.createElement('span');
	messageText.classList.add('messageText');
	messageText.textContent = text;

	const closeBtn = document.createElement('span');
	closeBtn.classList.add('messageClose');
	closeBtn.textContent = '✖';
	closeBtn.addEventListener('click', function () {
		messageContainer.classList.remove('show');
		messageContainer.classList.add('hide');
		setTimeout(() => messageContainer.remove(), 400);
	});

	messageContainer.appendChild(messageText);
	messageContainer.appendChild(closeBtn);
	stackContainer.appendChild(messageContainer);

	// trigger fade-in
	requestAnimationFrame(() => messageContainer.classList.add('show'));

	// auto fade-out after 5s
	setTimeout(() => {
		if (messageContainer.parentNode) {
			messageContainer.classList.remove('show');
			messageContainer.classList.add('hide');
			setTimeout(() => messageContainer.remove(), 400);
		}
	}, 5000);
}
