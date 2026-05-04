(function () {
    'use strict';

    if (!window.attachmentWidget) {
        return;
    }

    window.attachmentWidget.registerActionHandler('toggleSpoiler', function (ctx) {
        var imageElement = ctx.container ? ctx.container.querySelector('img') : null;
        if (imageElement) {
            imageElement.style.opacity = 0.5;
        }

        var href = ctx.menuItem ? ctx.menuItem.href : '';
        var urlParams = new URL(href).searchParams;

        var csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
        var body = new FormData();
        body.append('csrf_token', csrfToken);
        body.append('postUid', urlParams.get('postUid') || '');
        body.append('fileId', urlParams.get('fileId') || '');

        // Routing params (load, moduleMode) stay in the URL; postUid/fileId go in the body
        var endpoint = new URL(href);
        endpoint.searchParams.delete('postUid');
        endpoint.searchParams.delete('fileId');

        fetch(endpoint.toString(), {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: body
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('HTTP error ' + response.status);
                }
                return response.json();
            })
            .then(function (data) {
                if (imageElement) {
                    imageElement.style.opacity = 1;
                    imageElement.src = data.thumbUrl;
                    if (data.thumbWidth) imageElement.width = data.thumbWidth;
                    if (data.thumbHeight) imageElement.height = data.thumbHeight;
                }

                // Update the hidden widget data so the next menu open shows the correct label
                if (ctx.bar) {
                    var widgetAnchor = ctx.bar.querySelector('.attachmentWidgetData a[data-action="toggleSpoiler"]');
                    if (widgetAnchor) {
                        var newLabel = data.active ? 'Remove spoiler' : 'Mark as spoiler';
                        widgetAnchor.dataset.label = newLabel;
                        widgetAnchor.textContent = newLabel;
                    }
                }

                // Toggle the "[Spoiler]" indicator label
                if (ctx.bar) {
                    var labelIndicator = ctx.bar.querySelector('.indicator-spoilerLabel');
                    if (labelIndicator) {
                        if (data.active) {
                            labelIndicator.classList.remove('indicatorHidden');
                        } else {
                            labelIndicator.classList.add('indicatorHidden');
                        }
                    }
                }

                showMessage(data.active ? 'Spoiler enabled' : 'Spoiler removed', true);
            })
            .catch(function (error) {
                if (imageElement) {
                    imageElement.style.opacity = 1;
                }
                showMessage('Error while toggling spoiler', false);
                console.error(error);
            });
    });

})();
