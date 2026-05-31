<?php
/**
 * Legal Document Prompt Generator — shell page.
 *
 * Renders the mount node for the vanilla-JS wizard (admin/assets/legal-generator.js).
 * All wizard logic, validation and prompt assembly run client-side; the only
 * server interaction is saving/loading prompts through WPOG_Legal_REST.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<div id="wpog-legal-generator" class="wpog-legal">
	<noscript>
		<div class="notice notice-error"><p>
			<?php esc_html_e( 'The Legal Document Prompt Generator requires JavaScript to be enabled.', 'wp-opengdpr' ); ?>
		</p></div>
	</noscript>
	<p class="wpog-legal-loading"><?php esc_html_e( 'Loading the Legal Document Prompt Generator…', 'wp-opengdpr' ); ?></p>
</div>
