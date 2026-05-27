<?php
/**
 * View: Export / Import Settings
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
WPOG_Admin::notice();
?>
<h1><?php esc_html_e( 'Export / Import Settings', 'wp-opengdpr' ); ?></h1>
<p class="description"><?php esc_html_e( 'Use this page to export all plugin settings to a JSON file and import them on another site to clone or migrate your configuration.', 'wp-opengdpr' ); ?></p>

<hr />

<h2><?php esc_html_e( 'Export All Settings', 'wp-opengdpr' ); ?></h2>
<p><?php esc_html_e( 'Click the button below to download a JSON file containing all plugin settings: General, Banner, Popup, Categories & Cookies, Scripts, Translations and Cookie Policy.', 'wp-opengdpr' ); ?></p>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
    <input type="hidden" name="action" value="wpog_export_settings" />
    <?php wp_nonce_field( WPOG_Admin::NONCE ); ?>
    <?php submit_button( __( 'Download Settings JSON', 'wp-opengdpr' ), 'primary', 'wpog_do_export', false ); ?>
</form>

<hr />

<h2><?php esc_html_e( 'Import Settings', 'wp-opengdpr' ); ?></h2>
<p><?php esc_html_e( 'Select a previously exported JSON file to restore all settings. This will overwrite the current configuration — this action cannot be undone.', 'wp-opengdpr' ); ?></p>
<form method="post" enctype="multipart/form-data">
    <?php wp_nonce_field( WPOG_Admin::NONCE ); ?>
    <input type="hidden" name="wpog_form" value="settings_import" />
    <table class="form-table">
        <tr>
            <th scope="row">
                <label for="wpog_settings_file"><?php esc_html_e( 'Settings file (.json)', 'wp-opengdpr' ); ?></label>
            </th>
            <td>
                <input type="file" name="wpog_settings_file" id="wpog_settings_file" accept=".json,application/json" />
                <p class="description"><?php esc_html_e( 'Upload a wpog-settings-YYYY-MM-DD.json file exported from this plugin.', 'wp-opengdpr' ); ?></p>
            </td>
        </tr>
    </table>
    <?php submit_button( __( 'Import Settings', 'wp-opengdpr' ), 'secondary', 'wpog_do_import' ); ?>
</form>

