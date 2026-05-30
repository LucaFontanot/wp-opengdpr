<?php
/**
 * Detections — review third-party assets and cookies seen by admin
 * sessions while browsing the front-end.
 *
 * Actions by type:
 *   cookie → "Add to Category" dialog (pre-filled name; user provides category, provider, duration)
 *   script → "Add to Blocker" dialog (pre-filled domain + path; user provides category)
 *   other  → no action buttons
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
WPOG_Admin::notice();

$status   = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '';
$type     = isset( $_GET['type'] )   ? sanitize_key( $_GET['type'] )   : '';
$search   = isset( $_GET['s'] )      ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$page_num = max( 1, (int) ( $_GET['paged'] ?? 1 ) );

$result = WPOG_Tracking::query( array(
    'status'   => $status,
    'type'     => $type,
    'search'   => $search,
    'page'     => $page_num,
    'per_page' => 50,
) );
$stats  = WPOG_Tracking::stats();
$cats   = array( 'necessary', 'functional', 'analytics', 'marketing' );
$nonce  = wp_create_nonce( WPOG_Admin::NONCE );
?>
<h1><?php esc_html_e( 'Detections', 'wp-opengdpr' ); ?></h1>
<p><?php esc_html_e( 'Third-party scripts and cookies encountered while an administrator browses the front-end. Use the action buttons to add cookies to a consent category or block scripts by domain and path.', 'wp-opengdpr' ); ?></p>

<p>
    <strong><?php esc_html_e( 'Total', 'wp-opengdpr' ); ?>:</strong> <?php echo (int) $stats['total']; ?>
    &nbsp;|&nbsp; <strong><?php esc_html_e( 'New', 'wp-opengdpr' ); ?>:</strong> <?php echo (int) $stats['new']; ?>
    &nbsp;|&nbsp; <strong><?php esc_html_e( 'Blocked', 'wp-opengdpr' ); ?>:</strong> <?php echo (int) $stats['blocked']; ?>
    &nbsp;|&nbsp; <strong><?php esc_html_e( 'Allowed', 'wp-opengdpr' ); ?>:</strong> <?php echo (int) $stats['allowed']; ?>
    &nbsp;|&nbsp; <strong><?php esc_html_e( 'Ignored', 'wp-opengdpr' ); ?>:</strong> <?php echo (int) $stats['ignored']; ?>
</p>

<form method="get" style="margin-bottom:10px;">
    <input type="hidden" name="page" value="wpog-tracking" />
    <label><?php esc_html_e( 'Status', 'wp-opengdpr' ); ?>:
        <select name="status">
            <option value=""><?php esc_html_e( 'All', 'wp-opengdpr' ); ?></option>
            <?php foreach ( array( 'new', 'blocked', 'allowed', 'ignored' ) as $s ) : ?>
                <option value="<?php echo esc_attr( $s ); ?>" <?php selected( $status, $s ); ?>><?php echo esc_html( ucfirst( $s ) ); ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label><?php esc_html_e( 'Type', 'wp-opengdpr' ); ?>:
        <select name="type">
            <option value=""><?php esc_html_e( 'All', 'wp-opengdpr' ); ?></option>
            <?php foreach ( array( 'script', 'iframe', 'img', 'link', 'cookie' ) as $t ) : ?>
                <option value="<?php echo esc_attr( $t ); ?>" <?php selected( $type, $t ); ?>><?php echo esc_html( $t ); ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search domain or URL', 'wp-opengdpr' ); ?>" />
    <button type="submit" class="button"><?php esc_html_e( 'Filter', 'wp-opengdpr' ); ?></button>
</form>

<table class="widefat striped">
    <thead><tr>
        <th><?php esc_html_e( 'Type', 'wp-opengdpr' ); ?></th>
        <th><?php esc_html_e( 'Domain', 'wp-opengdpr' ); ?></th>
        <th><?php esc_html_e( 'Value', 'wp-opengdpr' ); ?></th>
        <th><?php esc_html_e( 'Hits', 'wp-opengdpr' ); ?></th>
        <th><?php esc_html_e( 'Last seen', 'wp-opengdpr' ); ?></th>
        <th><?php esc_html_e( 'Status', 'wp-opengdpr' ); ?></th>
        <th><?php esc_html_e( 'Actions', 'wp-opengdpr' ); ?></th>
    </tr></thead>
    <tbody>
    <?php if ( empty( $result['rows'] ) ) : ?>
        <tr><td colspan="7"><?php esc_html_e( 'No detections yet — browse the front-end as an administrator to populate this list.', 'wp-opengdpr' ); ?></td></tr>
    <?php endif; ?>
    <?php foreach ( (array) $result['rows'] as $row ) :
        // Pre-compute path for script rows.
        $script_path = '';
        if ( 'script' === $row->type && ! empty( $row->value ) ) {
            $p = wp_parse_url( $row->value, PHP_URL_PATH );
            $script_path = $p ?: '';
        }
    ?>
        <tr>
            <td><code><?php echo esc_html( $row->type ); ?></code></td>
            <td><?php echo esc_html( $row->domain ); ?></td>
            <td style="word-break:break-all;max-width:340px;" title="<?php echo esc_attr( $row->value ); ?>"><?php echo esc_html( $row->value ); ?></td>
            <td><?php echo (int) $row->hits; ?></td>
            <td><?php echo esc_html( $row->last_seen ); ?></td>
            <td><span class="wpog-status wpog-status-<?php echo esc_attr( $row->status ); ?>"><?php echo esc_html( $row->status ); ?></span></td>
            <td>
                <?php if ( 'cookie' === $row->type ) : ?>
                    <button type="button" class="button button-primary wpog-open-cookie-dlg"
                        data-id="<?php echo (int) $row->id; ?>"
                        data-name="<?php echo esc_attr( $row->value ); ?>"
                        data-nonce="<?php echo esc_attr( $nonce ); ?>"
                    ><?php esc_html_e( 'Add to Category', 'wp-opengdpr' ); ?></button>

                <?php elseif ( 'script' === $row->type ) : ?>
                    <button type="button" class="button button-primary wpog-open-blocker-dlg"
                        data-id="<?php echo (int) $row->id; ?>"
                        data-domain="<?php echo esc_attr( $row->domain ); ?>"
                        data-path="<?php echo esc_attr( $script_path ); ?>"
                        data-note="<?php echo esc_attr( $row->value ); ?>"
                        data-nonce="<?php echo esc_attr( $nonce ); ?>"
                    ><?php esc_html_e( 'Block', 'wp-opengdpr' ); ?></button>

                <?php endif; // other types get no action buttons ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<?php
$total_pages = max( 1, (int) ceil( $result['total'] / 50 ) );
if ( $total_pages > 1 ) :
    echo '<p>';
    echo paginate_links( array(
        'base'    => add_query_arg( 'paged', '%#%' ),
        'format'  => '',
        'current' => $page_num,
        'total'   => $total_pages,
    ) );
    echo '</p>';
endif;
?>

<hr/>
<form method="post" onsubmit="return confirm('<?php echo esc_js( __( 'Delete ALL detections? This cannot be undone.', 'wp-opengdpr' ) ); ?>');">
    <?php wp_nonce_field( WPOG_Admin::NONCE ); ?>
    <input type="hidden" name="wpog_form"   value="tracking" />
    <button type="submit" name="wpog_action" value="delete_all" class="button"><?php esc_html_e( 'Clear all detections', 'wp-opengdpr' ); ?></button>
</form>

<!-- ===== Cookie → Category dialog ===== -->
<div id="wpog-dlg-cookie" class="wpog-dlg-overlay" style="display:none;">
    <div class="wpog-dlg" role="dialog" aria-modal="true" aria-labelledby="wpog-dlg-cookie-title">
        <h2 id="wpog-dlg-cookie-title"><?php esc_html_e( 'Add Cookie to Category', 'wp-opengdpr' ); ?></h2>
        <form method="post">
            <?php wp_nonce_field( WPOG_Admin::NONCE ); ?>
            <input type="hidden" name="wpog_form"         value="add_cookie" />
            <input type="hidden" name="wpog_detection_id" id="wpog-dlg-cookie-id" value="" />
            <table class="form-table" style="margin:0;">
                <tr>
                    <th scope="row"><label for="wpog-dlg-cookie-name"><?php esc_html_e( 'Cookie name', 'wp-opengdpr' ); ?></label></th>
                    <td><input type="text" id="wpog-dlg-cookie-name" name="wpog_cookie_name" class="regular-text" required /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="wpog-dlg-cookie-cat"><?php esc_html_e( 'Category', 'wp-opengdpr' ); ?></label></th>
                    <td>
                        <select id="wpog-dlg-cookie-cat" name="wpog_cookie_category" required>
                            <?php foreach ( $cats as $c ) : ?>
                                <option value="<?php echo esc_attr( $c ); ?>"><?php echo esc_html( ucfirst( $c ) ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="wpog-dlg-cookie-provider"><?php esc_html_e( 'Provider', 'wp-opengdpr' ); ?></label></th>
                    <td><input type="text" id="wpog-dlg-cookie-provider" name="wpog_cookie_provider" class="regular-text" required /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="wpog-dlg-cookie-duration"><?php esc_html_e( 'Duration / scope', 'wp-opengdpr' ); ?></label></th>
                    <td><input type="text" id="wpog-dlg-cookie-duration" name="wpog_cookie_duration" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. 1 year, session', 'wp-opengdpr' ); ?>" required /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="wpog-dlg-cookie-purpose"><?php esc_html_e( 'Purpose', 'wp-opengdpr' ); ?></label></th>
                    <td><input type="text" id="wpog-dlg-cookie-purpose" name="wpog_cookie_purpose" class="regular-text" placeholder="<?php esc_attr_e( 'Optional description', 'wp-opengdpr' ); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="wpog-dlg-cookie-privacy"><?php esc_html_e( 'Privacy URL', 'wp-opengdpr' ); ?></label></th>
                    <td><input type="url" id="wpog-dlg-cookie-privacy" name="wpog_cookie_privacy" class="regular-text" placeholder="https://" /></td>
                </tr>
            </table>
            <div class="wpog-dlg-footer">
                <button type="button" class="button wpog-dlg-close"><?php esc_html_e( 'Cancel', 'wp-opengdpr' ); ?></button>
                <button type="submit" class="button button-primary"><?php esc_html_e( 'Save cookie', 'wp-opengdpr' ); ?></button>
            </div>
        </form>
    </div>
</div>

<!-- ===== Script → Domain Blocker dialog ===== -->
<div id="wpog-dlg-blocker" class="wpog-dlg-overlay" style="display:none;">
    <div class="wpog-dlg" role="dialog" aria-modal="true" aria-labelledby="wpog-dlg-blocker-title">
        <h2 id="wpog-dlg-blocker-title"><?php esc_html_e( 'Add to Domain Blocker', 'wp-opengdpr' ); ?></h2>
        <p class="description"><?php esc_html_e( 'The path is optional. Leave it empty to block the entire domain. Fill it in to block only a specific path on that domain (e.g. /analytics.js).', 'wp-opengdpr' ); ?></p>
        <form method="post">
            <?php wp_nonce_field( WPOG_Admin::NONCE ); ?>
            <input type="hidden" name="wpog_form"         value="add_to_blocker" />
            <input type="hidden" name="wpog_detection_id" id="wpog-dlg-blocker-id" value="" />
            <table class="form-table" style="margin:0;">
                <tr>
                    <th scope="row"><label for="wpog-dlg-blocker-domain"><?php esc_html_e( 'Domain', 'wp-opengdpr' ); ?></label></th>
                    <td><input type="text" id="wpog-dlg-blocker-domain" name="wpog_block_domain" class="regular-text" placeholder="example.com" required /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="wpog-dlg-blocker-path"><?php esc_html_e( 'Path (optional)', 'wp-opengdpr' ); ?></label></th>
                    <td><input type="text" id="wpog-dlg-blocker-path" name="wpog_block_path" class="regular-text" placeholder="/path/to/script.js" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="wpog-dlg-blocker-cat"><?php esc_html_e( 'Category', 'wp-opengdpr' ); ?></label></th>
                    <td>
                        <select id="wpog-dlg-blocker-cat" name="wpog_block_category" required>
                            <?php foreach ( $cats as $c ) : ?>
                                <option value="<?php echo esc_attr( $c ); ?>" <?php selected( $c, 'marketing' ); ?>><?php echo esc_html( ucfirst( $c ) ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="wpog-dlg-blocker-note"><?php esc_html_e( 'Note / vendor', 'wp-opengdpr' ); ?></label></th>
                    <td><input type="text" id="wpog-dlg-blocker-note" name="wpog_block_note" class="regular-text" /></td>
                </tr>
            </table>
            <div class="wpog-dlg-footer">
                <button type="button" class="button wpog-dlg-close"><?php esc_html_e( 'Cancel', 'wp-opengdpr' ); ?></button>
                <button type="submit" class="button button-primary"><?php esc_html_e( 'Block script', 'wp-opengdpr' ); ?></button>
            </div>
        </form>
    </div>
</div>
