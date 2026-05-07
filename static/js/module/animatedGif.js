(function () {
    'use strict';

    if (!window.attachmentWidget) {
        return;
    }

    window.attachmentWidget.registerActionHandler('animateGif', function (ctx) {
        var imageElement = ctx.container ? ctx.container.querySelector('img') : null;
        if (imageElement) {
            imageElement.style.opacity = 0.5;
        }

        fetch(ctx.menuItem.href, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
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
                    imageElement.src = data.attachmentUrl;
                }

                // Update the hidden widget data so the next menu open shows the correct label
                if (ctx.bar) {
                    var widgetAnchor = ctx.bar.querySelector('.attachmentWidgetData a[data-action="animateGif"]');
                    if (widgetAnchor) {
                        var newLabel = data.active ? 'Use still image of GIF' : 'Use animated GIF';
                        widgetAnchor.dataset.label = newLabel;
                        widgetAnchor.textContent = newLabel;
                    }

                    // Toggle the "[Animated GIF]" indicator label
                    var labelIndicator = ctx.bar.querySelector('.indicator-animatedGifLabel');
                    if (labelIndicator) {
                        if (data.active) {
                            labelIndicator.classList.remove('indicatorHidden');
                        } else {
                            labelIndicator.classList.add('indicatorHidden');
                        }
                    }
                }

                showMessage(data.active ? 'GIF animated!' : 'GIF animation disabled', true);
            })
            .catch(function (error) {
                if (imageElement) {
                    imageElement.style.opacity = 1;
                }
                showMessage('Error while toggling GIF animate status', false);
                console.error(error);
            });
    });

})();
