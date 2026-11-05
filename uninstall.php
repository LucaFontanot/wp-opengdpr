<?php
/**
 * Uninstall handler — removes every option, table, user-meta key, cron event
 * and transient created by the plugin.
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
    'wpog_blocker_rules',
    'wpog_cookie_policy',
    'wpog_form_consent',
    'wpog_terms_consent',
    'wpog_terms_versions',
    'wpog_cross_domains',
    'wpog_cookie_db_version',
    'wpog_cookie_db_last_fetch',
);

foreach ( $options as $opt ) {
    delete_option( $opt );
}

$tables = array(
    $wpdb->prefix . 'wpog_consent_log',
    $wpdb->prefix . 'wpog_detections',
    $wpdb->prefix . 'wpog_form_consent_log',
    $wpdb->prefix . 'wpog_terms_consent_log',
    $wpdb->prefix . 'wpog_legal_prompts',
    $wpdb->prefix . 'wpog_open_cookie_db',
);
foreach ( $tables as $table ) {
    $wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

// Consent user meta written at registration / checkout.
foreach ( array( '_wpog_consent_privacy', '_wpog_consent_marketing', '_wpog_consent_date', '_wpog_consent_ip' ) as $meta_key ) {
    delete_metadata( 'user', 0, $meta_key, '', true );
}

// Scheduled events.
wp_clear_scheduled_hook( 'wpog_daily_event' );
wp_clear_scheduled_hook( 'wpog_monthly_cookie_db_refresh' );

// Transients (bridge JS cache, rate-limit counters).
$wpdb->query(
    "DELETE FROM {$wpdb->options}
     WHERE option_name LIKE '\_transient\_wpog\_%'
        OR option_name LIKE '\_transient\_timeout\_wpog\_%'"
);
