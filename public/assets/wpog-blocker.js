/*
 * WP OpenGDPR — Domain Autoblocker
 *
 * Loaded as the FIRST script in <head>. Intercepts dynamically-created
 * elements (script/iframe/img/link/audio/video/source/embed/object/picture)
 * whose src or href belong to a configured blocked domain, until the user
 * has consented to the relevant category.
 *
 * Strategy:
 *   1. Override document.createElement: when a "blockable" element is created,
 *      redefine its `src`/`href` setter so assigning a blocked URL stores the
 *      value into `data-wpog-src` instead of triggering a network request.
 *   2. MutationObserver on document.documentElement to catch elements parsed
 *      from static HTML or inserted by other scripts that bypass createElement.
 *   3. Override HTMLElement.prototype.attachShadow so observation extends into
 *      shadow roots.
 *   4. On `wpog:consent`, re-scan elements bearing the placeholder class and
 *      restore the original URL when the rule's category is now allowed.
 *
 * Config (window.WPOG_BLOCKER_CONFIG):
 *   {
 *     rules:       [ { domain, path, category, note, active } ],
 *     consent:     { necessary, functional, analytics, marketing },
 *     sameOrigin:  'example.com',
 *     placeholder: 'wpog-lazyload'
 *   }
 *
 * Path-based rules: if a rule has a non-empty `path`, only URLs whose pathname
 * starts with that path are matched.  This allows fine-grained blocking of
 * individual scripts on shared CDN domains (e.g. block /maps/api on google.com
 * while leaving other Google services unblocked).
 */
