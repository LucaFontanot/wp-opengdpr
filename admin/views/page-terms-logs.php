<?php if ( ! defined( 'ABSPATH' ) ) { exit; }
WPOG_Admin::notice();

$from     = sanitize_text_field( $_GET['from'] ?? '' );
$to       = sanitize_text_field( $_GET['to'] ?? '' );
$dtype    = sanitize_text_field( $_GET['document_type'] ?? '' );
$version  = sanitize_text_field( $_GET['version'] ?? '' );
$order_id = isset( $_GET['order_id'] ) && '' !== $_GET['order_id'] ? (int) $_GET['order_id'] : '';
$page     = max( 1, (int) ( $_GET['paged'] ?? 1 ) );

$res   = WPOG_Terms_Consent_Logger::query( array(
    'from' => $from, 'to' => $to, 'document_type' => $dtype,
    'version' => $version, 'order_id' => $order_id, 'per_page' => 50, 'page' => $page,
) );
$stats    = WPOG_Terms_Consent_Logger::stats();
$versions = WPOG_Terms_Consent_Logger::versions();
$rows     = $res['rows'];
$total    = $res['total'];
$pages    = max( 1, ceil( $total / 50 ) );

$cur_hash = WPOG_Terms_Consent::terms_hash();
$max_day  = $stats['series'] ? max( $stats['series'] ) : 0;
$types    = array(
    'terms'     => __( 'Terms', 'wp-opengdpr' ),
    'privacy'   => __( 'Privacy', 'wp-opengdpr' ),
    'marketing' => __( 'Marketing', 'wp-opengdpr' ),
    'age'       => __( 'Age', 'wp-opengdpr' ),
);
?>
<h1><?php esc_html_e( 'Terms & Conditions — Acceptance Logs', 'wp-opengdpr' ); ?></h1>

<div class="wpog-stats" style="display:flex;gap:16px;margin:16px 0;flex-wrap:wrap;">
    <div class="wpog-stat"><strong><?php echo (int) $stats['total']; ?></strong><br><?php esc_html_e( 'Total records', 'wp-opengdpr' ); ?></div>
    <?php foreach ( $types as $k => $label ) : ?>
    <div class="wpog-stat"><strong><?php echo (int) $stats['by_type'][ $k ]; ?></strong><br><?php echo esc_html( $label ); ?></div>
    <?php endforeach; ?>
</div>

<div class="notice notice-info inline" style="margin:12px 0;"><p>
    <?php esc_html_e( 'Integrity check — current Terms page hash:', 'wp-opengdpr' ); ?>
    <code><?php echo esc_html( $cur_hash ?: __( '(no Terms page / empty)', 'wp-opengdpr' ) ); ?></code><br/>
    <?php esc_html_e( 'Terms rows whose stored hash differs from the current one are flagged below — the document was modified after acceptance.', 'wp-opengdpr' ); ?>
</p></div>

<?php if ( $max_day > 0 ) : ?>
<p><strong><?php esc_html_e( 'Last 30 days', 'wp-opengdpr' ); ?></strong></p>
<div style="display:flex;align-items:flex-end;gap:2px;height:60px;max-width:640px;margin-bottom:16px;">
    <?php
    for ( $i = 29; $i >= 0; $i-- ) {
        $d = gmdate( 'Y-m-d', strtotime( "-{$i} days", current_time( 'timestamp' ) ) );
        $c = isset( $stats['series'][ $d ] ) ? (int) $stats['series'][ $d ] : 0;
        $h = $max_day > 0 ? max( 2, (int) round( 56 * $c / $max_day ) ) : 2;
        echo '<div title="' . esc_attr( $d . ': ' . $c ) . '" style="flex:1;background:#0073aa;height:' . (int) $h . 'px;"></div>';
    }
    ?>
</div>
<?php endif; ?>

<form method="get" class="wpog-filters">
    <input type="hidden" name="page" value="wpog-terms-logs" />
    <label><?php esc_html_e( 'From', 'wp-opengdpr' ); ?> <input type="date" name="from" value="<?php echo esc_attr( $from ); ?>" /></label>
    <label><?php esc_html_e( 'To', 'wp-opengdpr' ); ?> <input type="date" name="to" value="<?php echo esc_attr( $to ); ?>" /></label>
    <label><?php esc_html_e( 'Type', 'wp-opengdpr' ); ?>
        <select name="document_type">
            <option value=""><?php esc_html_e( 'All', 'wp-opengdpr' ); ?></option>
            <?php foreach ( $types as $k => $label ) : ?>
                <option value="<?php echo esc_attr( $k ); ?>" <?php selected( $dtype, $k ); ?>><?php echo esc_html( $label ); ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label><?php esc_html_e( 'Version', 'wp-opengdpr' ); ?>
        <select name="version">
            <option value=""><?php esc_html_e( 'All', 'wp-opengdpr' ); ?></option>
            <?php foreach ( $versions as $v ) : ?>
                <option value="<?php echo esc_attr( $v ); ?>" <?php selected( $version, $v ); ?>><?php echo esc_html( $v ); ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label><?php esc_html_e( 'Order ID', 'wp-opengdpr' ); ?> <input type="number" name="order_id" value="<?php echo esc_attr( (string) $order_id ); ?>" style="width:90px;" /></label>
    <?php submit_button( __( 'Filter', 'wp-opengdpr' ), 'secondary', '', false ); ?>
    <a class="button" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'wpog_export_terms_logs', 'from' => $from, 'to' => $to, 'document_type' => $dtype, 'version' => $version, 'order_id' => $order_id ), admin_url( 'admin-post.php' ) ), WPOG_Admin::NONCE ) ); ?>"><?php esc_html_e( 'Export CSV', 'wp-opengdpr' ); ?></a>
