<?php if ( ! defined( 'ABSPATH' ) ) { exit; }
WPOG_Admin::notice();
$s         = WPOG_Settings::get( 'terms_consent' );
$wc_active = class_exists( 'WooCommerce' );
$position  = in_array( $s['wc_checkout_position'] ?? 'before_submit', array( 'before_submit', 'after_terms' ), true ) ? $s['wc_checkout_position'] : 'before_submit';
?>
<h1><?php esc_html_e( 'Terms & Conditions — WooCommerce', 'wp-opengdpr' ); ?></h1>

<?php if ( ! $wc_active ) : ?>
<div class="notice notice-warning inline" style="margin:12px 0;"><p>
    <?php esc_html_e( 'WooCommerce is not active. Install and activate WooCommerce to enable this integration.', 'wp-opengdpr' ); ?>
</p></div>
<?php else : ?>

<?php WPOG_Admin::form_open( 'terms_woocommerce' ); ?>
<table class="form-table">
    <tr><th><?php esc_html_e( 'WooCommerce integration', 'wp-opengdpr' ); ?></th>
        <td><label><input type="checkbox" name="wpog_tc[woocommerce_enabled]" value="1" <?php checked( $s['woocommerce_enabled'] ); ?> /> <?php esc_html_e( 'Enable the WooCommerce checkout integration', 'wp-opengdpr' ); ?></label></td></tr>

    <tr><th><?php esc_html_e( 'Checkbox position', 'wp-opengdpr' ); ?></th>
        <td><select name="wpog_tc[wc_checkout_position]">
            <option value="before_submit" <?php selected( $position, 'before_submit' ); ?>><?php esc_html_e( 'Before the “Place order” button', 'wp-opengdpr' ); ?></option>
            <option value="after_terms" <?php selected( $position, 'after_terms' ); ?>><?php esc_html_e( 'After the native Terms block', 'wp-opengdpr' ); ?></option>
        </select></td></tr>

    <tr><th><?php esc_html_e( 'Replace native WC Terms checkbox', 'wp-opengdpr' ); ?></th>
        <td><label><input type="checkbox" name="wpog_tc[wc_replace_native_terms]" value="1" <?php checked( $s['wc_replace_native_terms'] ); ?> /> <?php esc_html_e( 'Disable WooCommerce’s own Terms checkbox and let this plugin handle it', 'wp-opengdpr' ); ?></label></td></tr>

    <tr><th><?php esc_html_e( 'Show Terms inline', 'wp-opengdpr' ); ?></th>
        <td><label><input type="checkbox" name="wpog_tc[wc_show_terms_inline]" value="1" <?php checked( $s['wc_show_terms_inline'] ); ?> /> <?php esc_html_e( 'Show the Terms text in a collapsible box above the checkbox', 'wp-opengdpr' ); ?></label></td></tr>

    <tr><th><?php esc_html_e( 'Save consent as order meta', 'wp-opengdpr' ); ?></th>
        <td><label><input type="checkbox" name="wpog_tc[log_save_to_order_meta]" value="1" <?php checked( $s['log_save_to_order_meta'] ); ?> /> <?php esc_html_e( 'Store the consent on the order (recommended audit trail)', 'wp-opengdpr' ); ?></label></td></tr>

    <tr><th><?php esc_html_e( '“GDPR consents” order-list column', 'wp-opengdpr' ); ?></th>
        <td><label><input type="checkbox" name="wpog_tc[wc_order_column]" value="1" <?php checked( $s['wc_order_column'] ); ?> /> <?php esc_html_e( 'Add a column with ✓/✗ icons to the orders list (HPOS compatible)', 'wp-opengdpr' ); ?></label></td></tr>
    <tr><th><?php esc_html_e( 'Registration consent', 'wp-opengdpr' ); ?></th>
        <td><label><input type="checkbox" name="wpog_tc[wc_registration_consent]" value="1" <?php checked( $s['wc_registration_consent'] ); ?> /> <?php esc_html_e( 'Show Privacy and Marketing consent checkboxes on the My Account registration form and log acceptance', 'wp-opengdpr' ); ?></label>
        <p class="description"><?php esc_html_e( 'Uses the same Privacy and Marketing settings configured in the General tab. Terms acceptance is not shown (it is checkout-specific).', 'wp-opengdpr' ); ?></p></td></tr>
</table>
<?php WPOG_Admin::form_close(); ?>

<?php endif; ?>
