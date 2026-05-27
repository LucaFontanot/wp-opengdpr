<?php if ( ! defined( 'ABSPATH' ) ) { exit; }
WPOG_Admin::notice();
$p = WPOG_Settings::get( 'popup' );
?>
<h1><?php esc_html_e( 'Popup Appearance', 'wp-opengdpr' ); ?></h1>
<?php WPOG_Admin::form_open( 'popup' ); ?>
<table class="form-table">
    <tr><th><?php esc_html_e( 'Width (px)', 'wp-opengdpr' ); ?></th>
        <td><input type="number" min="280" name="wpog[width]" value="<?php echo esc_attr( $p['width'] ); ?>" /></td></tr>
    <tr><th><?php esc_html_e( 'Overlay color', 'wp-opengdpr' ); ?></th>
        <td><input type="text" class="regular-text" name="wpog[overlay_color]" value="<?php echo esc_attr( $p['overlay_color'] ); ?>" />
        <p class="description"><?php esc_html_e( 'CSS color or rgba()', 'wp-opengdpr' ); ?></p></td></tr>
    <?php
    $colors = array(
        'toggle_on'      => __( 'Toggle ON', 'wp-opengdpr' ),
        'toggle_off'     => __( 'Toggle OFF', 'wp-opengdpr' ),
        'bg_color'       => __( 'Background', 'wp-opengdpr' ),
        'text_color'     => __( 'Text', 'wp-opengdpr' ),
        'primary_bg'     => __( 'Primary button background', 'wp-opengdpr' ),
        'primary_text'   => __( 'Primary button text', 'wp-opengdpr' ),
        'secondary_bg'   => __( 'Secondary button background', 'wp-opengdpr' ),
        'secondary_text' => __( 'Secondary button text', 'wp-opengdpr' ),
    );
    foreach ( $colors as $key => $label ) : ?>
    <tr><th><?php echo esc_html( $label ); ?></th>
        <td><input type="text" class="wpog-color" name="wpog[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $p[ $key ] ); ?>" /></td></tr>
    <?php endforeach; ?>
    <tr><th><?php esc_html_e( 'Border radius (px)', 'wp-opengdpr' ); ?></th>
        <td><input type="number" min="0" name="wpog[border_radius]" value="<?php echo esc_attr( $p['border_radius'] ); ?>" /></td></tr>
    <tr><th><?php esc_html_e( 'Font size (px)', 'wp-opengdpr' ); ?></th>
        <td><input type="number" min="8" name="wpog[font_size]" value="<?php echo esc_attr( $p['font_size'] ); ?>" /></td></tr>
    <tr><th><?php esc_html_e( 'Show extended cookie descriptions', 'wp-opengdpr' ); ?></th>
        <td><label><input type="checkbox" name="wpog[show_extended]" value="1" <?php checked( $p['show_extended'] ); ?> /></label></td></tr>
</table>
<?php WPOG_Admin::form_close(); ?>
