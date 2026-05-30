<?php if ( ! defined( 'ABSPATH' ) ) { exit; }
WPOG_Admin::notice();
$s        = WPOG_Settings::get( 'form_consent' );
$mode     = ( 'manual' === ( $s['cf7_position'] ?? '' ) || empty( $s['cf7_auto_inject'] ) ) ? 'manual' : 'auto';
$position = in_array( $s['cf7_position'] ?? 'before_submit', array( 'before_submit', 'after_fields' ), true ) ? $s['cf7_position'] : 'before_submit';
$cf7_active  = class_exists( 'WPCF7' );
$wpf_active  = function_exists( 'wpforms' );
$enabled_ids = (array) ( $s['cf7_form_ids'] ?? array() );
$wpf_ids     = (array) ( $s['wpforms_form_ids'] ?? array() );

// Build the list of CF7 forms.
$cf7_forms = array();
if ( $cf7_active && class_exists( 'WPCF7_ContactForm' ) ) {
    $forms = WPCF7_ContactForm::find( array( 'posts_per_page' => 200 ) );
    foreach ( (array) $forms as $f ) {
        $cf7_forms[ (int) $f->id() ] = $f->title();
    }
}

// Build the list of WPForms forms.
$wpf_forms = array();
if ( $wpf_active ) {
    $ids = wpforms()->form->get( '', array( 'fields' => 'ids' ) );
    foreach ( (array) $ids as $fid ) {
        $wpf_forms[ (int) $fid ] = get_the_title( $fid );
    }
}
?>
<h1><?php esc_html_e( 'Privacy Consent — Form Integrations', 'wp-opengdpr' ); ?></h1>

<?php WPOG_Admin::form_open( 'form_integrations' ); ?>

<h2><?php esc_html_e( 'Contact Form 7', 'wp-opengdpr' ); ?></h2>

<?php if ( ! $cf7_active ) : ?>
    <div class="notice notice-warning inline"><p><?php esc_html_e( 'Contact Form 7 is not active. Install and activate the plugin to enable this integration.', 'wp-opengdpr' ); ?></p></div>
<?php else : ?>
<table class="form-table">
    <tr><th><?php esc_html_e( 'CF7 integration', 'wp-opengdpr' ); ?></th>
        <td><label><input type="checkbox" name="wpog_fc[cf7_enabled]" value="1" <?php checked( $s['cf7_enabled'] ); ?> /> <?php esc_html_e( 'Enable the Contact Form 7 integration', 'wp-opengdpr' ); ?></label></td></tr>

    <tr><th><?php esc_html_e( 'Injection mode', 'wp-opengdpr' ); ?></th>
        <td>
            <label style="display:block;margin-bottom:4px;"><input type="radio" name="wpog_fc[cf7_mode]" value="auto" <?php checked( $mode, 'auto' ); ?> /> <?php esc_html_e( 'Automatic — inject the checkbox into the selected forms', 'wp-opengdpr' ); ?></label>
            <label style="display:block;"><input type="radio" name="wpog_fc[cf7_mode]" value="manual" <?php checked( $mode, 'manual' ); ?> /> <?php esc_html_e( 'Manual — use the [wpog_privacy_consent] tag in the form template', 'wp-opengdpr' ); ?></label>
        </td></tr>

    <tr><th><?php esc_html_e( 'Checkbox position (automatic)', 'wp-opengdpr' ); ?></th>
        <td><select name="wpog_fc[cf7_position]">
            <option value="before_submit" <?php selected( $position, 'before_submit' ); ?>><?php esc_html_e( 'Before the submit button', 'wp-opengdpr' ); ?></option>
            <option value="after_fields"  <?php selected( $position, 'after_fields' ); ?>><?php esc_html_e( 'After all fields', 'wp-opengdpr' ); ?></option>
        </select></td></tr>

    <tr><th><?php esc_html_e( 'Enabled forms', 'wp-opengdpr' ); ?></th>
        <td>
            <?php if ( empty( $cf7_forms ) ) : ?>
                <p class="description"><?php esc_html_e( 'No Contact Form 7 forms found.', 'wp-opengdpr' ); ?></p>
            <?php else : ?>
                <select name="wpog_fc[cf7_form_ids][]" multiple size="<?php echo (int) min( 8, max( 3, count( $cf7_forms ) ) ); ?>" style="min-width:280px;">
                    <?php foreach ( $cf7_forms as $fid => $ftitle ) : ?>
                        <option value="<?php echo esc_attr( $fid ); ?>" <?php echo in_array( $fid, array_map( 'intval', $enabled_ids ), true ) ? 'selected' : ''; ?>><?php echo esc_html( $ftitle . ' (#' . $fid . ')' ); ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="description"><?php esc_html_e( 'Select nothing to apply to all forms. Hold Ctrl/Cmd to select multiple.', 'wp-opengdpr' ); ?></p>
            <?php endif; ?>
        </td></tr>
