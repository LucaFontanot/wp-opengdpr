/**
 * WP OpenGDPR — Form privacy consent client-side validation.
 *
 * Progressive enhancement only: the real guard is the native `required`
 * attribute on the checkbox (which works with CF7's AJAX submission) plus the
 * server-side wpcf7_spam check. This script just provides nicer UX (inline
 * error message, scroll into view) before CF7 serialises the form.
 */
( function () {
    'use strict';

    var MAIN_FIELD = 'wpog-privacy-consent';

    function fieldOf( input ) {
        return input.closest( '.wpog-consent-field' );
    }

    function showError( input ) {
        var field = fieldOf( input );
        if ( ! field ) {
            return;
        }
        field.classList.add( 'wpog-consent-error' );
        input.setAttribute( 'aria-invalid', 'true' );
        var msg = field.querySelector( '.wpog-consent-error-msg' );
        if ( msg ) {
            msg.hidden = false;
        }
    }

    function clearError( input ) {
        var field = fieldOf( input );
        if ( ! field ) {
            return;
        }
        field.classList.remove( 'wpog-consent-error' );
        input.setAttribute( 'aria-invalid', 'false' );
        var msg = field.querySelector( '.wpog-consent-error-msg' );
        if ( msg ) {
            msg.hidden = true;
        }
    }

    function bindForm( form ) {
        // Use capture so we run before CF7's own submit handler.
        form.addEventListener( 'submit', function ( e ) {
            var main = form.querySelector( 'input[name="' + MAIN_FIELD + '"]' );
            if ( main && main.required && ! main.checked ) {
                e.preventDefault();
                e.stopPropagation();
                if ( typeof e.stopImmediatePropagation === 'function' ) {
                    e.stopImmediatePropagation();
                }
                showError( main );
                main.scrollIntoView( { behavior: 'smooth', block: 'center' } );
                return false;
            }
        }, true );

        var boxes = form.querySelectorAll( 'input[name="' + MAIN_FIELD + '"]' );
        Array.prototype.forEach.call( boxes, function ( cb ) {
            cb.addEventListener( 'change', function () {
                if ( this.checked ) {
                    clearError( this );
                }
            } );
        } );
    }

    function init() {
        var forms = document.querySelectorAll( '.wpcf7-form, form.wpcf7-form, .wpforms-form, form.wpforms-form' );
        Array.prototype.forEach.call( forms, bindForm );
    }

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', init );
    } else {
        init();
    }
} )();
