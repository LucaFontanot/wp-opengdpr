<?php if ( ! defined( 'ABSPATH' ) ) { exit; }
WPOG_Admin::notice();
$scripts = WPOG_Settings::get( 'scripts' );
if ( ! is_array( $scripts ) ) { $scripts = array(); }
?>
<h1><?php esc_html_e( 'Script Manager', 'wp-opengdpr' ); ?></h1>
<p><?php esc_html_e( 'Register third-party scripts here. They will be blocked until the visitor consents to the chosen category.', 'wp-opengdpr' ); ?></p>
<?php WPOG_Admin::form_open( 'scripts' ); ?>
<table class="widefat wpog-scripts-table">
    <thead><tr>
        <th><?php esc_html_e( 'Name', 'wp-opengdpr' ); ?></th>
        <th><?php esc_html_e( 'Category', 'wp-opengdpr' ); ?></th>
        <th><?php esc_html_e( 'Type', 'wp-opengdpr' ); ?></th>
        <th><?php esc_html_e( 'Position', 'wp-opengdpr' ); ?></th>
        <th><?php esc_html_e( 'Content / URL', 'wp-opengdpr' ); ?></th>
        <th><?php esc_html_e( 'Active', 'wp-opengdpr' ); ?></th>
        <th></th>
    </tr></thead>
    <tbody id="wpog-scripts-body">
    <?php
    $rows = $scripts;
    if ( empty( $rows ) ) { $rows = array( array( 'name' => '', 'category' => 'analytics', 'type' => 'inline', 'position' => 'head', 'content' => '', 'active' => 1 ) ); }
    foreach ( $rows as $i => $row ) :
        $row = wp_parse_args( $row, array( 'name'=>'', 'category'=>'analytics', 'type'=>'inline', 'position'=>'head', 'content'=>'', 'active'=>1 ) );
    ?>
        <tr>
            <td><input type="text" name="wpog[scripts][<?php echo $i; ?>][name]" value="<?php echo esc_attr( $row['name'] ); ?>" /></td>
            <td>
                <select name="wpog[scripts][<?php echo $i; ?>][category]">
                    <?php foreach ( array( 'necessary','functional','analytics','marketing' ) as $c ) : ?>
                        <option value="<?php echo esc_attr( $c ); ?>" <?php selected( $row['category'], $c ); ?>><?php echo esc_html( $c ); ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td>
                <select name="wpog[scripts][<?php echo $i; ?>][type]">
                    <?php foreach ( array( 'inline'=>'Inline','src'=>'External src','iframe'=>'Iframe' ) as $v=>$l ) : ?>
                        <option value="<?php echo esc_attr( $v ); ?>" <?php selected( $row['type'], $v ); ?>><?php echo esc_html( $l ); ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td>
                <select name="wpog[scripts][<?php echo $i; ?>][position]">
                    <?php foreach ( array( 'head','body-top','body-bottom' ) as $p ) : ?>
                        <option value="<?php echo esc_attr( $p ); ?>" <?php selected( $row['position'], $p ); ?>><?php echo esc_html( $p ); ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td><textarea rows="3" class="large-text code" name="wpog[scripts][<?php echo $i; ?>][content]"><?php echo esc_textarea( $row['content'] ); ?></textarea></td>
            <td><input type="checkbox" name="wpog[scripts][<?php echo $i; ?>][active]" value="1" <?php checked( $row['active'] ); ?> /></td>
            <td><button type="button" class="button wpog-script-del">&times;</button></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p><button type="button" class="button" id="wpog-script-add" data-index="<?php echo (int) count( $rows ); ?>"><?php esc_html_e( 'Add script', 'wp-opengdpr' ); ?></button></p>
<?php WPOG_Admin::form_close(); ?>
