<?php
/**
 * Uninstall handler — removes options and consent log table.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

$options = array(
    'wpog_general',
    'wpog_banner',
    'wpog_popup',
    'wpog_categories',
    'wpog_scripts',
    'wpog_translations',
    'wpog_db_version',
);

foreach ( $options as $opt ) {
    delete_option( $opt );
}

$table = $wpdb->prefix . 'wpog_consent_log';
$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
