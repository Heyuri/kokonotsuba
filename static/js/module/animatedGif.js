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
                    if (widgetAnchor && data.label) {
                        widgetAnchor.dataset.label = data.label;
                        widgetAnchor.textContent = data.label;
                    }

                    // Toggle the "[Animated GIF]" / "[Animated WebP]" indicator
                    var labelIndicator = ctx.bar.querySelector('.indicator-animatedGifLabel');
                    if (labelIndicator) {
                        if (data.indicatorLabel) {
                            labelIndicator.textContent = data.indicatorLabel;
                        }
                        if (data.active) {
                            labelIndicator.classList.remove('indicatorHidden');
                        } else {
                            labelIndicator.classList.add('indicatorHidden');
                        }
                    }
                }

                showMessage(data.active ? 'Animation enabled!' : 'Animation disabled', true);
            })
            .catch(function (error) {
                if (imageElement) {
                    imageElement.style.opacity = 1;
                }
                showMessage('Error while toggling animation status', false);
                console.error(error);
            });
    });

})();
