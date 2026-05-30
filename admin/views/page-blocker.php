<?php
/**
 * Domain Blocker — list of third-party domains that the JS autoblocker
 * intercepts on page load until the visitor consents to the linked category.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
WPOG_Admin::notice();

$rules = WPOG_Domain_Blocker::all();
$cats  = array( 'necessary', 'functional', 'analytics', 'marketing' );
?>
<h1><?php esc_html_e( 'Domain Blocker', 'wp-opengdpr' ); ?></h1>
<p><?php esc_html_e( 'List third-party domains here. Until the visitor consents to the chosen category, every <script>, <iframe>, <img> or <link> whose URL matches a rule in this list will be neutered client-side and will not make any network request.', 'wp-opengdpr' ); ?></p>
<p><?php esc_html_e( 'Domain matching is suffix-based (e.g. "google-analytics.com" matches "www.google-analytics.com"). Add an optional path prefix to target specific scripts on shared domains (e.g. domain "google.com" + path "/maps/api").', 'wp-opengdpr' ); ?></p>

<?php WPOG_Admin::form_open( 'blocker' ); ?>
<table class="widefat wpog-blocker-table">
    <thead><tr>
        <th><?php esc_html_e( 'Domain', 'wp-opengdpr' ); ?></th>
        <th><?php esc_html_e( 'Path (optional)', 'wp-opengdpr' ); ?></th>
        <th><?php esc_html_e( 'Category', 'wp-opengdpr' ); ?></th>
        <th><?php esc_html_e( 'Note / vendor', 'wp-opengdpr' ); ?></th>
        <th><?php esc_html_e( 'Active', 'wp-opengdpr' ); ?></th>
        <th></th>
    </tr></thead>
    <tbody id="wpog-blocker-body">
    <?php
    $rows = $rules;
    if ( empty( $rows ) ) {
        $rows = array( array( 'domain' => '', 'path' => '', 'category' => 'analytics', 'note' => '', 'active' => 1 ) );
    }
    foreach ( $rows as $i => $row ) :
        $row = wp_parse_args( $row, array( 'domain' => '', 'path' => '', 'category' => 'analytics', 'note' => '', 'active' => 1 ) );
    ?>
        <tr>
            <td><input type="text" name="wpog[rules][<?php echo $i; ?>][domain]" value="<?php echo esc_attr( $row['domain'] ); ?>" placeholder="example.com" /></td>
            <td><input type="text" name="wpog[rules][<?php echo $i; ?>][path]" value="<?php echo esc_attr( $row['path'] ); ?>" placeholder="/path/to/script" style="width:140px;" /></td>
            <td>
                <select name="wpog[rules][<?php echo $i; ?>][category]">
                    <?php foreach ( $cats as $c ) : ?>
                        <option value="<?php echo esc_attr( $c ); ?>" <?php selected( $row['category'], $c ); ?>><?php echo esc_html( ucfirst( $c ) ); ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td><input type="text" name="wpog[rules][<?php echo $i; ?>][note]" value="<?php echo esc_attr( $row['note'] ); ?>" placeholder="Vendor / description" /></td>
            <td><input type="checkbox" name="wpog[rules][<?php echo $i; ?>][active]" value="1" <?php checked( ! empty( $row['active'] ) ); ?> /></td>
            <td><button type="button" class="button wpog-blocker-del">&times;</button></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p><button type="button" class="button" id="wpog-blocker-add" data-index="<?php echo (int) count( $rows ); ?>"><?php esc_html_e( 'Add domain', 'wp-opengdpr' ); ?></button></p>
<?php WPOG_Admin::form_close(); ?>
