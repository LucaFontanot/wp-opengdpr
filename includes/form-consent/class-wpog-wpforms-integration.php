<?php
/**
 * WPForms integration for the Form Privacy Consent module.
 *
 * Mirrors WPOG_CF7_Integration but uses the WPForms hook surface:
 *   - wpforms_display_submit_before : action, echo the consent checkbox(es)
 *                                     right before the submit button.
 *   - wpforms_process              : action, block the submission (header error)
 *                                     when the mandatory consent is missing.
 *   - wpforms_process_complete     : action, persist the consent proof once the
 *                                     entry has been stored and notifications sent.
 *
 * The rendered markup, CSS classes and field names are identical to the CF7
 * integration (rendered via WPOG_CF7_Integration::render_consent_checkboxes())
 * so the shared stylesheet and client-side validation work unchanged.
 *
 * Field names (read from $_POST — custom inputs are not part of WPForms $fields):
 *   wpog-privacy-consent   — mandatory consent checkbox
 *   wpog-marketing-consent — optional marketing checkbox
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPOG_WPForms_Integration {

    const FIELD_MAIN      = WPOG_CF7_Integration::FIELD_MAIN;
    const FIELD_MARKETING = WPOG_CF7_Integration::FIELD_MARKETING;

    public static function init() {
        $s = WPOG_Settings::get( 'form_consent' );
        if ( empty( $s['wpforms_enabled'] ) ) {
            return;
        }

        // Inject the checkbox(es) before the submit button.
        add_action( 'wpforms_display_submit_before', array( __CLASS__, 'inject_consent_checkbox' ), 10, 1 );

        // Server-side guard: block the submission when the mandatory consent is missing.
        add_action( 'wpforms_process', array( __CLASS__, 'validate_consent' ), 10, 3 );

        // Record the consent after a successful submission.
        add_action( 'wpforms_process_complete', array( __CLASS__, 'save_consent_log' ), 10, 4 );
    }

    /* ---------- rendering ---------- */

    /**
     * Echo the consent checkbox markup before the WPForms submit button.
     *
     * @param array $form_data Full form configuration.
     */
    public static function inject_consent_checkbox( $form_data ) {
        $s = WPOG_Settings::get( 'form_consent' );

        if ( empty( $s['enabled'] ) || empty( $s['wpforms_enabled'] ) ) {
            return;
        }
        if ( ! self::is_form_enabled( $form_data['id'] ?? 0, $s ) ) {
            return;
        }

        // Shared renderer — identical markup/CSS to the CF7 integration.
        $html = WPOG_CF7_Integration::render_consent_checkboxes( $s );
        if ( '' === $html ) {
            return;
        }

        // Make sure the shared CSS/JS are present on this page (WPForms does not
        // fire the wpcf7_enqueue_scripts hook used by the CF7 path).
        WPOG_Form_Consent::enqueue_assets();

        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup already escaped in render_consent_checkboxes().
    }

    /* ---------- validation ---------- */

    /**
     * Block the submission when the mandatory consent checkbox is missing.
     *
     * @param array $fields    Sanitised field values.
     * @param array $entry     Raw $_POST entry.
     * @param array $form_data Form configuration.
     */
    public static function validate_consent( $fields, $entry, $form_data ) {
        $s = WPOG_Settings::get( 'form_consent' );

        if ( empty( $s['enabled'] ) || empty( $s['wpforms_enabled'] ) ) {
            return;
        }
        if ( empty( $s['block_submit_without_consent'] ) ) {
            return;
        }
        if ( empty( $s['checkbox_main_enabled'] ) || empty( $s['checkbox_main_required'] ) ) {
            return;
        }
        if ( ! self::is_form_enabled( $form_data['id'] ?? 0, $s ) ) {
            return;
        }

        $given = isset( $_POST[ self::FIELD_MAIN ] ) && '1' === (string) wp_unslash( $_POST[ self::FIELD_MAIN ] );
        if ( $given ) {
            return;
        }

        if ( ! function_exists( 'wpforms' ) || ! isset( wpforms()->process ) ) {
            return;
        }

        $message = isset( $s['checkbox_main_error'] ) && '' !== $s['checkbox_main_error']
            ? $s['checkbox_main_error']
            : __( 'You must accept the privacy policy to send your message.', 'wp-opengdpr' );

        $form_id = $form_data['id'] ?? 0;
        wpforms()->process->errors[ $form_id ]['header'] = esc_html( $message );
    }

    /* ---------- logging ---------- */

    /**
     * Persist the consent proof after a completed submission.
     *
     * @param array $fields    Final field values.
     * @param array $entry     Raw $_POST entry.
     * @param array $form_data Form configuration.
     * @param int   $entry_id  Stored entry id (0 when entries are disabled).
     */
    public static function save_consent_log( $fields, $entry, $form_data, $entry_id ) {
        $s = WPOG_Settings::get( 'form_consent' );

        if ( empty( $s['enabled'] ) || empty( $s['wpforms_enabled'] ) || empty( $s['log_enabled'] ) ) {
            return;
        }
        if ( ! self::is_form_enabled( $form_data['id'] ?? 0, $s ) ) {
            return;
        }

        $given     = isset( $_POST[ self::FIELD_MAIN ] ) && '1' === (string) wp_unslash( $_POST[ self::FIELD_MAIN ] );
        $marketing = isset( $_POST[ self::FIELD_MARKETING ] ) && '1' === (string) wp_unslash( $_POST[ self::FIELD_MARKETING ] );

        $page_url = '';
        if ( isset( $_POST['wpforms']['page_url'] ) ) {
            $page_url = esc_url_raw( wp_unslash( $_POST['wpforms']['page_url'] ) );
        }

        WPOG_Form_Consent_Logger::log( array(
            'form_id'           => $form_data['id'] ?? '',
            'form_type'         => 'wpforms',
            'form_title'        => $form_data['settings']['form_title'] ?? '',
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
        $ids = isset( $s['wpforms_form_ids'] ) ? (array) $s['wpforms_form_ids'] : array();
        if ( empty( $ids ) ) {
            return true; // empty = all forms
        }
        return in_array( (int) $form_id, array_map( 'intval', $ids ), true );
    }
}
