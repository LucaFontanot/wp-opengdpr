<?php if ( ! defined( 'ABSPATH' ) ) { exit; }
WPOG_Admin::notice();

$from    = sanitize_text_field( $_GET['from'] ?? '' );
$to      = sanitize_text_field( $_GET['to'] ?? '' );
$form_id = sanitize_text_field( $_GET['form_id'] ?? '' );
$cg      = isset( $_GET['consent_given'] ) && '' !== $_GET['consent_given'] ? (int) $_GET['consent_given'] : '';
$page    = max( 1, (int) ( $_GET['paged'] ?? 1 ) );

$res   = WPOG_Form_Consent_Logger::query( array( 'from' => $from, 'to' => $to, 'form_id' => $form_id, 'consent_given' => $cg, 'per_page' => 50, 'page' => $page ) );
$stats = WPOG_Form_Consent_Logger::stats();
$forms = WPOG_Form_Consent_Logger::form_ids();
$rows  = $res['rows'];
$total = $res['total'];
$pages = max( 1, ceil( $total / 50 ) );

$mkt_enabled = (int) WPOG_Settings::get( 'form_consent', 'checkbox_marketing_enabled' );
$pct_given   = $stats['total'] > 0 ? round( 100 * $stats['given'] / $stats['total'] ) : 0;
$pct_mkt     = $stats['total'] > 0 ? round( 100 * $stats['marketing'] / $stats['total'] ) : 0;
$max_day     = $stats['series'] ? max( $stats['series'] ) : 0;
?>
<h1><?php esc_html_e( 'Form Consent Logs', 'wp-opengdpr' ); ?></h1>

<div class="wpog-stats" style="display:flex;gap:16px;margin:16px 0;">
    <div class="wpog-stat"><strong><?php echo (int) $stats['total']; ?></strong><br><?php esc_html_e( 'Total consents', 'wp-opengdpr' ); ?></div>
    <div class="wpog-stat"><strong><?php echo (int) $pct_given; ?>%</strong><br><?php esc_html_e( 'Main consent given', 'wp-opengdpr' ); ?></div>
    <div class="wpog-stat"><strong><?php echo (int) $pct_mkt; ?>%</strong><br><?php esc_html_e( 'Marketing consent given', 'wp-opengdpr' ); ?></div>
</div>

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
    <input type="hidden" name="page" value="wpog-form-logs" />
    <label><?php esc_html_e( 'From', 'wp-opengdpr' ); ?> <input type="date" name="from" value="<?php echo esc_attr( $from ); ?>" /></label>
    <label><?php esc_html_e( 'To', 'wp-opengdpr' ); ?> <input type="date" name="to" value="<?php echo esc_attr( $to ); ?>" /></label>
    <label><?php esc_html_e( 'Form', 'wp-opengdpr' ); ?>
        <select name="form_id">
            <option value=""><?php esc_html_e( 'All', 'wp-opengdpr' ); ?></option>
            <?php foreach ( $forms as $fid => $ftitle ) : ?>
                <option value="<?php echo esc_attr( $fid ); ?>" <?php selected( (string) $form_id, (string) $fid ); ?>><?php echo esc_html( ( $ftitle ?: $fid ) . ' (#' . $fid . ')' ); ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label><?php esc_html_e( 'Consent', 'wp-opengdpr' ); ?>
        <select name="consent_given">
            <option value="" <?php selected( $cg === '' ); ?>><?php esc_html_e( 'All', 'wp-opengdpr' ); ?></option>
            <option value="1" <?php selected( $cg === 1 ); ?>><?php esc_html_e( 'Given', 'wp-opengdpr' ); ?></option>
            <option value="0" <?php selected( $cg === 0 ); ?>><?php esc_html_e( 'Not given', 'wp-opengdpr' ); ?></option>
        </select>
    </label>
    <?php submit_button( __( 'Filter', 'wp-opengdpr' ), 'secondary', '', false ); ?>
    <a class="button" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'wpog_export_form_logs', 'from' => $from, 'to' => $to, 'form_id' => $form_id, 'consent_given' => $cg ), admin_url( 'admin-post.php' ) ), WPOG_Admin::NONCE ) ); ?>"><?php esc_html_e( 'Export CSV', 'wp-opengdpr' ); ?></a>
</form>

<table class="widefat striped" style="margin-top:16px;">
    <thead><tr>
        <th><?php esc_html_e( 'Date', 'wp-opengdpr' ); ?></th>
        <th><?php esc_html_e( 'Form', 'wp-opengdpr' ); ?></th>
        <th><?php esc_html_e( 'Page URL', 'wp-opengdpr' ); ?></th>
        <th><?php esc_html_e( 'Main consent', 'wp-opengdpr' ); ?></th>
        <th><?php esc_html_e( 'Marketing', 'wp-opengdpr' ); ?></th>
        <th><?php esc_html_e( 'IP (anonymised)', 'wp-opengdpr' ); ?></th>
        <th><?php esc_html_e( 'Policy version', 'wp-opengdpr' ); ?></th>
    </tr></thead>
    <tbody>
    <?php if ( empty( $rows ) ) : ?>
        <tr><td colspan="7"><?php esc_html_e( 'No form consents recorded.', 'wp-opengdpr' ); ?></td></tr>
    <?php else : foreach ( $rows as $r ) :
        $edit_link = ( 'cf7' === $r->form_type && $r->form_id ) ? admin_url( 'admin.php?page=wpcf7&post=' . (int) $r->form_id . '&action=edit' ) : '';
    ?>
        <tr>
            <td><?php echo esc_html( $r->consent_date ); ?></td>
            <td>
                <?php if ( $edit_link ) : ?>
                    <a href="<?php echo esc_url( $edit_link ); ?>"><?php echo esc_html( $r->form_title ?: ( '#' . $r->form_id ) ); ?></a>
                <?php else : ?>
                    <?php echo esc_html( $r->form_title ?: ( '#' . $r->form_id ) ); ?>
                <?php endif; ?>
                <br/><small><?php echo esc_html( $r->form_type ); ?></small>
            </td>
            <td><?php echo $r->page_url ? '<a href="' . esc_url( $r->page_url ) . '" target="_blank" rel="noopener">' . esc_html( $r->page_url ) . '</a>' : '—'; ?></td>
            <td><?php echo $r->consent_given ? '✓' : '✗'; ?></td>
            <td><?php echo $mkt_enabled ? ( $r->marketing_consent ? '✓' : '✗' ) : '—'; ?></td>
            <td><?php echo esc_html( $r->ip_address ); ?></td>
            <td><?php echo esc_html( $r->privacy_version ); ?></td>
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
    <input type="hidden" name="wpog_form" value="form_logs" />
    <button type="submit" name="wpog_purge" value="1" class="button button-secondary" onclick="return confirm('<?php echo esc_js( __( 'Delete form consent logs older than the configured retention period?', 'wp-opengdpr' ) ); ?>');">
        <?php esc_html_e( 'Purge old logs now', 'wp-opengdpr' ); ?>
    </button>
</form>
