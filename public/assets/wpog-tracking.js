/*
 * WP OpenGDPR — Admin-only Detection Probe
 *
 * Loaded only when an administrator is logged in. Scans the live page for
 * third-party assets and document.cookie entries and reports them to the
 * /wpog/v1/track REST endpoint. Reports are deduplicated per page load so
 * we don't hammer the endpoint.
 */
(function () {
    'use strict';

    var T = window.WPOG_TRACK || null;
    if (!T || !T.endpoint) { return; }

    var SAME_ORIGIN = (location.hostname || '').toLowerCase();
    var seen  = Object.create(null);
    var queue = [];
    var flushTimer = null;
    function key(type, value) { return type + '|' + value; }

    function record(type, value, extra) {
        if (!value) { return; }
        var k = key(type, value);
        if (seen[k]) { return; }
        seen[k] = 1;
        var item = { type: type, value: String(value).slice(0, 512), page_url: T.page_url };
        if (extra && extra.domain) { item.domain = extra.domain; }
        queue.push(item);
        scheduleFlush();
    }

    function scheduleFlush() {
        if (flushTimer) { return; }
        flushTimer = setTimeout(flush, 1500);
    }

    function flush() {
        flushTimer = null;
        if (!queue.length) { return; }
        var payload = { items: queue.splice(0, queue.length) };
        try {
            fetch(T.endpoint, {
                method:      'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce':   T.nonce
                },
                body: JSON.stringify(payload)
            }).catch(function () {});
        } catch (e) {}
    }

    function hostOf(url) {
        if (!url) { return ''; }
        try {
            var a = document.createElement('a');
            a.href = url;
            return (a.hostname || '').toLowerCase();
        } catch (e) { return ''; }
    }

    function scanAssets() {
        var selectors = {
            script: 'script[src]',
            iframe: 'iframe[src]',
            img:    'img[src]',
            link:   'link[href]'
        };
        Object.keys(selectors).forEach(function (type) {
            var nodes = document.querySelectorAll(selectors[type]);
            for (var i = 0; i < nodes.length; i++) {
                var n   = nodes[i];
                var url = (type === 'link') ? n.getAttribute('href') : n.getAttribute('src');
                if (!url) { continue; }
                // Ignore data: URLs — noise, not a third-party request.
                if (/^data:/i.test(url)) { continue; }
                record(type, url, { domain: hostOf(url) });
            }
        });
    }

    function scanCookies() {
        var raw = document.cookie || '';
        if (!raw) { return; }
        raw.split(';').forEach(function (pair) {
            var eq = pair.indexOf('=');
            var name = (eq > -1 ? pair.slice(0, eq) : pair).trim();
            if (!name) { return; }
            // Skip the plugin's own consent cookies — they aren't trackers and
            // would only add noise to the detections page.
            if (name === 'wpog_consent' || name === 'wpog_consent_id') { return; }
            record('cookie', name, { domain: SAME_ORIGIN });
        });
    }

    function fullScan() {
        scanAssets();
        scanCookies();
    }

    /* MutationObserver for assets added after load. */
    var ObserverCtor = window.MutationObserver || window.WebKitMutationObserver;
    if (ObserverCtor) {
        try {
            new ObserverCtor(function (mutations) {
                for (var i = 0; i < mutations.length; i++) {
                    var added = mutations[i].addedNodes;
                    if (!added) { continue; }
                    for (var j = 0; j < added.length; j++) {
                        var n = added[j];
                        if (!n || n.nodeType !== 1) { continue; }
                        if (n.matches && n.matches('script[src],iframe[src],img[src],link[href]')) {
                            checkOne(n);
                        }
                        if (n.querySelectorAll) {
                            var sub = n.querySelectorAll('script[src],iframe[src],img[src],link[href]');
                            for (var k = 0; k < sub.length; k++) { checkOne(sub[k]); }
                        }
                    }
                }
                // Cookies may be set later by scripts.
                scanCookies();
            }).observe(document.documentElement || document, { childList: true, subtree: true });
        } catch (e) {}
    }

    function checkOne(node) {
        var tag = node.tagName ? node.tagName.toLowerCase() : '';
        var url = (tag === 'link') ? node.getAttribute('href') : node.getAttribute('src');
        if (!url) { return; }
        if (/^data:/i.test(url)) { return; }
        record(tag, url, { domain: hostOf(url) });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', fullScan);
    } else {
        fullScan();
    }
    window.addEventListener('load', fullScan);
    // Final flush before navigation.
    window.addEventListener('beforeunload', flush);
})();
