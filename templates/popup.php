<?php
/**
 * Customisation popup template.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$cats    = WPOG_Settings::get( 'categories' );
$popup   = WPOG_Settings::get( 'popup' );
$general = WPOG_Settings::get( 'general' );
$current = WPOG_Consent::allowed_categories();
$show_ext = ! empty( $popup['show_extended'] );

$labels = array(
    'necessary'  => array( 'popup_necessary_label', 'popup_necessary_desc' ),
    'functional' => array( 'popup_functional_label', 'popup_functional_desc' ),
    'analytics'  => array( 'popup_analytics_label', 'popup_analytics_desc' ),
    'marketing'  => array( 'popup_marketing_label', 'popup_marketing_desc' ),
);
?>
<div id="wpog-popup-overlay" class="wpog-popup-overlay" hidden>
    <div id="wpog-popup" class="wpog-popup" role="dialog" aria-modal="true"
         aria-labelledby="wpog-popup-title">
        <div class="wpog-popup-header">
            <h2 id="wpog-popup-title"><?php echo esc_html( WPOG_Settings::string( 'popup_title' ) ); ?></h2>
            <button type="button" class="wpog-popup-close" data-wpog-action="close" aria-label="Close">&times;</button>
        </div>
        <div class="wpog-popup-body">
            <?php foreach ( $labels as $key => $strings ) :
                $label = WPOG_Settings::string( $strings[0] );
                $desc  = WPOG_Settings::string( $strings[1] );
                $disabled = ( 'necessary' === $key );
                $checked  = $disabled ? true : ! empty( $current[ $key ] );
            ?>
            <div class="wpog-category">
                <div class="wpog-category-head">
                    <strong><?php echo esc_html( $label ); ?></strong>
                    <?php if ( $disabled ) : ?>
                        <span class="wpog-always-active"><?php echo esc_html( WPOG_Settings::string( 'popup_always_active' ) ); ?></span>
                    <?php else : ?>
                        <label class="wpog-toggle">
                            <input type="checkbox" data-wpog-cat="<?php echo esc_attr( $key ); ?>" <?php checked( $checked ); ?> />
                            <span class="wpog-toggle-slider"></span>
                        </label>
                    <?php endif; ?>
                </div>
                <p class="wpog-category-desc"><?php echo esc_html( $desc ); ?></p>
                <?php if ( $show_ext && ! empty( $cats[ $key ]['cookies'] ) ) : ?>
                    <details class="wpog-cookie-details">
                        <summary>Cookies</summary>
                        <table class="wpog-cookie-table">
                            <thead><tr><th>Name</th><th>Provider</th><th>Duration</th><th>Purpose</th></tr></thead>
                            <tbody>
                            <?php foreach ( $cats[ $key ]['cookies'] as $c ) : ?>
                                <tr>
                                    <td><?php echo esc_html( $c['name'] ?? '' ); ?></td>
                                    <td><?php echo esc_html( $c['provider'] ?? '' ); ?></td>
                                    <td><?php echo esc_html( $c['duration'] ?? '' ); ?></td>
                                    <td><?php echo esc_html( $c['purpose'] ?? '' ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </details>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>

            <?php
            $cookie_policy = get_option( 'wpog_cookie_policy', '' );
            if ( ! empty( trim( $cookie_policy ) ) ) : ?>
                <div class="wpog-cookie-policy">
                    <?php echo wp_kses_post( $cookie_policy ); ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="wpog-popup-footer">
            <?php if ( ! empty( $general['privacy_url'] ) || ! empty( $general['cookie_url'] ) ) : ?>
                <p class="wpog-popup-policy-links">
                    <?php if ( ! empty( $general['privacy_url'] ) ) : ?>
                        <a href="<?php echo esc_url( $general['privacy_url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( WPOG_Settings::string( 'banner_privacy_link' ) ); ?></a>
                    <?php endif; ?>
                    <?php if ( ! empty( $general['cookie_url'] ) ) : ?>
                        <a href="<?php echo esc_url( $general['cookie_url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( WPOG_Settings::string( 'banner_cookie_link' ) ); ?></a>
                    <?php endif; ?>
                </p>
            <?php endif; ?>
            <div class="wpog-popup-footer-actions">
                <button type="button" class="wpog-btn wpog-btn-secondary" data-wpog-action="reject_all"><?php echo esc_html( WPOG_Settings::string( 'popup_reject_all' ) ); ?></button>
                <button type="button" class="wpog-btn wpog-btn-secondary" data-wpog-action="save"><?php echo esc_html( WPOG_Settings::string( 'popup_save' ) ); ?></button>
                <button type="button" class="wpog-btn wpog-btn-primary" data-wpog-action="accept_all"><?php echo esc_html( WPOG_Settings::string( 'popup_accept_all' ) ); ?></button>
            </div>
        </div>
    </div>
</div>
