/**
 * Terms & Conditions — client-side checkout validation.
 *
 * Adds immediate visual feedback on the WooCommerce classic checkout. The
 * authoritative validation stays server-side (wc_add_notice in PHP); this only
 * highlights the missing mandatory checkboxes and blocks the AJAX submit early.
 *
 * Also converts plain-text URLs in consent labels back to clickable links
 * (WooCommerce may strip HTML during AJAX fragment updates, and the Block
 * Checkout API does not support HTML in field labels — this script recovers
 * clickable links from the "text (URL)" pattern produced by the server side).
 */
(function ($) {
    'use strict';

    var REQUIRED = [
        'input[name="wpog_terms_consent"]',
        'input[name="wpog_privacy_consent_wc"]',
        'input[name="wpog_age_consent"]'
    ];

    function clearError() {
        $(this).closest('.wpog-consent-field').removeClass('wpog-consent-error');
    }

    $(document.body).on('change', REQUIRED.join(','), clearError);

    $(document.body).on('checkout_place_order', function () {
        var valid = true;

        REQUIRED.forEach(function (selector) {
            var $box = $(selector);
            if ($box.length && !$box.is(':checked')) {
                $box.closest('.wpog-consent-field').addClass('wpog-consent-error');
                valid = false;
            }
        });

        if (!valid) {
            var $first = $('.wpog-consent-error').first();
            if ($first.length) {
                $('html, body').animate(
                    { scrollTop: $first.offset().top - 120 },
                    300
                );
            }
        }

        return valid; // false blocks the WooCommerce submit
    });

    /* ---------- linkify plain-text URLs ---------- */

    /**
     * Convert "text (https://url)" inside a DOM element to a clickable <a>.
     * Returns true if the element was modified.
     *
     * Built with DOM APIs only (text nodes + textContent + setAttribute):
     * re-parsing label text as HTML would undo the server-side wp_kses()
     * and let a quote in a URL break out of the href attribute.
     */
    function makeLink(url, text) {
        var a = document.createElement('a');
        a.setAttribute('href', url);
        a.setAttribute('target', '_blank');
        a.setAttribute('rel', 'noopener');
        a.textContent = text;
        return a;
    }

    function isHttpUrl(url) {
        return /^https?:\/\/[^\s"'<>]+$/i.test(url);
    }

    function linkifyElement($el) {
        var el = $el[0];
        if (!el || $el.find('a').length) {
            return false; // Already has clickable links.
        }

        var text = el.textContent || '';
        var frag = document.createDocumentFragment();
        var last = 0;
        var changed = false;
        var m;

        // Pattern 1: "text (https://url)" → <a href="url">text</a>
        var re1 = /(\S[^(]*?)\s*\((https?:\/\/[^\s)"'<>]+)\)/g;
        while ((m = re1.exec(text)) !== null) {
            frag.appendChild(document.createTextNode(text.slice(last, m.index)));
            frag.appendChild(makeLink(m[2], $.trim(m[1])));
            last = re1.lastIndex;
            changed = true;
        }

        // Pattern 2: bare URLs (only when Pattern 1 did not match).
        if (!changed) {
            var re2 = /(https?:\/\/[^\s<>"']+)/g;
            while ((m = re2.exec(text)) !== null) {
                frag.appendChild(document.createTextNode(text.slice(last, m.index)));
                frag.appendChild(makeLink(m[1], m[1]));
                last = re2.lastIndex;
                changed = true;
            }
        }

        if (!changed) {
            return false;
        }
        frag.appendChild(document.createTextNode(text.slice(last)));
        el.textContent = '';
        el.appendChild(frag);
        return true;
    }

    /**
     * Scan the checkout page and convert plain-text consent URLs to clickable
     * links. Covers both the classic checkout (.wpog-consent-label) and the
     * Block Checkout (.wc-block-components-checkbox__label inside our wrapper).
     */
    function linkifyConsentLabels() {
        // ---- Classic Checkout ----
        $('.wpog-consent-label').each(function () {
            var $label = $(this);

            if (linkifyElement($label)) {
                return;
            }

            // Fallback: no URL in text — use data-wpog-url attribute.
            var fallbackUrl = $label.attr('data-wpog-url');
            if (fallbackUrl && isHttpUrl(fallbackUrl)) {
                var labelText = $.trim($label.text());
                if (labelText) {
                    $label.empty().append(makeLink(fallbackUrl, labelText));
                }
            }
        });

        // ---- Block Checkout ----
        //
        // The Block Checkout (WooCommerce Additional Checkout Fields API) renders
        // our consent fields with wrapper classes like:
        //   wc-block-components-address-form__wpog-terms_consent
        //   wc-block-components-address-form__wpog-privacy_consent
        //
        // Inside each wrapper the label text lives in:
        //   <span class="wc-block-components-checkbox__label">text (URL)</span>
        $('[class*="wpog-terms_consent"] .wc-block-components-checkbox__label,' +
          '[class*="wpog-privacy_consent"] .wc-block-components-checkbox__label,' +
          '[class*="wpog-marketing_consent"] .wc-block-components-checkbox__label').each(function () {
            linkifyElement($(this));
        });
    }

    // Run on first load.
    $(linkifyConsentLabels);

    // Re-run after every WooCommerce AJAX checkout update (shipping method
    // change, coupon application, etc. — all of which replace DOM fragments).
    $(document.body).on('updated_checkout', linkifyConsentLabels);

})(jQuery);
