function parseMessageWithLinks(text) {
    const container = document.createElement('span');

    // Regex: [url=URL]TEXT[/url]
    const urlRegex = /\[url=(.+?)\](.+?)\[\/url\]/g;

    let lastIndex = 0;
    let match;

    while ((match = urlRegex.exec(text)) !== null) {
        // Add text before the link
        if (match.index > lastIndex) {
            container.appendChild(document.createTextNode(text.substring(lastIndex, match.index)));
        }

        // Create the link element
        const a = document.createElement('a');
        a.href = match[1];
        a.textContent = match[2];
        a.target = '_blank';
        a.rel = 'noopener noreferrer';

        container.appendChild(a);
        lastIndex = urlRegex.lastIndex;
    }

    // Add remaining text
    if (lastIndex < text.length) {
        container.appendChild(document.createTextNode(text.substring(lastIndex)));
    }

    return container;
}

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
	messageText.appendChild(parseMessageWithLinks(text));

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
