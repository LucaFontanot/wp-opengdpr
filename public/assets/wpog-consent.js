/* WP OpenGDPR — frontend logic */
(function () {
    'use strict';

    var D = window.WPOG_DATA || {};
    var BANNER, POPUP, FAB;

    function uuid() {
        if (window.crypto && crypto.randomUUID) { return crypto.randomUUID(); }
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
            var r = Math.random() * 16 | 0, v = c === 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }

    function readCookie(name) {
        var m = document.cookie.match(new RegExp('(?:^|;\\s*)' + name + '=([^;]+)'));
        return m ? decodeURIComponent(m[1]) : null;
    }

    function writeCookie(name, value, days) {
        var d = new Date();
        d.setTime(d.getTime() + (days * 24 * 60 * 60 * 1000));
        var attrs = '; expires=' + d.toUTCString() + '; path=/; SameSite=' + (D.samesite || 'Lax');
        if (D.secure) { attrs += '; Secure'; }
        document.cookie = name + '=' + encodeURIComponent(value) + attrs;
    }

    function loadConsent() {
        var raw = readCookie(D.cookie_name);
        if (!raw) { return null; }
        try {
            var data = JSON.parse(raw);
            if (data.version && data.version !== D.version) { return null; }
            return data;
        } catch (e) { return null; }
    }

    function saveConsent(action, cats) {
        var id = readCookie(D.cookie_id) || uuid();
        var payload = {
            id: id,
            date: new Date().toISOString(),
            version: D.version,
            categories: {
                necessary: true,
                functional: !!cats.functional,
                analytics: !!cats.analytics,
                marketing: !!cats.marketing
            },
            action: action
        };
        writeCookie(D.cookie_id, id, D.duration);
        writeCookie(D.cookie_name, JSON.stringify(payload), D.duration);
        window.wpogConsent = payload.categories;

        // Server-side log.
        var body = new FormData();
        body.append('action', 'wpog_save_consent');
        body.append('nonce', D.nonce);
        body.append('payload', JSON.stringify(payload));
        try { fetch(D.ajax_url, { method: 'POST', credentials: 'same-origin', body: body }); } catch (e) {}

        activateAll(payload.categories);
        document.dispatchEvent(new CustomEvent('wpog:consent', { detail: payload }));
    }

    function activateAll(cats) {
        Object.keys(cats).forEach(function (cat) {
            if (cats[cat]) { activateScripts(cat); activateIframes(cat); }
        });
    }

    function activateScripts(category) {
        var nodes = document.querySelectorAll('script[type="text/plain"][data-wpog-category="' + category + '"]');
        nodes.forEach(function (s) {
            var ns = document.createElement('script');
            // Copy attributes except type / data-wpog-src.
            for (var i = 0; i < s.attributes.length; i++) {
                var a = s.attributes[i];
                if (a.name === 'type' || a.name === 'data-wpog-src') { continue; }
                ns.setAttribute(a.name, a.value);
            }
            var src = s.getAttribute('data-wpog-src');
            if (src) { ns.src = src; } else { ns.textContent = s.textContent; }
            s.parentNode.replaceChild(ns, s);
        });
    }

    function activateIframes(category) {
        var nodes = document.querySelectorAll('iframe[data-src][data-wpog-category="' + category + '"]');
        nodes.forEach(function (n) {
            n.src = n.getAttribute('data-src');
            n.removeAttribute('data-src');
        });
    }

    function showBanner() { if (BANNER) { BANNER.hidden = false; hideFab(); } }
    function hideBanner() { if (BANNER) { BANNER.hidden = true; } }
    function showPopup()  { if (POPUP)  { POPUP.hidden  = false; } }
    function hidePopup()  { if (POPUP)  { POPUP.hidden  = true;  } }
    function showFab()    { if (FAB)    { FAB.hidden     = false; } }
    function hideFab()    { if (FAB)    { FAB.hidden     = true;  } }

    function getCustomCats() {
        var out = { necessary: true, functional: false, analytics: false, marketing: false };
        document.querySelectorAll('#wpog-popup input[data-wpog-cat]').forEach(function (inp) {
            out[inp.getAttribute('data-wpog-cat')] = inp.checked;
        });
        return out;
    }

    function handleAction(action) {
        if (action === 'accept_all') {
            saveConsent('accept_all', { functional: true, analytics: true, marketing: true });
            hidePopup(); hideBanner(); showFab();
        } else if (action === 'reject_all') {
            saveConsent('reject_all', { functional: false, analytics: false, marketing: false });
            hidePopup(); hideBanner(); showFab();
        } else if (action === 'save') {
            saveConsent('custom', getCustomCats());
            hidePopup(); hideBanner(); showFab();
        } else if (action === 'customize') {
            showPopup();
        } else if (action === 'close') {
            hidePopup();
        }
    }

    function bind(root) {
        if (!root) { return; }
        root.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-wpog-action]');
            if (!btn) { return; }
            handleAction(btn.getAttribute('data-wpog-action'));
        });
    }

    function bindDocument() {
        // Catch clicks on FAB and shortcode buttons that live outside BANNER/POPUP.
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-wpog-action]');
            if (!btn) { return; }
            // Already handled by BANNER/POPUP listeners.
            if ((BANNER && BANNER.contains(btn)) || (POPUP && POPUP.contains(btn))) { return; }
            handleAction(btn.getAttribute('data-wpog-action'));
        });
    }

    function setupReopenLinks() {
        document.querySelectorAll('a[href="#wpog-settings"], .wpog-reopen').forEach(function (a) {
            a.addEventListener('click', function (e) { e.preventDefault(); showPopup(); });
        });
    }

    function init() {
        BANNER = document.getElementById('wpog-banner');
        POPUP  = document.getElementById('wpog-popup-overlay');
        FAB    = document.getElementById('wpog-fab');
        bind(BANNER);
        bind(POPUP);
        bindDocument();
        setupReopenLinks();

        var consent = loadConsent();
        if (consent) {
            window.wpogConsent = consent.categories;
            activateAll(consent.categories);
            hideBanner();
            showFab();
        } else {
            showBanner();
            hideFab();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Public API.
    window.WPOG = {
        open: showPopup,
        getConsent: loadConsent,
        revoke: function () {
            writeCookie(D.cookie_name, '', -1);
            writeCookie(D.cookie_id, '', -1);
            location.reload();
        }
    };
})();
