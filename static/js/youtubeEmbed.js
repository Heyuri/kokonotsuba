function embed_youtube(el) {
  const comment = el.closest('.comment');
  const videoId = el.getAttribute('data-uid');
  const existingContainer = comment.querySelector('.youtube-container');

  if (existingContainer) {
    // Video is already embedded → remove it
    existingContainer.remove();
    el.textContent = "(embed)";
  } else {
    // Create a container to place below the comment
    const container = document.createElement('div');
    container.className = 'youtube-container';
    container.style.marginTop = "8px"; // optional spacing

    const iframe = document.createElement('iframe');
    iframe.width = "560";
    iframe.height = "315";
    iframe.src = `https://www.youtube.com/embed/${videoId}`;
    iframe.allow = "accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture";
    iframe.allowFullscreen = true;
    iframe.className = "youtube-embed";

    container.appendChild(iframe);
    
    // Append container at the end of the comment
    comment.appendChild(container);

    // Change the link to unembed
    el.textContent = "(unembed)";
  }
}