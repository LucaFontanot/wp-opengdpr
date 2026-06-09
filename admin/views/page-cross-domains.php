<?php
/**
 * Cross-Domain Consent Bridge — manage authorized external domains that are
 * permitted to embed the consent script via:
 *   <script src="<?php echo esc_url( rest_url( 'wpog/v1/consent-bridge' ) ); ?>" async></script>
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
WPOG_Admin::notice();

$domains = WPOG_Cross_Domain::get_domains();
$bridge_url = rest_url( 'wpog/v1/consent-bridge' );
?>
<h1><?php esc_html_e( 'Cross-Domain Consent', 'wp-opengdpr' ); ?></h1>
<p><?php esc_html_e( 'Whitelist external domains that are allowed to embed the consent bridge. Each domain receives the identical banner, popup, styling, and blocking rules as your WordPress site.', 'wp-opengdpr' ); ?></p>

<div class="card" style="max-width:100%;padding:16px;margin-bottom:16px;background:#f9f9f9;">
    <p><strong><?php esc_html_e( 'Embed code', 'wp-opengdpr' ); ?></strong></p>
    <code style="display:block;padding:10px;background:#fff;border:1px solid #ddd;word-break:break-all;"><?php echo esc_html( '<script src="' . $bridge_url . '" async></script>' ); ?></code>
    <p class="description"><?php esc_html_e( 'Add this tag to the <head> or <body> of every external page that should show the consent banner. The script is self-contained — no other dependencies are needed.', 'wp-opengdpr' ); ?></p>
</div>

<?php WPOG_Admin::form_open( 'cross_domains' ); ?>
<table class="widefat wpog-cross-domains-table">
    <thead><tr>
        <th><?php esc_html_e( 'Domain', 'wp-opengdpr' ); ?></th>
        <th><?php esc_html_e( 'Active', 'wp-opengdpr' ); ?></th>
        <th></th>
    </tr></thead>
    <tbody id="wpog-cross-domains-body">
    <?php
    $rows = $domains;
    if ( empty( $rows ) ) {
        $rows = array( array( 'domain' => '', 'active' => 1 ) );
    }
    foreach ( $rows as $i => $row ) :
        $row = wp_parse_args( $row, array( 'domain' => '', 'active' => 1 ) );
    ?>
        <tr>
            <td><input type="text" name="wpog[cross_domains][<?php echo (int) $i; ?>][domain]"
                       value="<?php echo esc_attr( $row['domain'] ); ?>"
                       placeholder="example.com"
                       style="width:100%;max-width:400px;" /></td>
            <td><input type="checkbox" name="wpog[cross_domains][<?php echo (int) $i; ?>][active]"
                       value="1" <?php checked( ! empty( $row['active'] ) ); ?> /></td>
            <td><button type="button" class="button wpog-cross-domain-del">&times;</button></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p>
    <button type="button" class="button" id="wpog-cross-domain-add" data-index="<?php echo (int) count( $rows ); ?>">
        <?php esc_html_e( 'Add domain', 'wp-opengdpr' ); ?>
    </button>
</p>
<p class="description">
    <?php esc_html_e( 'Enter domain names without protocol (e.g. "example.com" or "*.example.com" for all subdomains). Only active domains with this exact hostname or www-prefix will be served the script.', 'wp-opengdpr' ); ?>
</p>
<?php WPOG_Admin::form_close(); ?>

<script>
(function(){
    var body = document.getElementById('wpog-cross-domains-body');
    var addBtn = document.getElementById('wpog-cross-domain-add');

    addBtn.addEventListener('click', function(){
        var idx = parseInt(addBtn.getAttribute('data-index')) || 0;
        var tr = document.createElement('tr');
        tr.innerHTML = '<td><input type="text" name="wpog[cross_domains]['+idx+'][domain]" placeholder="example.com" style="width:100%;max-width:400px;"></td>' +
            '<td><input type="checkbox" name="wpog[cross_domains]['+idx+'][active]" value="1" checked></td>' +
            '<td><button type="button" class="button wpog-cross-domain-del">&times;</button></td>';
        body.appendChild(tr);
        addBtn.setAttribute('data-index', idx + 1);
    });

    body.addEventListener('click', function(e){
        if (!e.target.classList.contains('wpog-cross-domain-del')) return;
        var tr = e.target.closest('tr');
        if (!tr) return;
        var rows = body.querySelectorAll('tr');
        if (rows.length <= 1) {
            // Clear the last row instead of removing it.
            var inputs = tr.querySelectorAll('input[type="text"]');
            if (inputs.length) inputs[0].value = '';
            var cb = tr.querySelector('input[type="checkbox"]');
            if (cb) cb.checked = true;
            return;
        }
        tr.parentNode.removeChild(tr);
        // Re-index remaining rows.
        body.querySelectorAll('tr').forEach(function(r, i){
            var inputs = r.querySelectorAll('input');
            inputs.forEach(function(inp){
                inp.name = inp.name.replace(/\[cross_domains\]\[\d+\]/, '[cross_domains]['+i+']');
            });
        });
        addBtn.setAttribute('data-index', rows.length - 1);
    });
})();
</script>
