<?php if ( ! defined( 'ABSPATH' ) ) { exit; }
WPOG_Admin::notice();
$b = WPOG_Settings::get( 'banner' );
?>
<h1><?php esc_html_e( 'Banner Appearance', 'wp-opengdpr' ); ?></h1>
<?php WPOG_Admin::form_open( 'banner' ); ?>
<table class="form-table">
    <tr><th><?php esc_html_e( 'Position', 'wp-opengdpr' ); ?></th>
        <td><select name="wpog[position]">
            <option value="bottom" <?php selected( $b['position'], 'bottom' ); ?>><?php esc_html_e( 'Bottom', 'wp-opengdpr' ); ?></option>
            <option value="top" <?php selected( $b['position'], 'top' ); ?>><?php esc_html_e( 'Top', 'wp-opengdpr' ); ?></option>
        </select></td></tr>
    <tr><th><?php esc_html_e( 'Animation', 'wp-opengdpr' ); ?></th>
        <td><select name="wpog[animation]">
            <?php foreach ( array( 'slide', 'fade', 'none' ) as $a ) : ?>
                <option value="<?php echo esc_attr( $a ); ?>" <?php selected( $b['animation'], $a ); ?>><?php echo esc_html( $a ); ?></option>
            <?php endforeach; ?>
        </select></td></tr>
    <?php
    $colors = array(
        'bg_color'       => __( 'Background', 'wp-opengdpr' ),
        'text_color'     => __( 'Text', 'wp-opengdpr' ),
        'primary_bg'     => __( 'Primary button background', 'wp-opengdpr' ),
        'primary_text'   => __( 'Primary button text', 'wp-opengdpr' ),
        'secondary_bg'   => __( 'Secondary button background', 'wp-opengdpr' ),
        'secondary_text' => __( 'Secondary button text', 'wp-opengdpr' ),
    );
    foreach ( $colors as $key => $label ) : ?>
    <tr><th><?php echo esc_html( $label ); ?></th>
        <td><input type="text" class="wpog-color" name="wpog[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $b[ $key ] ); ?>" /></td></tr>
    <?php endforeach; ?>
    <tr><th><?php esc_html_e( 'Button border radius (px)', 'wp-opengdpr' ); ?></th>
        <td><input type="number" min="0" name="wpog[border_radius]" value="<?php echo esc_attr( $b['border_radius'] ); ?>" /></td></tr>
    <tr><th><?php esc_html_e( 'Font size (px)', 'wp-opengdpr' ); ?></th>
        <td><input type="number" min="8" name="wpog[font_size]" value="<?php echo esc_attr( $b['font_size'] ); ?>" /></td></tr>
    <tr><th><?php esc_html_e( 'Overlay background', 'wp-opengdpr' ); ?></th>
        <td><label><input type="checkbox" name="wpog[overlay]" value="1" <?php checked( $b['overlay'] ); ?> /></label></td></tr>
    <tr><th><?php esc_html_e( 'Logo URL', 'wp-opengdpr' ); ?></th>
        <td><input type="url" class="regular-text wpog-media" name="wpog[logo]" value="<?php echo esc_attr( $b['logo'] ); ?>" />
        <button type="button" class="button wpog-media-pick"><?php esc_html_e( 'Choose image', 'wp-opengdpr' ); ?></button></td></tr>
</table>
<h2><?php esc_html_e( 'Live preview', 'wp-opengdpr' ); ?></h2>
<div class="wpog-preview" style="background:<?php echo esc_attr( $b['bg_color'] ); ?>;color:<?php echo esc_attr( $b['text_color'] ); ?>;padding:16px;">
    <p><?php echo esc_html( WPOG_Settings::string( 'banner_message' ) ); ?></p>
    <button type="button" style="background:<?php echo esc_attr( $b['secondary_bg'] ); ?>;color:<?php echo esc_attr( $b['secondary_text'] ); ?>;border:0;padding:10px 16px;border-radius:<?php echo esc_attr( $b['border_radius'] ); ?>px;"><?php echo esc_html( WPOG_Settings::string( 'banner_reject_all' ) ); ?></button>
    <button type="button" style="background:<?php echo esc_attr( $b['secondary_bg'] ); ?>;color:<?php echo esc_attr( $b['secondary_text'] ); ?>;border:0;padding:10px 16px;border-radius:<?php echo esc_attr( $b['border_radius'] ); ?>px;"><?php echo esc_html( WPOG_Settings::string( 'banner_customize' ) ); ?></button>
    <button type="button" style="background:<?php echo esc_attr( $b['primary_bg'] ); ?>;color:<?php echo esc_attr( $b['primary_text'] ); ?>;border:0;padding:10px 16px;border-radius:<?php echo esc_attr( $b['border_radius'] ); ?>px;"><?php echo esc_html( WPOG_Settings::string( 'banner_accept_all' ) ); ?></button>
</div>
<?php WPOG_Admin::form_close(); ?>