</form>

<table class="widefat striped" style="margin-top:16px;">
    <thead><tr>
        <th><?php esc_html_e( 'Date', 'wp-opengdpr' ); ?></th>
        <th><?php esc_html_e( 'Order', 'wp-opengdpr' ); ?></th>
        <th><?php esc_html_e( 'User', 'wp-opengdpr' ); ?></th>
        <th><?php esc_html_e( 'Document', 'wp-opengdpr' ); ?></th>
        <th><?php esc_html_e( 'Version', 'wp-opengdpr' ); ?></th>
        <th><?php esc_html_e( 'Hash', 'wp-opengdpr' ); ?></th>
        <th><?php esc_html_e( 'Consent', 'wp-opengdpr' ); ?></th>
        <th><?php esc_html_e( 'IP', 'wp-opengdpr' ); ?></th>
        <th><?php esc_html_e( 'Context', 'wp-opengdpr' ); ?></th>
    </tr></thead>
    <tbody>
    <?php if ( empty( $rows ) ) : ?>
        <tr><td colspan="9"><?php esc_html_e( 'No acceptances recorded.', 'wp-opengdpr' ); ?></td></tr>
    <?php else : foreach ( $rows as $r ) :
        $order_link = '';
        if ( $r->order_id && function_exists( 'wc_get_order' ) ) {
            $o = wc_get_order( (int) $r->order_id );
            if ( $o ) { $order_link = $o->get_edit_order_url(); }
        }
        $user_label = __( 'Guest', 'wp-opengdpr' );
        if ( $r->user_id ) {
            $u = get_userdata( (int) $r->user_id );
            $user_label = $u ? ( $u->display_name . ' (' . $u->user_email . ')' ) : ( '#' . (int) $r->user_id );
        }
        $hash_mismatch = ( 'terms' === $r->document_type && $r->document_hash && $cur_hash && $r->document_hash !== $cur_hash );
    ?>
        <tr>
            <td><?php echo esc_html( $r->consent_date ); ?></td>
            <td><?php echo $order_link
                ? '<a href="' . esc_url( $order_link ) . '">#' . (int) $r->order_id . '</a>'
                : ( $r->order_id ? '#' . (int) $r->order_id : '—' ); ?></td>
            <td><?php echo esc_html( $user_label ); ?></td>
            <td><?php echo esc_html( $types[ $r->document_type ] ?? $r->document_type ); ?></td>
            <td><?php echo esc_html( $r->document_version ); ?></td>
            <td>
                <?php if ( $r->document_hash ) : ?>
                    <code title="<?php echo esc_attr( $r->document_hash ); ?>"><?php echo esc_html( substr( $r->document_hash, 0, 8 ) ); ?></code>
                    <?php if ( $hash_mismatch ) : ?>
                        <span style="color:#dc3232;" title="<?php esc_attr_e( 'Document changed after acceptance', 'wp-opengdpr' ); ?>">&#9888;</span>
                    <?php endif; ?>
                <?php else : ?>—<?php endif; ?>
            </td>
            <td><?php echo $r->consent_given ? '✓' : '✗'; ?></td>
            <td><?php echo esc_html( $r->ip_address ); ?></td>
            <td><?php echo esc_html( $r->context ); ?></td>
        </tr>
    <?php endforeach; endif; ?>
    </tbody>
</table>

<?php if ( $pages > 1 ) : ?>
<div class="tablenav"><div class="tablenav-pages">
    <?php echo paginate_links( array(
        'base'    => add_query_arg( 'paged', '%#%' ),
        'format'  => '',
        'current' => $page,
        'total'   => $pages,
    ) ); ?>
</div></div>
<?php endif; ?>

<hr/>
<form method="post" style="margin-top:24px;">
    <?php wp_nonce_field( WPOG_Admin::NONCE ); ?>
    <input type="hidden" name="wpog_form" value="terms_logs" />
    <button type="submit" name="wpog_purge" value="1" class="button button-secondary" onclick="return confirm('<?php echo esc_js( __( 'Delete acceptance logs older than the configured retention period?', 'wp-opengdpr' ) ); ?>');">
        <?php esc_html_e( 'Purge old logs now', 'wp-opengdpr' ); ?>
    </button>
</form>
