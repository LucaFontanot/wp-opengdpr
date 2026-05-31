<?php if ( ! defined( 'ABSPATH' ) ) { exit; }
WPOG_Admin::notice();
$s = WPOG_Settings::get( 'terms_consent' );
$wc_active = class_exists( 'WooCommerce' );
?>
<h1><?php esc_html_e( 'Terms & Conditions — Settings', 'wp-opengdpr' ); ?></h1>
<p class="description"><?php esc_html_e( 'Sale Terms & Conditions consent for e-commerce checkouts. Independent from the Cookie Consent and Privacy Consent modules: disabling one does not affect the others.', 'wp-opengdpr' ); ?></p>

<div class="notice notice-warning inline" style="margin:12px 0;"><p>
    <strong><?php esc_html_e( 'Legal note:', 'wp-opengdpr' ); ?></strong>
    <?php esc_html_e( 'Sale Terms & Conditions must be drafted by a legal professional and kept up to date with every regulatory change. This plugin handles the collection and logging of consents but does not guarantee the legal compliance of the Terms content itself.', 'wp-opengdpr' ); ?>
</p></div>

<?php if ( ! $wc_active ) : ?>
<div class="notice notice-info inline" style="margin:12px 0;"><p>
    <?php esc_html_e( 'WooCommerce is not active. The module will start collecting checkout consents once WooCommerce is installed and active.', 'wp-opengdpr' ); ?>
</p></div>
<?php endif; ?>

<?php WPOG_Admin::form_open( 'terms_general' ); ?>
<table class="form-table">
    <tr><th><?php esc_html_e( 'Module enabled', 'wp-opengdpr' ); ?></th>
        <td><label><input type="checkbox" name="wpog_tc[enabled]" value="1" <?php checked( $s['enabled'] ); ?> /> <?php esc_html_e( 'Enable the Terms & Conditions module', 'wp-opengdpr' ); ?></label></td></tr>

    <tr><th><?php esc_html_e( 'Terms & Conditions page', 'wp-opengdpr' ); ?></th>
        <td><?php wp_dropdown_pages( array(
                'name'              => 'wpog_tc[terms_page_id]',
                'selected'          => (int) $s['terms_page_id'],
                'show_option_none'  => __( '— Select a page —', 'wp-opengdpr' ),
                'option_none_value' => 0,
            ) ); ?>
            <p class="description"><?php esc_html_e( 'The published page holding your Sale Terms & Conditions.', 'wp-opengdpr' ); ?></p></td></tr>

    <tr><th><?php esc_html_e( 'Current Terms version', 'wp-opengdpr' ); ?></th>
        <td><input type="text" name="wpog_tc[terms_version]" value="<?php echo esc_attr( $s['terms_version'] ); ?>" />
            <p class="description"><?php esc_html_e( 'Bump it on every substantial change. Recorded with every acceptance.', 'wp-opengdpr' ); ?></p></td></tr>

    <tr><th><?php esc_html_e( 'Last updated', 'wp-opengdpr' ); ?></th>
        <td><input type="date" name="wpog_tc[terms_last_updated]" value="<?php echo esc_attr( $s['terms_last_updated'] ); ?>" /></td></tr>

    <tr><th><?php esc_html_e( 'Privacy Policy page', 'wp-opengdpr' ); ?></th>
        <td><?php wp_dropdown_pages( array(
                'name'              => 'wpog_tc[privacy_page_id]',
                'selected'          => (int) $s['privacy_page_id'],
                'show_option_none'  => __( '— Select a page —', 'wp-opengdpr' ),
                'option_none_value' => 0,
            ) ); ?>
            <p class="description"><?php esc_html_e( 'May be the same page used by the Privacy Consent module. Falls back to the global Privacy URL when left empty.', 'wp-opengdpr' ); ?></p></td></tr>

    <tr><th><?php esc_html_e( 'Block order without Terms acceptance', 'wp-opengdpr' ); ?></th>
        <td><label><input type="checkbox" name="wpog_tc[require_terms_acceptance]" value="1" <?php checked( $s['require_terms_acceptance'] ); ?> /> <?php esc_html_e( 'Require the Terms & Conditions checkbox at checkout', 'wp-opengdpr' ); ?></label></td></tr>

    <tr><th><?php esc_html_e( 'Separate privacy acceptance', 'wp-opengdpr' ); ?></th>
        <td><label><input type="checkbox" name="wpog_tc[require_privacy_acceptance]" value="1" <?php checked( $s['require_privacy_acceptance'] ); ?> /> <?php esc_html_e( 'Require a separate Privacy Policy checkbox at checkout', 'wp-opengdpr' ); ?></label></td></tr>

    <tr><th><?php esc_html_e( 'Marketing consent at checkout', 'wp-opengdpr' ); ?></th>
        <td><label><input type="checkbox" name="wpog_tc[show_marketing_consent]" value="1" <?php checked( $s['show_marketing_consent'] ); ?> /> <?php esc_html_e( 'Show an optional marketing/newsletter checkbox (never blocks the order)', 'wp-opengdpr' ); ?></label></td></tr>

    <tr><th><?php esc_html_e( 'Age verification', 'wp-opengdpr' ); ?></th>
        <td><label><input type="checkbox" name="wpog_tc[require_age_verification]" value="1" <?php checked( $s['require_age_verification'] ); ?> /> <?php esc_html_e( 'Require minimum-age confirmation (age-restricted products)', 'wp-opengdpr' ); ?></label></td></tr>

    <tr><th><?php esc_html_e( 'Minimum age', 'wp-opengdpr' ); ?></th>
        <td><input type="number" min="0" name="wpog_tc[minimum_age]" value="<?php echo esc_attr( $s['minimum_age'] ); ?>" /></td></tr>

    <tr><th><?php esc_html_e( 'Log retention (days)', 'wp-opengdpr' ); ?></th>
        <td><input type="number" min="0" name="wpog_tc[log_retention_days]" value="<?php echo esc_attr( $s['log_retention_days'] ); ?>" />
            <p class="description"><?php esc_html_e( '730 days (2 years) recommended as contractual proof. 0 = keep forever.', 'wp-opengdpr' ); ?></p></td></tr>
