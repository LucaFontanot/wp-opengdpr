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
     */
    function linkifyElement($el) {
        if ($el.find('a').length) {
            return false; // Already has clickable links.
        }

        var html = $el.html();
        var changed = false;

        // Pattern 1: "text (https://url)" → <a href="url">text</a>
        // Run this first because Pattern 2 would re-match URLs inside
        // the href attributes we just inserted, producing broken HTML.
        html = html.replace(
            /(\S[^(]*?)\s*\(((?:https?:\/\/)[^\s)]+)\)/g,
            function (match, text, url) {
                changed = true;
                return '<a href="' + url + '" target="_blank" rel="noopener">' + $.trim(text) + '</a>';
            }
        );

        // Pattern 2: bare URLs not already wrapped in <a>.
        // Only run when Pattern 1 did NOT match — avoids re-matching
        // the URL inside the href we just inserted above.
        if (!changed) {
            html = html.replace(
                /(https?:\/\/[^\s<]+)/g,
                function (match, url) {
                    changed = true;
                    return '<a href="' + url + '" target="_blank" rel="noopener">' + url + '</a>';
                }
            );
        }

        if (changed) {
            $el.html(html);
            return true;
        }

        return false;
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
            if (fallbackUrl) {
                var labelText = $.trim($label.text());
                if (labelText) {
                    $label.html(
                        '<a href="' + fallbackUrl + '" target="_blank" rel="noopener">' + labelText + '</a>'
                    );
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
