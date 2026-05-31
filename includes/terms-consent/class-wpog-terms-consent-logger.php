<?php
/**
 * Terms & Conditions consent log — DB table management, inserts and queries for
 * the e-commerce Terms & Conditions module.
 *
 * Storage: {prefix}wpog_terms_consent_log. Each row is an audit-trail proof that
 * a given document (terms / privacy / marketing / age) was accepted (or not) at
 * checkout / registration / account, optionally tied to a WooCommerce order.
 *
 * The document_hash column stores the SHA-256 fingerprint of the Terms text at
 * the moment of acceptance, so the exact accepted version can be proven even if
 * the page is edited afterwards.
 *
 * IMPORTANT: the IP is always anonymised before storage (shared with the other
 * loggers via WPOG_Logger::anonymize_ip()).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPOG_Terms_Consent_Logger {

    const DOCUMENT_TYPES = array( 'terms', 'privacy', 'marketing', 'age' );

    public static function table_name() {
        global $wpdb;
        return $wpdb->prefix . 'wpog_terms_consent_log';
    }

    public static function install_table() {
        global $wpdb;
        $table           = self::table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            consent_id VARCHAR(64) NOT NULL,
            order_id BIGINT UNSIGNED DEFAULT NULL,
            user_id BIGINT UNSIGNED DEFAULT NULL,
            document_type VARCHAR(20) NOT NULL,
            document_version VARCHAR(20) NOT NULL,
            document_hash VARCHAR(64) DEFAULT '',
            consent_given TINYINT(1) DEFAULT 0,
            ip_address VARCHAR(45) DEFAULT '',
            user_agent VARCHAR(500) DEFAULT '',
            page_url VARCHAR(500) DEFAULT '',
            context VARCHAR(50) DEFAULT '',
            integration VARCHAR(50) DEFAULT '',
            consent_date DATETIME NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            KEY consent_id (consent_id),
            KEY order_id (order_id),
            KEY user_id (user_id),
            KEY document_type (document_type),
            KEY consent_date (consent_date)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Lazily create the table the first time it is needed in a process. Mirrors
     * WPOG_Form_Consent_Logger::ensure_table().
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
     *   order_id, user_id, document_type, document_version, document_hash,
     *   consent_given, page_url, context, integration
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

        $type = sanitize_key( (string) ( $data['document_type'] ?? 'terms' ) );
        if ( ! in_array( $type, self::DOCUMENT_TYPES, true ) ) {
            $type = 'terms';
        }

        $order_id = isset( $data['order_id'] ) ? absint( $data['order_id'] ) : 0;
        $user_id  = isset( $data['user_id'] ) ? absint( $data['user_id'] ) : 0;

        $row = array(
            'consent_id'       => substr( wp_generate_uuid4(), 0, 64 ),
            'order_id'         => $order_id ?: null,
            'user_id'          => $user_id ?: null,
            'document_type'    => $type,
            'document_version' => substr( sanitize_text_field( (string) ( $data['document_version'] ?? '1.0' ) ), 0, 20 ),
            'document_hash'    => substr( sanitize_text_field( (string) ( $data['document_hash'] ?? '' ) ), 0, 64 ),
            'consent_given'    => ! empty( $data['consent_given'] ) ? 1 : 0,
            'ip_address'       => $ip,
            'user_agent'       => $ua,
            'page_url'         => substr( esc_url_raw( (string) ( $data['page_url'] ?? '' ) ), 0, 500 ),
            'context'          => substr( sanitize_key( (string) ( $data['context'] ?? 'checkout' ) ), 0, 50 ),
            'integration'      => substr( sanitize_key( (string) ( $data['integration'] ?? 'generic' ) ), 0, 50 ),
            'consent_date'     => current_time( 'mysql' ),
        );

        $ok = $wpdb->insert(
            self::table_name(),
            $row,
            array( '%s', '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
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
            'document_type' => '',
            'version'       => '',
            'order_id'      => '',
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
        if ( '' !== $args['document_type'] && in_array( $args['document_type'], self::DOCUMENT_TYPES, true ) ) {
            $where[]  = 'document_type = %s';
            $params[] = (string) $args['document_type'];
        }
        if ( '' !== $args['version'] ) {
            $where[]  = 'document_version = %s';
            $params[] = (string) $args['version'];
        }
        if ( '' !== $args['order_id'] && (int) $args['order_id'] > 0 ) {
            $where[]  = 'order_id = %d';
            $params[] = (int) $args['order_id'];
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
     * Distinct document versions present in the log, for the filter dropdown.
     */
    public static function versions() {
        global $wpdb;
        self::ensure_table();
        $table = self::table_name();
        $rows  = $wpdb->get_col( "SELECT DISTINCT document_version FROM {$table} WHERE document_version <> '' ORDER BY document_version DESC" );
        return array_map( 'strval', (array) $rows );
    }

    public static function stats() {
        global $wpdb;
        self::ensure_table();
        $table = self::table_name();

        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

        $by_type = array();
        foreach ( self::DOCUMENT_TYPES as $t ) {
            $by_type[ $t ] = (int) $wpdb->get_var(
                $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE document_type = %s AND consent_given = 1", $t )
            );
        }

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
            'total'   => $total,
            'by_type' => $by_type,
            'series'  => $series,
        );
    }

    /* ---------- per-user queries (My Account + WP Privacy API) ---------- */

    /**
     * All consent rows belonging to a user, newest first. Matches both the
     * directly-stored user_id and any rows attached to the supplied order ids
     * (covers consent given while logged in or tied to that user's orders).
     *
     * @param int   $user_id
     * @param int[] $order_ids Optional list of order ids belonging to the user.
     * @return array Row objects.
     */
    public static function get_by_user( $user_id, array $order_ids = array() ) {
        global $wpdb;
        self::ensure_table();

        $user_id = absint( $user_id );
        $table   = self::table_name();

        $where  = array();
        $params = array();

        if ( $user_id > 0 ) {
            $where[]  = 'user_id = %d';
            $params[] = $user_id;
        }

        $order_ids = array_values( array_filter( array_map( 'absint', $order_ids ) ) );
        if ( $order_ids ) {
            $placeholders = implode( ',', array_fill( 0, count( $order_ids ), '%d' ) );
            $where[]      = "order_id IN ({$placeholders})";
            $params       = array_merge( $params, $order_ids );
        }

        if ( ! $where ) {
            return array();
        }

        $sql = "SELECT * FROM {$table} WHERE " . implode( ' OR ', $where ) . ' ORDER BY consent_date DESC';
        return (array) $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
    }

    /**
     * Anonymise (rather than hard-delete) every consent row of a user: the proof
     * that consent was given is kept for legal accountability, but the personal
     * identifiers are scrubbed. Used by the WP Privacy "erase personal data" tool.
     *
     * @param int   $user_id
     * @param int[] $order_ids
     * @return int Number of affected rows.
     */
    public static function anonymize_user( $user_id, array $order_ids = array() ) {
        global $wpdb;
        self::ensure_table();

        $user_id = absint( $user_id );
        $table   = self::table_name();

        $where  = array();
        $params = array();

        if ( $user_id > 0 ) {
            $where[]  = 'user_id = %d';
            $params[] = $user_id;
        }
        $order_ids = array_values( array_filter( array_map( 'absint', $order_ids ) ) );
        if ( $order_ids ) {
            $placeholders = implode( ',', array_fill( 0, count( $order_ids ), '%d' ) );
            $where[]      = "order_id IN ({$placeholders})";
            $params       = array_merge( $params, $order_ids );
        }
        if ( ! $where ) {
            return 0;
        }

        $sql = "UPDATE {$table} SET ip_address = '', user_agent = '' WHERE " . implode( ' OR ', $where );
        return (int) $wpdb->query( $wpdb->prepare( $sql, $params ) );
    }

    /**
     * Delete records older than the configured retention period. Hooked onto
     * the existing wpog_daily_event cron.
     */
    public static function purge_old() {
        global $wpdb;
        $days = (int) WPOG_Settings::get( 'terms_consent', 'log_retention_days', 730 );
        if ( $days <= 0 ) {
            return;
        }
        $table = self::table_name();
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE consent_date < (NOW() - INTERVAL %d DAY)", $days ) );
    }
}
