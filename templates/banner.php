<?php
/**
 * Cookie banner template.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$general = WPOG_Settings::get( 'general' );
$banner  = WPOG_Settings::get( 'banner' );
// Always rendered hidden: wpog-consent.js reads the consent cookie in the
// browser and shows the banner when needed. Server-side cookie-dependent
// markup would be poisoned by full-page caches.
$show    = false;
$pos_raw = $banner['position'];
$is_box  = ! in_array( $pos_raw, array( 'top', 'bottom' ), true );
$pos_cls = 'wpog-pos-' . sanitize_html_class( $pos_raw );

// Box positions always use fade animation (slide conflicts with transform centering).
if ( $is_box ) {
    $anim = $banner['animation'] === 'none' ? '' : 'wpog-anim-fade';
} else {
    $anim = 'wpog-anim-' . sanitize_html_class( $banner['animation'] );
}

$box_cls = $is_box ? ' wpog-banner--box' : '';
$overlay = ! empty( $banner['overlay'] );
?>
<?php if ( $overlay ) : ?>
<div id="wpog-banner-overlay" class="wpog-banner-overlay" <?php echo $show ? '' : 'hidden'; ?>></div>
<?php endif; ?>
<div id="wpog-banner" class="wpog-banner <?php echo esc_attr( $pos_cls . $box_cls . ' ' . $anim ); ?>"
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
