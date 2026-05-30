<?php if ( ! defined( 'ABSPATH' ) ) { exit; }
WPOG_Admin::notice();
$s           = WPOG_Settings::get( 'form_consent' );
$preview_main = WPOG_Form_Consent::main_label();
?>
<h1><?php esc_html_e( 'Privacy Consent — Texts & Labels', 'wp-opengdpr' ); ?></h1>

<?php if ( ! empty( $s['checkbox_marketing_enabled'] ) && ! empty( $s['checkbox_marketing_required'] ) ) : ?>
<div class="notice notice-error inline" style="margin:12px 0;"><p>
    <?php echo esc_html__( '⚠️ The marketing consent should not be mandatory. Making it required may violate GDPR Art. 7, which requires consent for marketing purposes to always be freely given.', 'wp-opengdpr' ); ?>
</p></div>
<?php endif; ?>

<?php WPOG_Admin::form_open( 'form_texts' ); ?>

<h2><?php esc_html_e( 'Main consent checkbox (mandatory)', 'wp-opengdpr' ); ?></h2>
<table class="form-table">
    <tr><th><?php esc_html_e( 'Enabled', 'wp-opengdpr' ); ?></th>
        <td><label><input type="checkbox" name="wpog_fc[checkbox_main_enabled]" value="1" <?php checked( $s['checkbox_main_enabled'] ); ?> /> <?php esc_html_e( 'Show the main consent checkbox', 'wp-opengdpr' ); ?></label></td></tr>
    <tr><th><?php esc_html_e( 'Required', 'wp-opengdpr' ); ?></th>
        <td><label><input type="checkbox" name="wpog_fc[checkbox_main_required]" value="1" <?php checked( $s['checkbox_main_required'] ); ?> /> <?php esc_html_e( 'Block submission if not ticked (recommended)', 'wp-opengdpr' ); ?></label></td></tr>
    <tr><th><?php esc_html_e( 'Checkbox text', 'wp-opengdpr' ); ?></th>
        <td>
            <textarea name="wpog_fc[checkbox_main_label]" rows="3" class="large-text"><?php echo esc_textarea( $s['checkbox_main_label'] ); ?></textarea>
            <p class="description"><?php echo wp_kses_post( __( 'You may use a single <code>&lt;a&gt;</code> link. Use <code>{privacy_url}</code> as the placeholder for the Privacy Policy URL.', 'wp-opengdpr' ) ); ?></p>
            <p><strong><?php esc_html_e( 'Preview:', 'wp-opengdpr' ); ?></strong></p>
            <div style="padding:8px 12px;border:1px solid #e0e0e0;border-radius:4px;font-size:13px;color:#555;max-width:640px;">
                <?php echo wp_kses( $preview_main, array( 'a' => array( 'href' => array(), 'target' => array(), 'rel' => array() ) ) ); ?>
            </div>
        </td></tr>
    <tr><th><?php esc_html_e( 'Error message', 'wp-opengdpr' ); ?></th>
        <td><input type="text" class="large-text" name="wpog_fc[checkbox_main_error]" value="<?php echo esc_attr( $s['checkbox_main_error'] ); ?>" /></td></tr>
</table>

<h2><?php esc_html_e( 'Marketing consent checkbox (optional)', 'wp-opengdpr' ); ?></h2>
<table class="form-table">
    <tr><th><?php esc_html_e( 'Enabled', 'wp-opengdpr' ); ?></th>
        <td><label><input type="checkbox" name="wpog_fc[checkbox_marketing_enabled]" value="1" <?php checked( $s['checkbox_marketing_enabled'] ); ?> /> <?php esc_html_e( 'Show a separate marketing/newsletter consent checkbox', 'wp-opengdpr' ); ?></label></td></tr>
    <tr><th><?php esc_html_e( 'Required', 'wp-opengdpr' ); ?></th>
        <td><label><input type="checkbox" name="wpog_fc[checkbox_marketing_required]" value="1" <?php checked( $s['checkbox_marketing_required'] ); ?> /> <?php esc_html_e( 'Make mandatory (not recommended — may violate GDPR Art. 7)', 'wp-opengdpr' ); ?></label></td></tr>
    <tr><th><?php esc_html_e( 'Checkbox text', 'wp-opengdpr' ); ?></th>
        <td><textarea name="wpog_fc[checkbox_marketing_label]" rows="3" class="large-text"><?php echo esc_textarea( $s['checkbox_marketing_label'] ); ?></textarea>
        <p class="description"><?php echo wp_kses_post( __( 'You may use a single <code>&lt;a&gt;</code> link.', 'wp-opengdpr' ) ); ?></p></td></tr>
</table>

<?php WPOG_Admin::form_close(); ?>