</table>
<?php WPOG_Admin::form_close(); ?>

<hr/>
<h2><?php esc_html_e( 'Checkbox texts & error messages', 'wp-opengdpr' ); ?></h2>
<?php WPOG_Admin::form_open( 'terms_texts' ); ?>
<table class="form-table">
    <tr><th><?php esc_html_e( 'Terms checkbox label', 'wp-opengdpr' ); ?></th>
        <td><textarea name="wpog_tc[terms_checkbox_label]" rows="2" class="large-text"><?php echo esc_textarea( $s['terms_checkbox_label'] ); ?></textarea>
            <p class="description"><?php esc_html_e( 'Placeholders: {terms_url}, {privacy_url}. Links allowed.', 'wp-opengdpr' ); ?></p></td></tr>

    <tr><th><?php esc_html_e( 'Privacy checkbox label', 'wp-opengdpr' ); ?></th>
        <td><textarea name="wpog_tc[privacy_checkbox_label]" rows="2" class="large-text"><?php echo esc_textarea( $s['privacy_checkbox_label'] ); ?></textarea>
            <p class="description"><?php esc_html_e( 'Placeholder: {privacy_url}. Links allowed.', 'wp-opengdpr' ); ?></p></td></tr>

    <tr><th><?php esc_html_e( 'Marketing checkbox label', 'wp-opengdpr' ); ?></th>
        <td><textarea name="wpog_tc[marketing_checkbox_label]" rows="2" class="large-text"><?php echo esc_textarea( $s['marketing_checkbox_label'] ); ?></textarea></td></tr>

    <tr><th><?php esc_html_e( 'Age checkbox label', 'wp-opengdpr' ); ?></th>
        <td><input type="text" class="large-text" name="wpog_tc[age_checkbox_label]" value="<?php echo esc_attr( $s['age_checkbox_label'] ); ?>" />
            <p class="description"><?php esc_html_e( 'Placeholder: {min_age}.', 'wp-opengdpr' ); ?></p></td></tr>

    <tr><th><?php esc_html_e( 'Terms error message', 'wp-opengdpr' ); ?></th>
        <td><input type="text" class="large-text" name="wpog_tc[terms_error_message]" value="<?php echo esc_attr( $s['terms_error_message'] ); ?>" /></td></tr>

    <tr><th><?php esc_html_e( 'Privacy error message', 'wp-opengdpr' ); ?></th>
        <td><input type="text" class="large-text" name="wpog_tc[privacy_error_message]" value="<?php echo esc_attr( $s['privacy_error_message'] ); ?>" /></td></tr>

    <tr><th><?php esc_html_e( 'Age error message', 'wp-opengdpr' ); ?></th>
        <td><input type="text" class="large-text" name="wpog_tc[age_error_message]" value="<?php echo esc_attr( $s['age_error_message'] ); ?>" />
            <p class="description"><?php esc_html_e( 'Placeholder: {min_age}.', 'wp-opengdpr' ); ?></p></td></tr>
</table>
<?php WPOG_Admin::form_close(); ?>
