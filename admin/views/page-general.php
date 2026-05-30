<?php if ( ! defined( 'ABSPATH' ) ) { exit; }
WPOG_Admin::notice();
$g = WPOG_Settings::get( 'general' );
?>
<h1><?php esc_html_e( 'General Settings', 'wp-opengdpr' ); ?></h1>
<p class="description"><?php esc_html_e( 'Shared URLs used by both the cookie consent banner and the form privacy consent module.', 'wp-opengdpr' ); ?></p>
<?php WPOG_Admin::form_open( 'general' ); ?>
<table class="form-table">
    <tr><th><?php esc_html_e( 'Privacy Policy URL', 'wp-opengdpr' ); ?></th>
        <td><input type="url" class="regular-text" name="wpog[privacy_url]" value="<?php echo esc_attr( $g['privacy_url'] ); ?>" />
        <p class="description"><?php esc_html_e( 'Linked in the cookie banner and used as the {privacy_url} placeholder in form consent checkbox labels.', 'wp-opengdpr' ); ?></p></td></tr>
    <tr><th><?php esc_html_e( 'Cookie Policy URL', 'wp-opengdpr' ); ?></th>
        <td><input type="url" class="regular-text" name="wpog[cookie_url]" value="<?php echo esc_attr( $g['cookie_url'] ); ?>" />
        <p class="description"><?php esc_html_e( 'Linked in the cookie banner footer.', 'wp-opengdpr' ); ?></p></td></tr>
</table>
<h2><?php esc_html_e( 'Shortcode', 'wp-opengdpr' ); ?></h2>
<p><?php esc_html_e( 'Use the shortcode below anywhere in your content to add a link/button that opens the cookie settings.', 'wp-opengdpr' ); ?></p>
<p><code>[wpog_settings]</code> &mdash; <?php esc_html_e( 'renders a button that opens the cookie popup.', 'wp-opengdpr' ); ?><br/>
<?php esc_html_e( 'Optional attributes:', 'wp-opengdpr' ); ?> <code>label="Cookie Settings"</code>, <code>class="my-class"</code></p>
<?php WPOG_Admin::form_close(); ?>
