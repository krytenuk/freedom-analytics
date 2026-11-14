/*!
 * Freedom Analytics tracker
 *
 *   <script async src="https://analytics.example.com/fa.js" data-site="SITE_KEY"></script>
 *
 * Options, as attributes of the script tag:
 *   data-site        the site key (required)
 *   data-cookieless  keep no visitor id in a cookie
 *   data-spa         record a page view when a single page app changes the address
 *   data-outbound    record clicks on links to other sites as events
 *   data-downloads   record clicks on links to files as events
 *   data-manual      record nothing until fa('pageview') is called
 *
 * The page can call, before or after this script loads:
 *   window.fa = window.fa || function () { (fa.q = fa.q || []).push(arguments); };
 *   fa('pageview');                                   // or fa('pageview', {url: ..., title: ...})
 *   fa('event', category, action, label, value);
 */
(function (window, document) {
    'use strict';

    var script = document.currentScript || document.querySelector('script[data-site][src*="fa.js"]');
    if (!script || window.__faLoaded) {
        return;
    }
    window.__faLoaded = true;

    var siteKey = script.getAttribute('data-site');
    var endpoint = new URL('collect', script.src).href;
    var COOKIE = '_fa_vid';
    var DOWNLOADS = /\.(pdf|zip|rar|7z|gz|docx?|xlsx?|pptx?|csv|txt|rtf|odt|ods|mp3|mp4|mov|avi|dmg|exe|msi|apk|epub)$/i;
    var lastUrl = null;

    function option(name) {
        return script.hasAttribute('data-' + name) && script.getAttribute('data-' + name) !== 'false';
    }

    // Browsers that ask not to be tracked get no cookie. The server decides whether to record them at all.
    function doNotTrack() {
        return navigator.doNotTrack === '1' || window.doNotTrack === '1' || navigator.globalPrivacyControl === true;
    }

    function randomId() {
        var bytes = new Uint8Array(12);
        (window.crypto || window.msCrypto).getRandomValues(bytes);
        return Array.prototype.map.call(bytes, function (b) { return ('0' + b.toString(16)).slice(-2); }).join('');
    }

    function visitorId() {
        if (option('cookieless') || doNotTrack()) {
            return null;
        }
        var match = document.cookie.match(new RegExp('(?:^|; )' + COOKIE + '=([A-Za-z0-9_-]{8,64})'));
        var id = match ? match[1] : randomId();
        // Renewed on each visit, for a year
        var secure = location.protocol === 'https:' ? '; Secure' : '';
        document.cookie = COOKIE + '=' + id + '; Max-Age=31536000; Path=/; SameSite=Lax' + secure;
        return id;
    }

    function send(params) {
        if (!siteKey) {
            return;
        }
        params.s = siteKey;
        var vid = visitorId();
        if (vid) {
            params.v = vid;
        }
        var body = new URLSearchParams();
        Object.keys(params).forEach(function (key) {
            if (params[key] !== undefined && params[key] !== null && params[key] !== '') {
                body.append(key, String(params[key]));
            }
        });

        if (navigator.sendBeacon && navigator.sendBeacon(endpoint, body)) {
            return;
        }
        // Older browsers, or a full beacon queue
        var image = new Image(1, 1);
        image.src = endpoint + '?' + body.toString() + '&z=' + Date.now();
    }

    function pageview(options) {
        options = options || {};
        var url = options.url || location.href;
        // A change of fragment alone, such as a jump to #section, is the same page
        if (lastUrl !== null && url.split('#')[0] === lastUrl.split('#')[0] && !options.force) {
            return;
        }
        var referrer = lastUrl || document.referrer;
        lastUrl = url;
        send({
            t: 'pageview',
            u: url,
            ti: options.title || document.title,
            r: options.referrer || referrer,
            sr: screen.width + 'x' + screen.height,
            la: navigator.language || navigator.userLanguage
        });
    }

    function event(category, action, label, value) {
        if (!action) {
            return;
        }
        send({t: 'event', u: location.href, ec: category, ea: action, el: label, ev: value});
    }

    function command(args) {
        var name = args[0];
        if (name === 'pageview') {
            pageview(args[1]);
        } else if (name === 'event') {
            event(args[1], args[2], args[3], args[4]);
        }
    }

    // Commands queued before the script loaded, then direct calls from now on
    var queued = (window.fa && window.fa.q) || [];
    window.fa = function () {
        command(arguments);
    };
    window.fa.q = [];

    if (!option('manual')) {
        pageview();
    }
    for (var i = 0; i < queued.length; i++) {
        command(queued[i]);
    }

    if (option('spa')) {
        var push = history.pushState;
        history.pushState = function () {
            push.apply(this, arguments);
            setTimeout(pageview, 0);
        };
        var replace = history.replaceState;
        history.replaceState = function () {
            replace.apply(this, arguments);
            setTimeout(pageview, 0);
        };
        window.addEventListener('popstate', function () { pageview(); });
    }

    if (option('outbound') || option('downloads')) {
        document.addEventListener('click', function (e) {
            var link = e.target && e.target.closest ? e.target.closest('a[href]') : null;
            if (!link) {
                return;
            }
            var href = link.href;
            if (option('downloads') && DOWNLOADS.test(link.pathname)) {
                event('Downloads', 'Download', href);
            } else if (option('outbound') && link.hostname && link.hostname !== location.hostname
                && /^https?:$/.test(link.protocol)) {
                event('Outbound Links', 'Click', href);
            }
        }, true);
    }
})(window, document);
