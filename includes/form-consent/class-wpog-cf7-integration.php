<?php
/**
 * Contact Form 7 integration for the Form Privacy Consent module.
 *
 * Two modes:
 *   - Automatic (default): inject the consent checkbox(es) right before the
 *     submit button of every (or selected) CF7 form via wpcf7_form_elements.
 *   - Manual: the webmaster places [wpog_privacy_consent] / [wpog_marketing_consent]
 *     form-tags inside the CF7 template.
 *
 * Both modes render identical markup with the same field names, so server-side
 * validation and logging are mode-agnostic.
 *
 * Field names (read from $_POST — auto-injected raw inputs are NOT part of CF7's
 * get_posted_data(), which only contains registered form-tags):
 *   wpog-privacy-consent   — mandatory consent checkbox
 *   wpog-marketing-consent — optional marketing checkbox
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPOG_CF7_Integration {

    const FIELD_MAIN      = 'wpog-privacy-consent';
    const FIELD_MARKETING = 'wpog-marketing-consent';

    public static function init() {
        $s = WPOG_Settings::get( 'form_consent' );
        if ( empty( $s['cf7_enabled'] ) ) {
            return;
        }

        // Automatic injection.
        add_filter( 'wpcf7_form_elements', array( __CLASS__, 'inject_consent_checkbox' ) );

        // Manual form-tags.
        if ( function_exists( 'wpcf7_add_form_tag' ) ) {
            wpcf7_add_form_tag(
                array( 'wpog_privacy_consent', 'wpog_privacy_consent*' ),
                array( __CLASS__, 'tag_main' ),
                array( 'name-attr' => false )
            );
            wpcf7_add_form_tag(
                array( 'wpog_marketing_consent', 'wpog_marketing_consent*' ),
                array( __CLASS__, 'tag_marketing' ),
                array( 'name-attr' => false )
            );
        }

        // Server-side guard: block submission when the mandatory consent is missing.
        add_filter( 'wpcf7_spam', array( __CLASS__, 'validate_consent_on_submit' ), 10, 2 );

        // Record the consent after validation, before the mail is sent.
        add_action( 'wpcf7_before_send_mail', array( __CLASS__, 'save_consent_log' ), 10, 3 );
    }

    /* ---------- rendering ---------- */

    /**
     * Inject the checkbox markup before the CF7 submit button.
     */
    public static function inject_consent_checkbox( $form_elements ) {
        $s = WPOG_Settings::get( 'form_consent' );

        if ( empty( $s['enabled'] ) || empty( $s['cf7_auto_inject'] ) ) {
            return $form_elements;
        }
        // 'manual' position disables automatic injection.
        if ( 'manual' === ( $s['cf7_position'] ?? 'before_submit' ) ) {
            return $form_elements;
        }

        $current = function_exists( 'wpcf7_get_current_contact_form' )
            ? wpcf7_get_current_contact_form()
            : ( class_exists( 'WPCF7_ContactForm' ) ? WPCF7_ContactForm::get_current() : null );

        if ( $current && ! self::is_form_enabled( $current->id(), $s ) ) {
            return $form_elements;
        }

        $checkbox_html = self::render_consent_checkboxes( $s );
        if ( '' === $checkbox_html ) {
            return $form_elements;
        }

        if ( 'after_fields' === ( $s['cf7_position'] ?? 'before_submit' ) ) {
            // Append at the very end of the form body.
            return $form_elements . $checkbox_html;
        }

        // Default: before_submit — insert just before the first submit button.
        $injected = preg_replace(
            '/(<input[^>]*type=["\']submit["\'][^>]*>|<button[^>]*type=["\']submit["\'][^>]*>|<[^>]*class=["\'][^"\']*wpcf7-submit[^"\']*["\'][^>]*>)/i',
            $checkbox_html . '$1',
            $form_elements,
            1,
            $count
        );

        if ( $count > 0 && null !== $injected ) {
            return $injected;
        }

        // No submit button matched — fall back to appending.
        return $form_elements . $checkbox_html;
    }

    /**
     * Generate the consent checkbox HTML. Shared by automatic and manual modes.
     */
    public static function render_consent_checkboxes( $s, $which = 'both' ) {
        $allowed = array( 'a' => array( 'href' => array(), 'target' => array(), 'rel' => array() ) );
        $html    = '<div class="wpog-form-consent-wrapper">';
        $any     = false;

        if ( 'both' === $which || 'main' === $which ) {
            if ( ! empty( $s['checkbox_main_enabled'] ) ) {
                $label    = WPOG_Form_Consent::main_label();
                $required = ! empty( $s['checkbox_main_required'] ) ? ' required aria-required="true"' : '';
                $error    = isset( $s['checkbox_main_error'] ) ? $s['checkbox_main_error'] : '';

                $html .= sprintf(
                    '<p class="wpog-consent-field wpog-consent-main">
                        <label>
                            <input type="checkbox" name="%1$s" value="1"%2$s>
                            <span class="wpog-consent-label">%3$s</span>
                        </label>
                        <span class="wpog-consent-error-msg" aria-live="polite" hidden>%4$s</span>
                    </p>',
                    esc_attr( self::FIELD_MAIN ),
                    $required,
                    wp_kses( $label, $allowed ),
                    esc_html( $error )
                );
                $any = true;
            }
        }

        if ( 'both' === $which || 'marketing' === $which ) {
            if ( ! empty( $s['checkbox_marketing_enabled'] ) ) {
                $label    = WPOG_Form_Consent::marketing_label();
                $required = ! empty( $s['checkbox_marketing_required'] ) ? ' required aria-required="true"' : '';

                $html .= sprintf(
                    '<p class="wpog-consent-field wpog-consent-marketing">
                        <label>
                            <input type="checkbox" name="%1$s" value="1"%2$s>
                            <span class="wpog-consent-label">%3$s</span>
                        </label>
                    </p>',
                    esc_attr( self::FIELD_MARKETING ),
                    $required,
                    wp_kses( $label, $allowed )
                );
                $any = true;
            }
        }

        $html .= '</div>';

        return $any ? $html : '';
    }

    /* ---------- manual form-tags ---------- */

    public static function tag_main( $tag ) {
        $s = WPOG_Settings::get( 'form_consent' );
        return self::render_consent_checkboxes( $s, 'main' );
    }

    public static function tag_marketing( $tag ) {
        $s = WPOG_Settings::get( 'form_consent' );
        return self::render_consent_checkboxes( $s, 'marketing' );
    }

    /* ---------- validation ---------- */

    /**
     * Mark the submission as spam (= block) when the mandatory consent checkbox
     * was not ticked. Reads from $_POST: auto-injected inputs are not part of
     * CF7's posted_data.
     */
    public static function validate_consent_on_submit( $spam, $submission ) {
        $s = WPOG_Settings::get( 'form_consent' );

        if ( empty( $s['enabled'] ) || empty( $s['block_submit_without_consent'] ) ) {
            return $spam;
        }
        if ( empty( $s['checkbox_main_enabled'] ) || empty( $s['checkbox_main_required'] ) ) {
            return $spam;
        }

        $given = isset( $_POST[ self::FIELD_MAIN ] ) && '1' === (string) wp_unslash( $_POST[ self::FIELD_MAIN ] );
        if ( ! $given ) {
            return true; // block submission
        }

        return $spam;
    }

    /* ---------- logging ---------- */

    /**
     * Persist the consent proof after a valid submission.
     *
     * @param WPCF7_ContactForm $contact_form
     * @param bool              $abort
     * @param WPCF7_Submission  $submission
     */
    public static function save_consent_log( $contact_form, $abort = false, $submission = null ) {
        if ( $abort ) {
            return;
        }

        $s = WPOG_Settings::get( 'form_consent' );
        if ( empty( $s['enabled'] ) || empty( $s['log_enabled'] ) ) {
            return;
        }

        $page_url = '';
        if ( $submission && method_exists( $submission, 'get_meta' ) ) {
            $page_url = (string) $submission->get_meta( 'url' );
        }

        $given     = isset( $_POST[ self::FIELD_MAIN ] ) && '1' === (string) wp_unslash( $_POST[ self::FIELD_MAIN ] );
        $marketing = isset( $_POST[ self::FIELD_MARKETING ] ) && '1' === (string) wp_unslash( $_POST[ self::FIELD_MARKETING ] );

        WPOG_Form_Consent_Logger::log( array(
            'form_id'           => method_exists( $contact_form, 'id' ) ? $contact_form->id() : '',
            'form_type'         => 'cf7',
            'form_title'        => method_exists( $contact_form, 'title' ) ? $contact_form->title() : '',
            'page_url'          => $page_url,
            'consent_given'     => $given ? 1 : 0,
            'marketing_consent' => $marketing ? 1 : 0,
            // Record the EXACT text shown to the user (with the privacy link resolved).
            'consent_text'      => WPOG_Form_Consent::main_label(),
            'privacy_version'   => (string) $s['privacy_policy_version'],
        ) );
    }

    /* ---------- helpers ---------- */

    public static function is_form_enabled( $form_id, $s ) {
        $ids = isset( $s['cf7_form_ids'] ) ? (array) $s['cf7_form_ids'] : array();
        if ( empty( $ids ) ) {
            return true; // empty = all forms
        }
        return in_array( (int) $form_id, array_map( 'intval', $ids ), true );
    }
}
