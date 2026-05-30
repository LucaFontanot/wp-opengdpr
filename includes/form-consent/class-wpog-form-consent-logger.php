<?php
/**
 * Form consent log — DB table management, inserts and queries for the
 * privacy-consent-on-forms module.
 *
 * Storage: {prefix}wpog_form_consent_log. Each row is the audit-trail proof of
 * one form submission consent (GDPR Art. 7): timestamp, anonymised IP, the
 * exact checkbox text shown to the user, the privacy policy version, the form
 * it belonged to and which checkboxes were ticked.
 *
 * IMPORTANT: the IP is always anonymised before storage and the form payload
 * (name, email, message, ...) is NEVER recorded here — only the consent itself.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPOG_Form_Consent_Logger {

    public static function table_name() {
        global $wpdb;
        return $wpdb->prefix . 'wpog_form_consent_log';
    }

    public static function install_table() {
        global $wpdb;
        $table           = self::table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            consent_id VARCHAR(64) NOT NULL,
            form_id VARCHAR(100) DEFAULT '',
            form_type VARCHAR(50) DEFAULT '',
            form_title VARCHAR(255) DEFAULT '',
            page_url VARCHAR(500) DEFAULT '',
            consent_given TINYINT(1) DEFAULT 0,
            marketing_consent TINYINT(1) DEFAULT 0,
            consent_text TEXT,
            privacy_version VARCHAR(20) DEFAULT '',
            ip_address VARCHAR(45) DEFAULT '',
            user_agent VARCHAR(500) DEFAULT '',
            consent_date DATETIME NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            KEY consent_id (consent_id),
            KEY form_id (form_id),
            KEY consent_date (consent_date)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Lazily create the table the first time it is needed in a process. Mirrors
     * WPOG_Tracking::ensure_table().
     */
    public static function ensure_table() {
        static $checked = false;
        if ( $checked ) {
            return;
        }
        global $wpdb;
        $table    = self::table_name();
        $suppress = $wpdb->suppress_errors( true );
        $exists   = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
        $wpdb->suppress_errors( $suppress );
        if ( $exists !== $table ) {
            self::install_table();
        }
        $checked = true;
    }

    /**
     * Insert one consent record.
     *
     * @param array $data {
     *   form_id, form_type, form_title, page_url,
     *   consent_given, marketing_consent, consent_text, privacy_version
     * }
     * @return int|false Inserted row id or false on failure.
     */
    public static function log( array $data ) {
        global $wpdb;

        self::ensure_table();

        // IP is always anonymised — not configurable, for GDPR compliance.
        $ip = WPOG_Logger::anonymize_ip( WPOG_Logger::get_client_ip() );

        $ua = isset( $_SERVER['HTTP_USER_AGENT'] )
            ? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 500 )
            : '';

        $row = array(
            'consent_id'        => substr( wp_generate_uuid4(), 0, 64 ),
            'form_id'           => substr( sanitize_text_field( (string) ( $data['form_id'] ?? '' ) ), 0, 100 ),
            'form_type'         => substr( sanitize_text_field( (string) ( $data['form_type'] ?? 'generic' ) ), 0, 50 ),
            'form_title'        => substr( sanitize_text_field( (string) ( $data['form_title'] ?? '' ) ), 0, 255 ),
            'page_url'          => substr( esc_url_raw( (string) ( $data['page_url'] ?? '' ) ), 0, 500 ),
            'consent_given'     => ! empty( $data['consent_given'] ) ? 1 : 0,
            'marketing_consent' => ! empty( $data['marketing_consent'] ) ? 1 : 0,
            'consent_text'      => wp_kses_post( (string) ( $data['consent_text'] ?? '' ) ),
            'privacy_version'   => substr( sanitize_text_field( (string) ( $data['privacy_version'] ?? '' ) ), 0, 20 ),
            'ip_address'        => $ip,
            'user_agent'        => $ua,
            'consent_date'      => current_time( 'mysql' ),
        );

        $ok = $wpdb->insert(
            self::table_name(),
            $row,
            array( '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
        );

        return false === $ok ? false : (int) $wpdb->insert_id;
    }

    /* ---------- query ---------- */

    public static function query( $args = array() ) {
        global $wpdb;
        self::ensure_table();

        $defaults = array(
            'from'          => '',
            'to'            => '',
            'form_id'       => '',
            'consent_given' => '',
            'per_page'      => 50,
            'page'          => 1,
        );
        $args  = array_merge( $defaults, $args );
        $table = self::table_name();

        $where  = array( '1=1' );
        $params = array();

        if ( ! empty( $args['from'] ) ) {
            $where[]  = 'consent_date >= %s';
            $params[] = $args['from'] . ' 00:00:00';
        }
        if ( ! empty( $args['to'] ) ) {
            $where[]  = 'consent_date <= %s';
            $params[] = $args['to'] . ' 23:59:59';
        }
        if ( '' !== $args['form_id'] ) {
            $where[]  = 'form_id = %s';
            $params[] = (string) $args['form_id'];
        }
        if ( '' !== $args['consent_given'] && in_array( (int) $args['consent_given'], array( 0, 1 ), true ) ) {
            $where[]  = 'consent_given = %d';
            $params[] = (int) $args['consent_given'];
        }
        $where_sql = implode( ' AND ', $where );

        $per_page = max( 1, (int) $args['per_page'] );
        $page     = max( 1, (int) $args['page'] );
        $offset   = ( $page - 1 ) * $per_page;

        $count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
        $total     = (int) $wpdb->get_var( $params ? $wpdb->prepare( $count_sql, $params ) : $count_sql );

        $rows_sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY consent_date DESC LIMIT %d OFFSET %d";
        $rows     = $wpdb->get_results( $wpdb->prepare( $rows_sql, array_merge( $params, array( $per_page, $offset ) ) ) );

        return array( 'rows' => $rows, 'total' => $total );
    }

    /**
     * Distinct (form_id => form_title) pairs present in the log, for filters.
     */
    public static function form_ids() {
        global $wpdb;
        self::ensure_table();
        $table = self::table_name();
        $rows  = $wpdb->get_results( "SELECT form_id, MAX(form_title) AS form_title FROM {$table} WHERE form_id <> '' GROUP BY form_id ORDER BY form_title ASC" );
        $out   = array();
        foreach ( (array) $rows as $r ) {
            $out[ $r->form_id ] = $r->form_title;
        }
        return $out;
    }

    public static function stats() {
        global $wpdb;
        self::ensure_table();
        $table = self::table_name();

        $total     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
        $given     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE consent_given = 1" );
        $marketing = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE marketing_consent = 1" );

        // Last 30 days, counts per day (for the simple bar chart).
        $series = array();
        $rows   = $wpdb->get_results(
            "SELECT DATE(consent_date) AS d, COUNT(*) AS c
             FROM {$table}
             WHERE consent_date >= (NOW() - INTERVAL 30 DAY)
             GROUP BY DATE(consent_date)"
        );
        foreach ( (array) $rows as $r ) {
            $series[ $r->d ] = (int) $r->c;
        }

        return array(
            'total'     => $total,
            'given'     => $given,
            'marketing' => $marketing,
            'series'    => $series,
        );
    }

    /**
     * Delete records older than the configured retention period. Hooked onto
     * the existing wpog_daily_event cron.
     */
    public static function purge_old() {
        global $wpdb;
        $days = (int) WPOG_Settings::get( 'form_consent', 'log_retention_days', 365 );
        if ( $days <= 0 ) {
            return;
        }
        $table = self::table_name();
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE consent_date < (NOW() - INTERVAL %d DAY)", $days ) );
    }
}
