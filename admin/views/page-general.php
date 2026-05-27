<?php if ( ! defined( 'ABSPATH' ) ) { exit; }
WPOG_Admin::notice();
$g = WPOG_Settings::get( 'general' );
?>
<h1><?php esc_html_e( 'General Settings', 'wp-opengdpr' ); ?></h1>
<?php WPOG_Admin::form_open( 'general' ); ?>
<table class="form-table">
    <tr><th><?php esc_html_e( 'Plugin enabled', 'wp-opengdpr' ); ?></th>
        <td><label><input type="checkbox" name="wpog[enabled]" value="1" <?php checked( $g['enabled'] ); ?> /> <?php esc_html_e( 'Enable consent banner on the front-end', 'wp-opengdpr' ); ?></label></td></tr>
    <tr><th><?php esc_html_e( 'Consent duration (days)', 'wp-opengdpr' ); ?></th>
        <td><input type="number" min="1" name="wpog[consent_duration]" value="<?php echo esc_attr( $g['consent_duration'] ); ?>" />
        <p class="description"><?php esc_html_e( 'EU DPAs recommend a maximum of 180 days.', 'wp-opengdpr' ); ?></p></td></tr>
    <tr><th><?php esc_html_e( 'Privacy policy version', 'wp-opengdpr' ); ?></th>
        <td><input type="text" name="wpog[policy_version]" value="<?php echo esc_attr( $g['policy_version'] ); ?>" />
        <p class="description"><?php esc_html_e( 'Bumping this value invalidates all previously stored consents.', 'wp-opengdpr' ); ?></p></td></tr>
    <tr><th><?php esc_html_e( 'Privacy Policy URL', 'wp-opengdpr' ); ?></th>
        <td><input type="url" class="regular-text" name="wpog[privacy_url]" value="<?php echo esc_attr( $g['privacy_url'] ); ?>" /></td></tr>
    <tr><th><?php esc_html_e( 'Cookie Policy URL', 'wp-opengdpr' ); ?></th>
        <td><input type="url" class="regular-text" name="wpog[cookie_url]" value="<?php echo esc_attr( $g['cookie_url'] ); ?>" /></td></tr>
    <tr><th><?php esc_html_e( 'Show only to EU visitors', 'wp-opengdpr' ); ?></th>
        <td><label><input type="checkbox" name="wpog[eu_only]" value="1" <?php checked( $g['eu_only'] ); ?> /> <?php esc_html_e( 'Requires geolocation provider (MaxMind, etc.)', 'wp-opengdpr' ); ?></label></td></tr>
    <tr><th><?php esc_html_e( 'Enable consent log', 'wp-opengdpr' ); ?></th>
        <td><label><input type="checkbox" name="wpog[log_enabled]" value="1" <?php checked( $g['log_enabled'] ); ?> /> <?php esc_html_e( 'Store consents in the database', 'wp-opengdpr' ); ?></label></td></tr>
    <tr><th><?php esc_html_e( 'Anonymise IP in logs', 'wp-opengdpr' ); ?></th>
        <td><label><input type="checkbox" name="wpog[anonymize_ip]" value="1" <?php checked( $g['anonymize_ip'] ); ?> /> <?php esc_html_e( 'Required by GDPR', 'wp-opengdpr' ); ?></label></td></tr>
    <tr><th><?php esc_html_e( 'Log retention (days)', 'wp-opengdpr' ); ?></th>
        <td><input type="number" min="0" name="wpog[log_retention_days]" value="<?php echo esc_attr( $g['log_retention_days'] ); ?>" /></td></tr>
</table>
<h2><?php esc_html_e( 'Floating Action Button', 'wp-opengdpr' ); ?></h2>
<p class="description"><?php esc_html_e( 'A floating button that lets users re-open the cookie settings at any time after consenting.', 'wp-opengdpr' ); ?></p>
<table class="form-table">
    <tr><th><?php esc_html_e( 'Enable floating button', 'wp-opengdpr' ); ?></th>
        <td><label><input type="checkbox" name="wpog[fab_enabled]" value="1" <?php checked( $g['fab_enabled'] ); ?> /> <?php esc_html_e( 'Show a floating cookie button after the user has accepted cookies', 'wp-opengdpr' ); ?></label></td></tr>
    <tr><th><?php esc_html_e( 'Button position', 'wp-opengdpr' ); ?></th>
        <td><select name="wpog[fab_position]">
            <option value="bottom-right" <?php selected( $g['fab_position'], 'bottom-right' ); ?>><?php esc_html_e( 'Bottom Right', 'wp-opengdpr' ); ?></option>
            <option value="bottom-left"  <?php selected( $g['fab_position'], 'bottom-left' );  ?>><?php esc_html_e( 'Bottom Left', 'wp-opengdpr' ); ?></option>
        </select></td></tr>
    <tr><th><?php esc_html_e( 'Button label / icon', 'wp-opengdpr' ); ?></th>
        <td><input type="text" name="wpog[fab_label]" value="<?php echo esc_attr( $g['fab_label'] ); ?>" />
        <p class="description"><?php esc_html_e( 'Emoji or short text shown inside the button. Default: 🍪', 'wp-opengdpr' ); ?></p></td></tr>
    <tr><th><?php esc_html_e( 'Button background colour', 'wp-opengdpr' ); ?></th>
        <td><input type="text" name="wpog[fab_bg_color]" value="<?php echo esc_attr( $g['fab_bg_color'] ); ?>" class="wpog-color" />
        <p class="description"><?php esc_html_e( 'Leave empty to use the banner primary colour.', 'wp-opengdpr' ); ?></p></td></tr>
    <tr><th><?php esc_html_e( 'Button text colour', 'wp-opengdpr' ); ?></th>
        <td><input type="text" name="wpog[fab_text_color]" value="<?php echo esc_attr( $g['fab_text_color'] ); ?>" class="wpog-color" />
        <p class="description"><?php esc_html_e( 'Leave empty to use the banner primary text colour.', 'wp-opengdpr' ); ?></p></td></tr>
</table>
<h2><?php esc_html_e( 'Shortcode', 'wp-opengdpr' ); ?></h2>
<p><?php esc_html_e( 'Use the shortcode below anywhere in your content to add a link/button that opens the cookie settings.', 'wp-opengdpr' ); ?></p>
<p><code>[wpog_settings]</code> &mdash; <?php esc_html_e( 'renders a button that opens the cookie popup.', 'wp-opengdpr' ); ?><br/>
<?php esc_html_e( 'Optional attributes:', 'wp-opengdpr' ); ?> <code>label="Cookie Settings"</code>, <code>class="my-class"</code></p>
<?php WPOG_Admin::form_close(); ?>
