(function () {
    var stickyIntervalId = null;
    var cfg = null;

    function startup() {
        var configNode = document.querySelector(".pmConfig");
        if (!configNode) {
            return;
        }

        cfg = {
            stickyRotateSeconds: Math.max(0, parseInt(configNode.dataset.stickyRotateSeconds || "45", 10)),
        };

        setupStickyRotation();
    }

    function setupStickyRotation() {
        if (window.matchMedia && window.matchMedia("(max-width: 650px)").matches) {
            return;
        }

        var tpl = document.getElementById("pmStickyTpl");
        if (!tpl) {
            return;
        }

        var dismissedUntil = parseInt(localStorage.getItem("pmStickyDismissedUntil") || "0", 10);
        if (dismissedUntil > Math.floor(Date.now() / 1000)) {
            return;
        }

        var clone = tpl.content.cloneNode(true);
        document.body.appendChild(clone);

        var stickyWrap = document.querySelector(".pmStickyWrap");
        var stickyFrame = document.querySelector(".pmStickyWrap .pmStickyContent");
        if (!stickyFrame) {
            return;
        }

        var closeBtn = document.querySelector(".pmStickyWrap .pmStickyClose");
        if (closeBtn) {
            closeBtn.addEventListener("click", function () {
                var wrap = stickyFrame.parentElement;
                if (wrap) {
                    wrap.style.display = "none";
                }
                if (stickyIntervalId !== null) {
                    clearInterval(stickyIntervalId);
                    stickyIntervalId = null;
                }
                var dismissUntil = Math.floor(Date.now() / 1000) + 3600;
                localStorage.setItem("pmStickyDismissedUntil", String(dismissUntil));
            });
        }

        var stickyAds;
        try {
            stickyAds = JSON.parse((stickyWrap && stickyWrap.dataset.ads) || "[]");
        } catch (e) {
            stickyAds = [];
        }

        if (!stickyAds.length || cfg.stickyRotateSeconds <= 0 || stickyAds.length < 2) {
            return;
        }

        // First ad is already pre-rendered server-side; start rotation from index 1.
        var stickyIndex = 1;
        function rotate() {
            setHtml(stickyFrame, stickyAds[stickyIndex]);
            stickyIndex = (stickyIndex + 1) % stickyAds.length;
        }
        stickyIntervalId = setInterval(rotate, cfg.stickyRotateSeconds * 1000);
    }

    async function fetchContent(url) {
        try {
            var response = await fetch(url, { credentials: "same-origin" });
            if (!response.ok) {
                return null;
            }
            var payload = await response.json();
            if (!payload || !payload.success || !payload.ad) {
                return null;
            }
            return payload.ad;
        } catch (error) {
            console.error("Failed to load media content", error);
            return null;
        }
    }

    function wrapContent(html) {
        return "<!DOCTYPE html><html><head><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\"><style>html,body{margin:0;padding:0;background:transparent;overflow:hidden;}img,iframe{max-width:100%;height:auto;display:block;}</style></head><body>" + html + "</body></html>";
    }

    function setHtml(container, html) {
        container.innerHTML = html;
        var scripts = container.querySelectorAll("script");
        for (var i = 0; i < scripts.length; i++) {
            var oldScript = scripts[i];
            var newScript = document.createElement("script");
            for (var j = 0; j < oldScript.attributes.length; j++) {
                newScript.setAttribute(oldScript.attributes[j].name, oldScript.attributes[j].value);
            }
            newScript.textContent = oldScript.textContent;
            oldScript.parentNode.replaceChild(newScript, oldScript);
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", startup);
    } else {
        startup();
    }
}());
