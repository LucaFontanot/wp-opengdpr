<?php
/**
 * Terms & Conditions — module bootstrap (e-commerce).
 *
 * Independent from the cookie-consent and form-consent modules. Disabling one
 * does not disable the others.
 *
 * Responsibilities:
 *   - load the WooCommerce integration when WooCommerce is active;
 *   - enqueue the lightweight checkout validation assets on the WC checkout page;
 *   - expose helpers shared by the integration and the admin views (resolved
 *     checkbox labels, current Terms text + hash, version-history storage).
 *
 * The actual checkout injection / validation / logging lives in
 * WPOG_WooCommerce_Integration.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPOG_Terms_Consent {

    const VERSIONS_OPTION = 'wpog_terms_versions';

    public static function init() {
        if ( ! WPOG_Settings::get( 'terms_consent', 'enabled' ) ) {
            return;
        }

        // WooCommerce integration — only when WooCommerce is active.
        if ( class_exists( 'WooCommerce' ) ) {
            WPOG_WooCommerce_Integration::init();
        }
    }

    /**
     * Enqueue the checkout assets. Called on demand from the WooCommerce
     * integration so the files only load where the checkboxes are rendered.
     */
    public static function enqueue_assets() {
        if ( wp_style_is( 'wpog-terms-consent', 'enqueued' ) ) {
            return;
        }
        wp_enqueue_style(
            'wpog-terms-consent',
            WPOG_PLUGIN_URL . 'public/assets/wpog-terms-consent.css',
            array(),
            WPOG_VERSION
        );
        wp_enqueue_script(
            'wpog-terms-consent',
            WPOG_PLUGIN_URL . 'public/assets/wpog-terms-consent.js',
            array( 'jquery' ),
            WPOG_VERSION,
            true
        );
    }

    /* ---------- URLs ---------- */

    public static function terms_url() {
        $id = (int) WPOG_Settings::get( 'terms_consent', 'terms_page_id', 0 );
        return $id ? (string) get_permalink( $id ) : '';
    }

    public static function privacy_url() {
        $id = (int) WPOG_Settings::get( 'terms_consent', 'privacy_page_id', 0 );
        if ( $id ) {
            return (string) get_permalink( $id );
        }
        // Fall back to the global privacy URL used by the cookie module.
        return (string) WPOG_Settings::get( 'general', 'privacy_url', '' );
    }

    /* ---------- resolved labels (exact text recorded as proof) ---------- */

    public static function terms_label() {
        $s = WPOG_Settings::get( 'terms_consent' );
        return str_replace(
            array( '{terms_url}', '{privacy_url}' ),
            array( esc_url( self::terms_url() ), esc_url( self::privacy_url() ) ),
            (string) $s['terms_checkbox_label']
        );
    }

    public static function privacy_label() {
        $s = WPOG_Settings::get( 'terms_consent' );
        return str_replace(
            '{privacy_url}',
            esc_url( self::privacy_url() ),
            (string) $s['privacy_checkbox_label']
        );
    }

    public static function marketing_label() {
        $s = WPOG_Settings::get( 'terms_consent' );
        return (string) $s['marketing_checkbox_label'];
    }

    public static function age_label() {
        $s   = WPOG_Settings::get( 'terms_consent' );
        $age = absint( $s['minimum_age'] ?? 18 );
        return str_replace( '{min_age}', (string) $age, (string) $s['age_checkbox_label'] );
    }

    /* ---------- Terms document text + hash ---------- */

    /**
     * Raw post_content of the selected Terms page.
     */
    public static function terms_content() {
        $id = (int) WPOG_Settings::get( 'terms_consent', 'terms_page_id', 0 );
        if ( ! $id ) {
            return '';
        }
        $page = get_post( $id );
        return $page ? (string) $page->post_content : '';
    }

    /**
     * SHA-256 fingerprint of the current Terms page content. Empty string when
     * no page is selected or the page has no content.
     */
    public static function terms_hash() {
        $content = self::terms_content();
        return '' !== $content ? hash( 'sha256', $content ) : '';
    }

    /* ---------- version history ---------- */

    /**
     * @return array List of { version, effective_date, reason, hash, status }.
     */
    public static function get_versions() {
        $v = get_option( self::VERSIONS_OPTION, array() );
        return is_array( $v ) ? $v : array();
    }

    public static function save_versions( array $versions ) {
        update_option( self::VERSIONS_OPTION, array_values( $versions ) );
    }

    /**
     * Capture the current Terms page text/hash as a new version-history entry
     * and mark it active (older entries become historic).
     *
     * @param string $version
     * @param string $effective_date  Y-m-d
     * @param string $reason
     */
    public static function register_version( $version, $effective_date, $reason ) {
        $version = sanitize_text_field( $version );
        if ( '' === $version ) {
            return;
        }
        $versions = self::get_versions();
        foreach ( $versions as &$row ) {
            $row['status'] = 'historic';
        }
        unset( $row );

        $versions[] = array(
            'version'        => $version,
            'effective_date' => sanitize_text_field( $effective_date ),
            'reason'         => sanitize_textarea_field( $reason ),
            'hash'           => self::terms_hash(),
            'status'         => 'active',
        );
        self::save_versions( $versions );
    }
}
