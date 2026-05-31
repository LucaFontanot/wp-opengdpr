<?php if ( ! defined( 'ABSPATH' ) ) { exit; }
WPOG_Admin::notice();
$s        = WPOG_Settings::get( 'terms_consent' );
$versions = WPOG_Terms_Consent::get_versions();
$cur_hash = WPOG_Terms_Consent::terms_hash();
$has_page = (int) $s['terms_page_id'] > 0;
?>
<h1><?php esc_html_e( 'Terms & Conditions — Documents & Versions', 'wp-opengdpr' ); ?></h1>
<p class="description"><?php esc_html_e( 'Keep a history of your Terms versions. Registering a version captures the current Terms page text, computes its SHA-256 hash and marks it active.', 'wp-opengdpr' ); ?></p>

<?php if ( ! $has_page ) : ?>
<div class="notice notice-warning inline" style="margin:12px 0;"><p>
    <?php esc_html_e( 'Select a Terms & Conditions page in the General Settings first.', 'wp-opengdpr' ); ?>
</p></div>
<?php else : ?>
<div class="notice notice-info inline" style="margin:12px 0;"><p>
    <?php esc_html_e( 'Current Terms page hash (SHA-256):', 'wp-opengdpr' ); ?>
    <code><?php echo esc_html( $cur_hash ?: __( '(empty page)', 'wp-opengdpr' ) ); ?></code>
</p></div>
<?php endif; ?>

<h2><?php esc_html_e( 'Register current version', 'wp-opengdpr' ); ?></h2>
<form method="post">
    <?php wp_nonce_field( WPOG_Admin::NONCE ); ?>
    <input type="hidden" name="wpog_form" value="terms_register_version" />
    <table class="form-table">
        <tr><th><?php esc_html_e( 'Version', 'wp-opengdpr' ); ?></th>
            <td><input type="text" name="wpog_tc_version" value="<?php echo esc_attr( $s['terms_version'] ); ?>" required /></td></tr>
        <tr><th><?php esc_html_e( 'Effective date', 'wp-opengdpr' ); ?></th>
            <td><input type="date" name="wpog_tc_effective_date" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" /></td></tr>
        <tr><th><?php esc_html_e( 'Reason for change', 'wp-opengdpr' ); ?></th>
            <td><textarea name="wpog_tc_reason" rows="2" class="large-text" placeholder="<?php esc_attr_e( 'e.g. Update for ODR removal, GPSR update…', 'wp-opengdpr' ); ?>"></textarea></td></tr>
    </table>
    <?php submit_button( __( 'Register current version', 'wp-opengdpr' ) ); ?>
</form>

<hr/>
<h2><?php esc_html_e( 'Version history', 'wp-opengdpr' ); ?></h2>
<table class="widefat striped">
    <thead><tr>
        <th><?php esc_html_e( 'Version', 'wp-opengdpr' ); ?></th>
        <th><?php esc_html_e( 'Effective date', 'wp-opengdpr' ); ?></th>
        <th><?php esc_html_e( 'Reason', 'wp-opengdpr' ); ?></th>
        <th><?php esc_html_e( 'Hash (SHA-256)', 'wp-opengdpr' ); ?></th>
        <th><?php esc_html_e( 'Status', 'wp-opengdpr' ); ?></th>
    </tr></thead>
    <tbody>
    <?php if ( empty( $versions ) ) : ?>
        <tr><td colspan="5"><?php esc_html_e( 'No versions registered yet.', 'wp-opengdpr' ); ?></td></tr>
    <?php else : foreach ( array_reverse( $versions ) as $v ) : ?>
        <tr>
            <td><?php echo esc_html( $v['version'] ?? '' ); ?></td>
            <td><?php echo esc_html( $v['effective_date'] ?? '' ); ?></td>
            <td><?php echo esc_html( $v['reason'] ?? '' ); ?></td>
            <td><code title="<?php echo esc_attr( $v['hash'] ?? '' ); ?>"><?php echo esc_html( $v['hash'] ? substr( $v['hash'], 0, 16 ) . '…' : '—' ); ?></code></td>
            <td><?php echo 'active' === ( $v['status'] ?? '' )
                ? '<span style="color:#46b450;font-weight:600;">' . esc_html__( 'Active', 'wp-opengdpr' ) . '</span>'
                : esc_html__( 'Historic', 'wp-opengdpr' ); ?></td>
        </tr>
    <?php endforeach; endif; ?>
    </tbody>
</table>
