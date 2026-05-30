<?php if ( ! defined( 'ABSPATH' ) ) { exit; }
WPOG_Admin::notice();
$s = WPOG_Settings::get( 'form_consent' );
?>
<h1><?php esc_html_e( 'Privacy Consent — Settings', 'wp-opengdpr' ); ?></h1>
<p class="description"><?php esc_html_e( 'Privacy consent for contact forms (GDPR Art. 6/7). This is independent from the cookie consent: disabling one does not affect the other.', 'wp-opengdpr' ); ?></p>

<div class="notice notice-info inline" style="margin:12px 0;"><p>
    <?php esc_html_e( 'Visitor IP addresses are always anonymised before being stored. This cannot be disabled, to guarantee GDPR compliance.', 'wp-opengdpr' ); ?>
</p></div>

<?php WPOG_Admin::form_open( 'form_general' ); ?>
<table class="form-table">
    <tr><th><?php esc_html_e( 'Module enabled', 'wp-opengdpr' ); ?></th>
        <td><label><input type="checkbox" name="wpog_fc[enabled]" value="1" <?php checked( $s['enabled'] ); ?> /> <?php esc_html_e( 'Enable the form privacy consent module', 'wp-opengdpr' ); ?></label>
        <p class="description"><?php esc_html_e( 'Turn the whole module off without losing your configuration.', 'wp-opengdpr' ); ?></p></td></tr>

    <tr><th><?php esc_html_e( 'Privacy Policy version', 'wp-opengdpr' ); ?></th>
        <td><input type="text" name="wpog_fc[privacy_policy_version]" value="<?php echo esc_attr( $s['privacy_policy_version'] ); ?>" />
        <p class="description"><?php esc_html_e( 'Recorded with every consent. Bumping it lets you prove which policy version was accepted.', 'wp-opengdpr' ); ?></p></td></tr>

    <tr><th><?php esc_html_e( 'Block submit without consent', 'wp-opengdpr' ); ?></th>
        <td><label><input type="checkbox" name="wpog_fc[block_submit_without_consent]" value="1" <?php checked( $s['block_submit_without_consent'] ); ?> /> <?php esc_html_e( 'Prevent submission when the mandatory consent checkbox is not ticked', 'wp-opengdpr' ); ?></label>
        <p class="description"><?php esc_html_e( 'If disabled, the form is sent anyway and the missing consent is still recorded.', 'wp-opengdpr' ); ?></p></td></tr>

    <tr><th><?php esc_html_e( 'Enable consent log', 'wp-opengdpr' ); ?></th>
        <td><label><input type="checkbox" name="wpog_fc[log_enabled]" value="1" <?php checked( $s['log_enabled'] ); ?> /> <?php esc_html_e( 'Store every form consent in the database (audit trail)', 'wp-opengdpr' ); ?></label></td></tr>

    <tr><th><?php esc_html_e( 'Log retention (days)', 'wp-opengdpr' ); ?></th>
        <td><input type="number" min="0" name="wpog_fc[log_retention_days]" value="<?php echo esc_attr( $s['log_retention_days'] ); ?>" />
        <p class="description"><?php esc_html_e( 'Records older than this are removed automatically by the daily cleanup. 0 = keep forever.', 'wp-opengdpr' ); ?></p></td></tr>
</table>
<?php WPOG_Admin::form_close(); ?>
