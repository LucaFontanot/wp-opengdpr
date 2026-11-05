<?php
/**
 * Script blocker — outputs admin-defined third-party scripts in a blocked form
 * (type="text/plain" + data-wpog-category) so they can be activated by JS after consent.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPOG_Script_Blocker {

    public static function init() {
        // Priority -1: must run BEFORE the domain-blocker bootstrap (priority 0)
        // so window.wpogConsent is defined when wpog-blocker.js evaluates.
        add_action( 'wp_head', array( __CLASS__, 'render_head_top' ), -1 );
        add_action( 'wp_head', array( __CLASS__, 'render_head' ), 99 );
        add_action( 'wp_body_open', array( __CLASS__, 'render_body_top' ), 1 );
        add_action( 'wp_footer', array( __CLASS__, 'render_body_bottom' ), 1 );
    }

    /**
     * Inline boot snippet — runs before any other JS and sets window.wpogConsent.
     *
     * The consent state is read from the cookie IN THE BROWSER, never on the
     * server: the HTML must be identical for every visitor so that full-page
     * caches (Varnish, WP Rocket, Cloudflare APO, LiteSpeed…) can never serve
     * a page rendered for a visitor who accepted everything to a visitor who
     * did not consent (which would execute marketing/analytics scripts
     * without consent).
     */
    public static function render_head_top() {
        if ( ! WPOG_Settings::get( 'general', 'enabled' ) ) {
            return;
        }
        $cfg = wp_json_encode( array(
            'v' => (string) WPOG_Settings::get( 'general', 'policy_version' ),
            'd' => (int) WPOG_Settings::get( 'general', 'consent_duration', 180 ),
        ) );
        echo '<script id="wpog-consent-boot">(function(C){var c={necessary:true,functional:false,analytics:false,marketing:false};'
            . 'try{var m=document.cookie.match(/(?:^|;\s*)wpog_consent=([^;]+)/);'
            . 'if(m){var d=JSON.parse(decodeURIComponent(m[1]));var ok=d&&String(d.version)===C.v&&d.categories&&typeof d.categories==="object";'
            . 'if(ok&&d.date){var t=Date.parse(d.date);if(!isNaN(t)&&Date.now()-t>C.d*864e5){ok=false;}}'
            . 'if(ok){c.functional=!!d.categories.functional;c.analytics=!!d.categories.analytics;c.marketing=!!d.categories.marketing;}}'
            . '}catch(e){}window.wpogConsent=c;})(' . $cfg . ');</script>' . "\n";
    }

    public static function render_head() {
        self::render_for_position( 'head' );
    }

    public static function render_body_top() {
        self::render_for_position( 'body-top' );
    }

    public static function render_body_bottom() {
        self::render_for_position( 'body-bottom' );
    }

    protected static function render_for_position( $position ) {
        $scripts = WPOG_Settings::get( 'scripts' );
        if ( empty( $scripts ) || ! is_array( $scripts ) ) {
            return;
        }
        foreach ( $scripts as $script ) {
            if ( empty( $script['active'] ) ) {
                continue;
            }
            if ( ( $script['position'] ?? 'head' ) !== $position ) {
                continue;
            }
            $category = $script['category'] ?? 'marketing';
            $type     = $script['type'] ?? 'inline';
            // Cache-safe: only "necessary" snippets are emitted active. Every other
            // category is ALWAYS emitted blocked and activated client-side by
            // wpog-consent.js once the visitor's cookie is read in the browser.
            $is_allowed = 'necessary' === $category;

            if ( 'iframe' === $type ) {
                $url = esc_url( $script['content'] ?? '' );
                if ( ! $url ) {
                    continue;
                }
                if ( $is_allowed ) {
                    printf(
                        '<iframe src="%1$s" data-wpog-category="%2$s" style="border:0;width:100%%;"></iframe>',
                        esc_attr( $url ),
                        esc_attr( $category )
                    );
                } else {
                    printf(
                        '<iframe data-src="%1$s" data-wpog-category="%2$s" style="border:0;width:100%%;"></iframe>',
                        esc_attr( $url ),
                        esc_attr( $category )
                    );
                }
                continue;
            }

            if ( 'src' === $type ) {
                $url = esc_url( $script['content'] ?? '' );
                if ( ! $url ) {
                    continue;
                }
                if ( $is_allowed ) {
                    printf(
                        '<script src="%1$s" data-wpog-category="%2$s"></script>' . "\n",
                        esc_attr( $url ),
                        esc_attr( $category )
                    );
                } else {
                    printf(
                        '<script type="text/plain" data-wpog-src="%1$s" data-wpog-category="%2$s"></script>' . "\n",
                        esc_attr( $url ),
                        esc_attr( $category )
                    );
                }
                continue;
            }

            // Inline.
            $code = (string) ( $script['content'] ?? '' );
            // Never let stored content close the wrapper tag.
            $code = preg_replace( '#</(script)#i', '<\\/$1', $code );
            if ( '' === trim( $code ) ) {
                continue;
            }
            $type_attr = $is_allowed ? 'text/javascript' : 'text/plain';
            printf(
                '<script type="%1$s" data-wpog-category="%2$s">%3$s</script>' . "\n",
                esc_attr( $type_attr ),
                esc_attr( $category ),
                $code // Admin-only content, output as-is (cap-checked at save time).
            );
        }
    }
}