(function () {
    'use strict';

    var CFG = window.WPOG_BLOCKER_CONFIG || null;
    if (!CFG || !Array.isArray(CFG.rules) || !CFG.rules.length) {
        return;
    }

    var BLOCKABLE = ['script', 'iframe', 'img', 'link', 'audio', 'video',
                     'source', 'embed', 'object', 'picture'];

    var PLACEHOLDER_CLASS = CFG.placeholder || 'wpog-lazyload';
    var SAME_ORIGIN = (CFG.sameOrigin || location.hostname || '').toLowerCase();

    // Index rules by domain for fast lookup; longest-suffix match supported.
    var RULES = CFG.rules.slice();

    function consentMap() {
        // Always re-read in case it changed (consent.js may update window.wpogConsent).
        var c = window.wpogConsent || CFG.consent || {};
        return {
            necessary:  true,
            functional: !!c.functional,
            analytics:  !!c.analytics,
            marketing:  !!c.marketing
        };
    }

    function urlToDomain(url) {
        if (!url) { return ''; }
        try {
            // Absolute URLs.
            if (/^[a-z][a-z0-9+\-.]*:/i.test(url) || url.indexOf('//') === 0) {
                var a = document.cmpblockerCreateElement
                    ? document.cmpblockerCreateElement.call(document, 'a')
                    : nativeCreateElement.call(document, 'a');
                a.href = (url.indexOf('//') === 0) ? location.protocol + url : url;
                return (a.hostname || '').toLowerCase();
            }
        } catch (e) {}
        return ''; // relative URL → same-origin
    }

    function urlToPath(url) {
        if (!url) { return ''; }
        try {
            var a = document.cmpblockerCreateElement
                ? document.cmpblockerCreateElement.call(document, 'a')
                : nativeCreateElement.call(document, 'a');
            a.href = url;
            return a.pathname || '';
        } catch (e) {}
        return '';
    }

    function findRule(url, domain) {
        if (!domain) { return null; }
        domain = domain.toLowerCase().replace(/^www\./, '');
        var urlPath = '';
        for (var i = 0; i < RULES.length; i++) {
            var r = RULES[i];
            if (!r || !r.active || !r.domain) { continue; }
            var rd = r.domain.toLowerCase().replace(/^www\./, '');
            if (domain !== rd && !domain.endsWith('.' + rd)) { continue; }
            // Domain matches. If rule specifies a path prefix, check it too.
            if (r.path && r.path !== '/') {
                if (!urlPath) { urlPath = urlToPath(url); }
                if (!urlPath.startsWith(r.path)) { continue; }
            }
            return r;
        }
        return null;
    }

    function shouldBlock(url) {
        var domain = urlToDomain(url);
        if (!domain) { return null; }                     // relative / non-http
        if (domain === SAME_ORIGIN) { return null; }      // same origin: never block
        if (domain.endsWith('.' + SAME_ORIGIN)) { return null; }
        var rule = findRule(url, domain);
        if (!rule) { return null; }
        var cm = consentMap();
        if (cm[rule.category]) { return null; }           // already consented
        return rule;
    }

    /* ---------- createElement override ---------- */

    var nativeCreateElement = document.createElement;
    document.cmpblockerCreateElement = nativeCreateElement;

    document.createElement = function (tagName, options) {
        var el = nativeCreateElement.apply(document, arguments);
        var t  = (typeof tagName === 'string') ? tagName.toLowerCase() : '';
        if (BLOCKABLE.indexOf(t) === -1) { return el; }

        defineBlockedAttr(el, 'src');
        if (t === 'link' || t === 'a') {
            defineBlockedAttr(el, 'href');
        }
        return el;
    };

    function defineBlockedAttr(el, attr) {
        try {
            Object.defineProperty(el, attr, {
                configurable: true,
                enumerable:   true,
                get: function () {
                    return el.getAttribute(attr) || '';
                },
                set: function (value) {
                    var rule = shouldBlock(value);
                    if (rule) {
                        el.setAttribute('data-wpog-src',      value);
                        el.setAttribute('data-wpog-category', rule.category);
                        el.setAttribute('data-wpog-domain',   rule.domain);
                        if (el.classList) { el.classList.add(PLACEHOLDER_CLASS); }
                        // Set src to empty/about:blank to avoid request.
                        el.setAttribute(attr, '');
                        if (el.tagName && el.tagName.toLowerCase() === 'script') {
                            el.type = 'text/plain';
                        }
                    } else {
                        el.setAttribute(attr, value);
                    }
                }
            });
        } catch (e) {
            // Some hosts (older WebKit) may already define src non-configurable;
            // MutationObserver below is our fallback.
        }
    }

    /* ---------- MutationObserver ---------- */

    function checkNode(node) {
        if (!node || node.nodeType !== 1) { return; }
        var tag = node.tagName ? node.tagName.toLowerCase() : '';
        if (BLOCKABLE.indexOf(tag) === -1) { return; }
        if (node.classList && node.classList.contains(PLACEHOLDER_CLASS)) { return; }
        if (node.hasAttribute && node.hasAttribute('data-wpog-blocked')) { return; }

        var attr = (tag === 'link') ? 'href' : 'src';
        var url  = node.getAttribute(attr);
        if (!url) { return; }

        var rule = shouldBlock(url);
        if (!rule) { return; }
        freezeNode(node, attr, url, rule);
    }

    function freezeNode(node, attr, url, rule) {
        // Mark before swap so we don't process the replacement again.
        node.setAttribute('data-wpog-blocked', '1');
        node.setAttribute('data-wpog-src', url);
        node.setAttribute('data-wpog-category', rule.category);
        node.setAttribute('data-wpog-domain', rule.domain);
        if (node.classList) { node.classList.add(PLACEHOLDER_CLASS); }

        if (node.tagName.toLowerCase() === 'script') {
            // Replace the script node with a neutered clone so the browser never
            // executes it. The clone keeps the same attributes for later restore.
            var clone = nativeCreateElement.call(document, 'script');
            for (var i = 0; i < node.attributes.length; i++) {
                var a = node.attributes[i];
                clone.setAttribute(a.name, a.value);
            }
            clone.type = 'text/plain';
            clone.removeAttribute('src');
            if (node.parentNode) {
                node.parentNode.replaceChild(clone, node);
            }
        } else {
            // For iframe/img/link/etc., just blank the URL attribute.
            node.removeAttribute(attr);
        }
    }

    function scan(root) {
        if (!root || !root.querySelectorAll) { return; }
        var sel = BLOCKABLE.join(',');
        var nodes = root.querySelectorAll(sel);
        for (var i = 0; i < nodes.length; i++) {
            checkNode(nodes[i]);
        }
    }

    var ObserverCtor = window.MutationObserver || window.WebKitMutationObserver;
    if (ObserverCtor) {
        var observer = new ObserverCtor(function (mutations) {
            for (var i = 0; i < mutations.length; i++) {
                var m = mutations[i];
                if (m.addedNodes) {
                    for (var j = 0; j < m.addedNodes.length; j++) {
                        var n = m.addedNodes[j];
                        checkNode(n);
                        if (n && n.querySelectorAll) { scan(n); }
                    }
                }
            }
        });
        try {
            observer.observe(document.documentElement || document, {
                childList: true, subtree: true
            });
        } catch (e) {}
    }

    /* ---------- Shadow DOM ---------- */

    if (typeof HTMLElement !== 'undefined' && HTMLElement.prototype.attachShadow) {
        var nativeAttachShadow = HTMLElement.prototype.attachShadow;
        document.cmpblockerAttachShadow = nativeAttachShadow;
        HTMLElement.prototype.attachShadow = function (options) {
            var root = nativeAttachShadow.call(this, options);
            if (ObserverCtor) {
                try {
                    new ObserverCtor(function (mutations) {
                        for (var i = 0; i < mutations.length; i++) {
                            var m = mutations[i];
                            if (!m.addedNodes) { continue; }
                            for (var j = 0; j < m.addedNodes.length; j++) {
                                checkNode(m.addedNodes[j]);
                            }
                        }
                    }).observe(root, { childList: true, subtree: true });
                } catch (e) {}
            }
            return root;
        };
    }

    /* ---------- Sync scans on DOM lifecycle ---------- */

    function fullScan() { scan(document); }
    document.addEventListener('DOMContentLoaded', fullScan);
    document.addEventListener('readystatechange', fullScan);
    window.addEventListener('load', fullScan);
    // Safety net.
    setTimeout(fullScan, 50);
    setTimeout(fullScan, 200);

    /* ---------- Unblock on consent change ---------- */

    function unblock() {
        var cm = consentMap();
        var nodes = document.querySelectorAll('.' + PLACEHOLDER_CLASS);
        for (var i = 0; i < nodes.length; i++) {
            var n   = nodes[i];
            var cat = n.getAttribute('data-wpog-category');
            var url = n.getAttribute('data-wpog-src');
            if (!cat || !url) { continue; }
            if (!cm[cat]) { continue; }

            var tag = n.tagName ? n.tagName.toLowerCase() : '';
            if (tag === 'script') {
                // Recreate as live script.
                var s = nativeCreateElement.call(document, 'script');
                for (var k = 0; k < n.attributes.length; k++) {
                    var a = n.attributes[k];
                    if (a.name === 'type' || a.name === 'data-wpog-src' ||
                        a.name === 'data-wpog-blocked' || a.name === 'class') { continue; }
                    s.setAttribute(a.name, a.value);
                }
                s.src = url;
                if (n.parentNode) {
                    n.parentNode.replaceChild(s, n);
                }
            } else {
                var attr = (tag === 'link') ? 'href' : 'src';
                n.setAttribute(attr, url);
                n.classList.remove(PLACEHOLDER_CLASS);
                n.setAttribute('data-wpog-unblocked', '1');
            }
        }
    }

    document.addEventListener('wpog:consent', unblock);

    // Expose minimal API for diagnostics.
    window.WPOG_BLOCKER = {
        rescan: fullScan,
        unblock: unblock,
        rules: RULES
    };
})();
