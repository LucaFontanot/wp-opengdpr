<?php if ( ! defined( 'ABSPATH' ) ) { exit; }
WPOG_Admin::notice();

$from   = sanitize_text_field( $_GET['from'] ?? '' );
$to     = sanitize_text_field( $_GET['to'] ?? '' );
$action = sanitize_text_field( $_GET['filter_action'] ?? '' );
$page   = max( 1, (int) ( $_GET['paged'] ?? 1 ) );

$res    = WPOG_Logger::query( array( 'from' => $from, 'to' => $to, 'action' => $action, 'per_page' => 50, 'page' => $page ) );
$stats  = WPOG_Logger::stats();
$rows   = $res['rows'];
$total  = $res['total'];
$pages  = max( 1, ceil( $total / 50 ) );
?>
<h1><?php esc_html_e( 'Consent Logs', 'wp-opengdpr' ); ?></h1>

<div class="wpog-stats" style="display:flex;gap:16px;margin:16px 0;">
    <div class="wpog-stat"><strong><?php echo (int) $stats['total']; ?></strong><br><?php esc_html_e( 'Total', 'wp-opengdpr' ); ?></div>
    <div class="wpog-stat"><strong><?php echo (int) $stats['accept_all']; ?></strong><br><?php esc_html_e( 'Accept all', 'wp-opengdpr' ); ?></div>
    <div class="wpog-stat"><strong><?php echo (int) $stats['reject_all']; ?></strong><br><?php esc_html_e( 'Reject all', 'wp-opengdpr' ); ?></div>
    <div class="wpog-stat"><strong><?php echo (int) $stats['custom']; ?></strong><br><?php esc_html_e( 'Custom', 'wp-opengdpr' ); ?></div>
</div>

<?php if ( $stats['total'] > 0 ) :
    $aw = (int) round( 100 * $stats['accept_all'] / $stats['total'] );
    $rw = (int) round( 100 * $stats['reject_all'] / $stats['total'] );
    $cw = max( 0, 100 - $aw - $rw );
?>
<div style="display:flex;height:14px;border-radius:7px;overflow:hidden;max-width:600px;margin-bottom:16px;">
    <div title="Accept" style="background:#46b450;width:<?php echo $aw; ?>%"></div>
    <div title="Reject" style="background:#dc3232;width:<?php echo $rw; ?>%"></div>
    <div title="Custom" style="background:#ffb900;width:<?php echo $cw; ?>%"></div>
</div>
<?php endif; ?>

<form method="get" class="wpog-filters">
    <input type="hidden" name="page" value="wpog-logs" />
    <label><?php esc_html_e( 'From', 'wp-opengdpr' ); ?> <input type="date" name="from" value="<?php echo esc_attr( $from ); ?>" /></label>
    <label><?php esc_html_e( 'To', 'wp-opengdpr' ); ?> <input type="date" name="to" value="<?php echo esc_attr( $to ); ?>" /></label>
    <label><?php esc_html_e( 'Action', 'wp-opengdpr' ); ?>
        <select name="filter_action">
            <option value=""><?php esc_html_e( 'All', 'wp-opengdpr' ); ?></option>
            <?php foreach ( array( 'accept_all', 'reject_all', 'custom' ) as $a ) : ?>
                <option value="<?php echo esc_attr( $a ); ?>" <?php selected( $action, $a ); ?>><?php echo esc_html( $a ); ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <?php submit_button( __( 'Filter', 'wp-opengdpr' ), 'secondary', '', false ); ?>
    <a class="button" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action'=>'wpog_export_logs', 'from'=>$from, 'to'=>$to, 'filter_action'=>$action ), admin_url( 'admin-post.php' ) ), WPOG_Admin::NONCE ) ); ?>"><?php esc_html_e( 'Export CSV', 'wp-opengdpr' ); ?></a>
</form>

<table class="widefat striped" style="margin-top:16px;">
    <thead><tr>
        <th><?php esc_html_e( 'Consent ID', 'wp-opengdpr' ); ?></th>
        <th><?php esc_html_e( 'Date', 'wp-opengdpr' ); ?></th>
        <th><?php esc_html_e( 'IP', 'wp-opengdpr' ); ?></th>
        <th><?php esc_html_e( 'Action', 'wp-opengdpr' ); ?></th>
        <th>N</th><th>F</th><th>A</th><th>M</th>
        <th><?php esc_html_e( 'Policy version', 'wp-opengdpr' ); ?></th>
    </tr></thead>
    <tbody>
    <?php if ( empty( $rows ) ) : ?>
        <tr><td colspan="9"><?php esc_html_e( 'No consents recorded.', 'wp-opengdpr' ); ?></td></tr>
    <?php else : foreach ( $rows as $r ) : ?>
        <tr>
            <td><code><?php echo esc_html( $r->consent_id ); ?></code></td>
            <td><?php echo esc_html( $r->consent_date ); ?></td>
            <td><?php echo esc_html( $r->ip_address ); ?></td>
            <td><?php echo esc_html( $r->action ); ?></td>
            <td><?php echo $r->necessary ? '✓' : '✗'; ?></td>
            <td><?php echo $r->functional ? '✓' : '✗'; ?></td>
            <td><?php echo $r->analytics ? '✓' : '✗'; ?></td>
            <td><?php echo $r->marketing ? '✓' : '✗'; ?></td>
            <td><?php echo esc_html( $r->policy_version ); ?></td>
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
    <input type="hidden" name="wpog_form" value="logs" />
    <button type="submit" name="wpog_purge" value="1" class="button button-secondary" onclick="return confirm('<?php echo esc_js( __( 'Delete logs older than the configured retention period?', 'wp-opengdpr' ) ); ?>');">
        <?php esc_html_e( 'Purge old logs now', 'wp-opengdpr' ); ?>
    </button>
</form>
