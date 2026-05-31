/**
 * Terms & Conditions — client-side checkout validation.
 *
 * Adds immediate visual feedback on the WooCommerce classic checkout. The
 * authoritative validation stays server-side (wc_add_notice in PHP); this only
 * highlights the missing mandatory checkboxes and blocks the AJAX submit early.
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
})(jQuery);
