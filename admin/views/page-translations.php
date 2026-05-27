<?php if ( ! defined( 'ABSPATH' ) ) { exit; }
WPOG_Admin::notice();
$defaults  = WPOG_Settings::default_strings();
$overrides = get_option( 'wpog_translations', array() );
if ( ! is_array( $overrides ) ) { $overrides = array(); }

// banner_message gets its own rich-text editor section
$bm_value   = $overrides['banner_message'] ?? '';
$bm_default = $defaults['banner_message'];
unset( $defaults['banner_message'] );

$textareas = array( 'popup_necessary_desc', 'popup_functional_desc', 'popup_analytics_desc', 'popup_marketing_desc' );
?>
<h1><?php esc_html_e( 'Translations', 'wp-opengdpr' ); ?></h1>
<p><?php esc_html_e( 'Override any user-facing string. Leave empty to use the default English text (or its .po/.mo translation).', 'wp-opengdpr' ); ?></p>

<p>
    <a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=wpog_export_translations' ), WPOG_Admin::NONCE ) ); ?>"><?php esc_html_e( 'Export strings (JSON)', 'wp-opengdpr' ); ?></a>
</p>

<?php WPOG_Admin::form_open( 'translations' ); ?>

<div class="postbox wpog-banner-message-box">
    <h2 class="hndle" style="padding:8px 12px;"><?php esc_html_e( 'Banner Message', 'wp-opengdpr' ); ?></h2>
    <div class="inside">
        <p class="description"><?php esc_html_e( 'Main message shown in the cookie banner. HTML is supported — use the editor toolbar to add links, bold/italic text, etc.', 'wp-opengdpr' ); ?></p>
        <?php
        wp_editor(
            $bm_value,
            'wpog_banner_message_editor',
            array(
                'textarea_name' => 'wpog[translations][banner_message]',
                'media_buttons' => false,
                'teeny'         => true,
                'textarea_rows' => 5,
                'quicktags'     => true,
            )
        );
        ?>
        <p class="description" style="margin-top:6px;">
            <?php echo esc_html__( 'Default:', 'wp-opengdpr' ) . ' '; ?>
            <em><?php echo esc_html( $bm_default ); ?></em>
        </p>
    </div>
</div>

<h2 style="margin-top:20px;"><?php esc_html_e( 'Other Strings', 'wp-opengdpr' ); ?></h2>
<p><?php esc_html_e( 'Leave empty to use the default English text (or its .po/.mo translation).', 'wp-opengdpr' ); ?></p>

<table class="form-table">
    <?php foreach ( $defaults as $key => $default ) :
        $current = $overrides[ $key ] ?? '';
        $is_textarea = in_array( $key, $textareas, true );
    ?>
    <tr>
        <th><label for="wpog-tr-<?php echo esc_attr( $key ); ?>"><code><?php echo esc_html( $key ); ?></code></label></th>
        <td>
            <?php if ( $is_textarea ) : ?>
                <textarea id="wpog-tr-<?php echo esc_attr( $key ); ?>" class="large-text" rows="2" name="wpog[translations][<?php echo esc_attr( $key ); ?>]" placeholder="<?php echo esc_attr( $default ); ?>"><?php echo esc_textarea( $current ); ?></textarea>
            <?php else : ?>
                <input type="text" id="wpog-tr-<?php echo esc_attr( $key ); ?>" class="regular-text" name="wpog[translations][<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $current ); ?>" placeholder="<?php echo esc_attr( $default ); ?>" />
            <?php endif; ?>
            <button type="button" class="button button-small wpog-tr-reset" data-target="wpog-tr-<?php echo esc_attr( $key ); ?>"><?php esc_html_e( 'Reset', 'wp-opengdpr' ); ?></button>
        </td>
    </tr>
    <?php endforeach; ?>
</table>

<h2><?php esc_html_e( 'Import / Reset', 'wp-opengdpr' ); ?></h2>
<p>
    <label><?php esc_html_e( 'Import strings (JSON):', 'wp-opengdpr' ); ?>
        <input type="file" name="wpog_import" accept="application/json" />
    </label>
</p>
<p>
    <label><input type="checkbox" name="wpog_reset_all" value="1" /> <?php esc_html_e( 'Reset all strings to defaults', 'wp-opengdpr' ); ?></label>
</p>
<?php WPOG_Admin::form_close(); ?>
