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
        add_action( 'wp_head', array( __CLASS__, 'render_head_top' ), 1 );
        add_action( 'wp_head', array( __CLASS__, 'render_head' ), 99 );
        add_action( 'wp_body_open', array( __CLASS__, 'render_body_top' ), 1 );
        add_action( 'wp_footer', array( __CLASS__, 'render_body_bottom' ), 1 );
    }

    /**
     * Inline boot snippet — runs before any other JS, sets up window.wpogConsent stub
     * so third-party code that checks for it (in admin-defined inline scripts) can adapt.
     */
    public static function render_head_top() {
        if ( ! WPOG_Settings::get( 'general', 'enabled' ) ) {
            return;
        }
        $allowed = WPOG_Consent::allowed_categories();
        echo "<script>window.wpogConsent=" . wp_json_encode( $allowed ) . ";</script>\n";
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
        $allowed = WPOG_Consent::allowed_categories();
        foreach ( $scripts as $script ) {
            if ( empty( $script['active'] ) ) {
                continue;
            }
            if ( ( $script['position'] ?? 'head' ) !== $position ) {
                continue;
            }
            $category = $script['category'] ?? 'marketing';
            $type     = $script['type'] ?? 'inline';
            $is_allowed = ! empty( $allowed[ $category ] ) || 'necessary' === $category;

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
