<?php
/**
 * Cookie banner template.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$general = WPOG_Settings::get( 'general' );
$banner  = WPOG_Settings::get( 'banner' );
$show    = WPOG_Consent::should_show_banner();
$pos     = $banner['position'] === 'top' ? 'wpog-pos-top' : 'wpog-pos-bottom';
$anim    = 'wpog-anim-' . sanitize_html_class( $banner['animation'] );
?>
<div id="wpog-banner" class="wpog-banner <?php echo esc_attr( $pos . ' ' . $anim ); ?>"
     role="dialog" aria-live="polite" aria-label="<?php echo esc_attr( WPOG_Settings::string( 'popup_title' ) ); ?>"
     <?php echo $show ? '' : 'hidden'; ?>>
    <div class="wpog-banner-inner">
        <?php if ( ! empty( $banner['logo'] ) ) : ?>
            <img class="wpog-banner-logo" src="<?php echo esc_url( $banner['logo'] ); ?>" alt="" />
        <?php endif; ?>
        <div class="wpog-banner-text">
            <p><?php echo wp_kses_post( WPOG_Settings::string( 'banner_message' ) ); ?></p>
            <p class="wpog-banner-links">
                <?php if ( ! empty( $general['privacy_url'] ) ) : ?>
                    <a href="<?php echo esc_url( $general['privacy_url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( WPOG_Settings::string( 'banner_privacy_link' ) ); ?></a>
                <?php endif; ?>
                <?php if ( ! empty( $general['cookie_url'] ) ) : ?>
                    <a href="<?php echo esc_url( $general['cookie_url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( WPOG_Settings::string( 'banner_cookie_link' ) ); ?></a>
                <?php endif; ?>
            </p>
        </div>
        <div class="wpog-banner-actions">
            <button type="button" class="wpog-btn wpog-btn-secondary" data-wpog-action="reject_all"><?php echo esc_html( WPOG_Settings::string( 'banner_reject_all' ) ); ?></button>
            <button type="button" class="wpog-btn wpog-btn-secondary" data-wpog-action="customize"><?php echo esc_html( WPOG_Settings::string( 'banner_customize' ) ); ?></button>
            <button type="button" class="wpog-btn wpog-btn-primary" data-wpog-action="accept_all"><?php echo esc_html( WPOG_Settings::string( 'banner_accept_all' ) ); ?></button>
        </div>
    </div>
</div>