</table>

<div class="notice notice-info inline" style="margin:12px 0;"><p>
    <?php esc_html_e( 'Manual mode — add these tags inside your CF7 form template:', 'wp-opengdpr' ); ?><br/>
    <?php esc_html_e( 'Mandatory consent:', 'wp-opengdpr' ); ?> <code>[wpog_privacy_consent]</code><br/>
    <?php esc_html_e( 'Marketing consent:', 'wp-opengdpr' ); ?> <code>[wpog_marketing_consent]</code>
</p></div>
<?php endif; ?>

<h2><?php esc_html_e( 'WPForms', 'wp-opengdpr' ); ?></h2>

<?php if ( ! $wpf_active ) : ?>
    <div class="notice notice-warning inline"><p><?php esc_html_e( 'WPForms is not active. Install and activate the plugin to enable this integration.', 'wp-opengdpr' ); ?></p></div>
<?php else : ?>
<table class="form-table">
    <tr><th><?php esc_html_e( 'WPForms integration', 'wp-opengdpr' ); ?></th>
        <td><label><input type="checkbox" name="wpog_fc[wpforms_enabled]" value="1" <?php checked( $s['wpforms_enabled'] ); ?> /> <?php esc_html_e( 'Enable the WPForms integration', 'wp-opengdpr' ); ?></label></td></tr>

    <tr><th><?php esc_html_e( 'Enabled forms', 'wp-opengdpr' ); ?></th>
        <td>
            <?php if ( empty( $wpf_forms ) ) : ?>
                <p class="description"><?php esc_html_e( 'No WPForms forms found.', 'wp-opengdpr' ); ?></p>
            <?php else : ?>
                <select name="wpog_fc[wpforms_form_ids][]" multiple size="<?php echo (int) min( 8, max( 3, count( $wpf_forms ) ) ); ?>" style="min-width:280px;">
                    <?php foreach ( $wpf_forms as $fid => $ftitle ) : ?>
                        <option value="<?php echo esc_attr( $fid ); ?>" <?php echo in_array( $fid, array_map( 'intval', $wpf_ids ), true ) ? 'selected' : ''; ?>><?php echo esc_html( $ftitle . ' (#' . $fid . ')' ); ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="description"><?php esc_html_e( 'Select nothing to apply to all forms. Hold Ctrl/Cmd to select multiple.', 'wp-opengdpr' ); ?></p>
            <?php endif; ?>
        </td></tr>
</table>
<?php endif; ?>

<h2><?php esc_html_e( 'Other form builders', 'wp-opengdpr' ); ?></h2>
<table class="form-table">
    <?php foreach ( array( 'Gravity Forms', 'Elementor Forms', 'Generic form (WordPress hook)' ) as $builder ) : ?>
    <tr><th><?php echo esc_html( $builder ); ?></th>
        <td><label><input type="checkbox" disabled /> <?php esc_html_e( 'Coming soon', 'wp-opengdpr' ); ?></label></td></tr>
    <?php endforeach; ?>
</table>

<?php WPOG_Admin::form_close(); ?>
