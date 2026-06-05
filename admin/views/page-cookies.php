<?php if ( ! defined( 'ABSPATH' ) ) { exit; }
WPOG_Admin::notice();
$cats = WPOG_Settings::get( 'categories' );
$keys = array( 'necessary', 'functional', 'analytics', 'marketing' );
?>
<h1><?php esc_html_e( 'Categories & Cookies', 'wp-opengdpr' ); ?></h1>

<?php
// Open Cookie Database status.
$db_has_data   = WPOG_Cookie_Database::has_data();
$db_last_fetch = (int) get_option( 'wpog_cookie_db_last_fetch', 0 );
if ( ! $db_has_data ) : ?>
    <div class="notice notice-warning"><p>
        <?php esc_html_e( 'The Open Cookie Database has not been loaded yet. Click the button below to download it — this enables cookie name autocomplete and automatic pre-fill.', 'wp-opengdpr' ); ?>
    </p></div>
<?php endif; ?>

<p style="margin:8px 0 16px;">
    <strong><?php esc_html_e( 'Open Cookie Database', 'wp-opengdpr' ); ?>:</strong>
    <?php if ( $db_has_data ) : ?>
        <span style="color:#1a6b1a;">&#10003; <?php esc_html_e( 'Loaded', 'wp-opengdpr' ); ?></span>
        <?php if ( $db_last_fetch ) : ?>
            <em style="color:#888;margin-left:8px;"><?php echo esc_html( __( 'Last updated', 'wp-opengdpr' ) . ': ' . gmdate( 'Y-m-d', $db_last_fetch ) ); ?></em>
        <?php endif; ?>
    <?php else : ?>
        <span style="color:#8a0000;">&#10007; <?php esc_html_e( 'Not loaded', 'wp-opengdpr' ); ?></span>
    <?php endif; ?>
</p>
<form method="post" style="display:inline;">
    <?php wp_nonce_field( WPOG_Admin::NONCE ); ?>
    <input type="hidden" name="wpog_form" value="cookie_db_refresh" />
    <button type="submit" class="button button-secondary">
        <?php $db_has_data ? esc_html_e( 'Refresh database now', 'wp-opengdpr' ) : esc_html_e( 'Download database now', 'wp-opengdpr' ); ?>
    </button>
</form>

<hr style="margin:24px 0;" />

<?php WPOG_Admin::form_open( 'categories' ); ?>
<?php foreach ( $keys as $k ) :
    $cat = $cats[ $k ];
    $is_necessary = ( 'necessary' === $k );
?>
<div class="postbox">
    <h2 class="hndle" style="padding:8px 12px;"><?php echo esc_html( ucfirst( $k ) ); ?></h2>
    <div class="inside">
        <table class="form-table">
            <tr><th><?php esc_html_e( 'Name', 'wp-opengdpr' ); ?></th>
                <td><input type="text" class="regular-text" name="wpog[<?php echo esc_attr( $k ); ?>][name]" value="<?php echo esc_attr( $cat['name'] ); ?>" /></td></tr>
            <tr><th><?php esc_html_e( 'Description', 'wp-opengdpr' ); ?></th>
                <td><textarea rows="2" class="large-text" name="wpog[<?php echo esc_attr( $k ); ?>][description]"><?php echo esc_textarea( $cat['description'] ); ?></textarea></td></tr>
            <tr><th><?php esc_html_e( 'Extended description', 'wp-opengdpr' ); ?></th>
                <td><textarea rows="3" class="large-text" name="wpog[<?php echo esc_attr( $k ); ?>][extended]"><?php echo esc_textarea( $cat['extended'] ); ?></textarea></td></tr>
        </table>
        <h4><?php esc_html_e( 'Cookies', 'wp-opengdpr' ); ?></h4>
        <?php if ( $db_has_data ) : ?>
            <p class="description"><?php esc_html_e( 'Start typing a cookie name — suggestions from the Open Cookie Database will appear. Selecting one auto-fills the remaining fields.', 'wp-opengdpr' ); ?></p>
        <?php endif; ?>
        <table class="widefat wpog-cookies-table" data-cat="<?php echo esc_attr( $k ); ?>">
            <thead><tr><th><?php esc_html_e( 'Name', 'wp-opengdpr' ); ?></th><th><?php esc_html_e( 'Provider', 'wp-opengdpr' ); ?></th><th><?php esc_html_e( 'Duration', 'wp-opengdpr' ); ?></th><th><?php esc_html_e( 'Purpose', 'wp-opengdpr' ); ?></th><th><?php esc_html_e( 'Provider privacy URL', 'wp-opengdpr' ); ?></th><th></th></tr></thead>
            <tbody>
            <?php $i = 0; foreach ( (array) $cat['cookies'] as $c ) : ?>
                <tr>
                    <td><input type="text" name="wpog[<?php echo esc_attr( $k ); ?>][cookies][<?php echo $i; ?>][name]" value="<?php echo esc_attr( $c['name'] ?? '' ); ?>" /></td>
                    <td><input type="text" name="wpog[<?php echo esc_attr( $k ); ?>][cookies][<?php echo $i; ?>][provider]" value="<?php echo esc_attr( $c['provider'] ?? '' ); ?>" /></td>
                    <td><input type="text" name="wpog[<?php echo esc_attr( $k ); ?>][cookies][<?php echo $i; ?>][duration]" value="<?php echo esc_attr( $c['duration'] ?? '' ); ?>" /></td>
                    <td><input type="text" name="wpog[<?php echo esc_attr( $k ); ?>][cookies][<?php echo $i; ?>][purpose]" value="<?php echo esc_attr( $c['purpose'] ?? '' ); ?>" /></td>
                    <td><input type="url" name="wpog[<?php echo esc_attr( $k ); ?>][cookies][<?php echo $i; ?>][privacy]" value="<?php echo esc_attr( $c['privacy'] ?? '' ); ?>" /></td>
                    <td><button type="button" class="button wpog-row-del">&times;</button></td>
                </tr>
            <?php $i++; endforeach; ?>
            </tbody>
        </table>
        <p><button type="button" class="button wpog-row-add" data-cat="<?php echo esc_attr( $k ); ?>" data-index="<?php echo (int) $i; ?>"><?php esc_html_e( 'Add cookie', 'wp-opengdpr' ); ?></button></p>
        <?php if ( $is_necessary ) : ?>
            <p class="description"><?php esc_html_e( 'This category is always active and cannot be disabled by visitors.', 'wp-opengdpr' ); ?></p>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>
<?php WPOG_Admin::form_close(); ?>

<hr style="margin:32px 0 24px;" />
<h2><?php esc_html_e( 'Cookie Policy Text', 'wp-opengdpr' ); ?></h2>
<p><?php esc_html_e( 'Optional rich-text content displayed at the bottom of the Cookie Settings dialog. Supports links, formatting, tables, etc.', 'wp-opengdpr' ); ?></p>

<?php WPOG_Admin::form_open( 'cookie_policy' ); ?>
<div class="postbox" style="margin-top:0;">
    <div class="inside" style="padding-bottom:16px;">
        <?php
        $policy_content = get_option( 'wpog_cookie_policy', '' );
        wp_editor(
            $policy_content,
            'wpog_cookie_policy_editor',
            array(
                'textarea_name' => 'wpog_cookie_policy',
                'media_buttons' => false,
                'textarea_rows' => 10,
                'quicktags'     => true,
            )
        );
        ?>
    </div>
</div>
<?php WPOG_Admin::form_close(); ?>

