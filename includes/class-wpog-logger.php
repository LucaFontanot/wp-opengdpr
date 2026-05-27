<?php
/**
 * Consent log — DB table management and inserts.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPOG_Logger {

    public static function table_name() {
        global $wpdb;
        return $wpdb->prefix . 'wpog_consent_log';
    }

    public static function install_table() {
        global $wpdb;
        $table           = self::table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            consent_id VARCHAR(64) NOT NULL,
            consent_date DATETIME NOT NULL,
            ip_address VARCHAR(45),
            user_agent VARCHAR(255),
            necessary TINYINT(1) DEFAULT 1,
            functional TINYINT(1) DEFAULT 0,
            analytics TINYINT(1) DEFAULT 0,
            marketing TINYINT(1) DEFAULT 0,
            action VARCHAR(20) NOT NULL,
            policy_version VARCHAR(20),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            KEY consent_id (consent_id),
            KEY consent_date (consent_date)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        update_option( 'wpog_db_version', WPOG_Settings::DB_VERSION );
    }

    public static function anonymize_ip( $ip ) {
        if ( empty( $ip ) ) {
            return '';
        }
        if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
            $parts = explode( '.', $ip );
            $parts[3] = '0';
            return implode( '.', $parts );
        }
        if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
            $packed = inet_pton( $ip );
            if ( false !== $packed ) {
                // Zero the last 80 bits (last 10 bytes) of the 16-byte IPv6 address.
                $packed = substr( $packed, 0, 6 ) . str_repeat( "\0", 10 );
                return inet_ntop( $packed );
            }
        }
        return '';
    }

    public static function get_client_ip() {
        $candidates = array( 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );
        foreach ( $candidates as $key ) {
            if ( ! empty( $_SERVER[ $key ] ) ) {
                $ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
                if ( strpos( $ip, ',' ) !== false ) {
                    $ip = trim( explode( ',', $ip )[0] );
                }
                if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                    return $ip;
                }
            }
        }
        return '';
    }

    public static function log( $data ) {
        global $wpdb;

        if ( ! WPOG_Settings::get( 'general', 'log_enabled' ) ) {
            return false;
        }

        $ip = self::get_client_ip();
        if ( WPOG_Settings::get( 'general', 'anonymize_ip' ) ) {
            $ip = self::anonymize_ip( $ip );
        }

        $ua = isset( $_SERVER['HTTP_USER_AGENT'] )
            ? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 255 )
            : '';

        $row = array(
            'consent_id'     => substr( (string) ( $data['id'] ?? '' ), 0, 64 ),
            'consent_date'   => current_time( 'mysql' ),
            'ip_address'     => $ip,
            'user_agent'     => $ua,
            'necessary'      => 1,
            'functional'     => ! empty( $data['categories']['functional'] ) ? 1 : 0,
            'analytics'      => ! empty( $data['categories']['analytics'] ) ? 1 : 0,
            'marketing'      => ! empty( $data['categories']['marketing'] ) ? 1 : 0,
            'action'         => in_array( $data['action'] ?? '', array( 'accept_all', 'reject_all', 'custom' ), true ) ? $data['action'] : 'custom',
            'policy_version' => substr( (string) ( $data['version'] ?? '' ), 0, 20 ),
        );

        return $wpdb->insert( self::table_name(), $row );
    }

    public static function purge_old() {
        global $wpdb;
        $days = (int) WPOG_Settings::get( 'general', 'log_retention_days', 365 );
        if ( $days <= 0 ) {
            return;
        }
        $table = self::table_name();
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE consent_date < (NOW() - INTERVAL %d DAY)", $days ) );
    }

    public static function query( $args = array() ) {
        global $wpdb;
        $defaults = array(
            'from'     => '',
            'to'       => '',
            'action'   => '',
            'per_page' => 50,
            'page'     => 1,
        );
        $args = array_merge( $defaults, $args );
        $table = self::table_name();
        $where = array( '1=1' );
        $params = array();
        if ( ! empty( $args['from'] ) ) {
            $where[] = 'consent_date >= %s';
            $params[] = $args['from'] . ' 00:00:00';
        }
        if ( ! empty( $args['to'] ) ) {
            $where[] = 'consent_date <= %s';
            $params[] = $args['to'] . ' 23:59:59';
        }
        if ( ! empty( $args['action'] ) && in_array( $args['action'], array( 'accept_all', 'reject_all', 'custom' ), true ) ) {
            $where[] = 'action = %s';
            $params[] = $args['action'];
        }
        $where_sql = implode( ' AND ', $where );

        $per_page = max( 1, (int) $args['per_page'] );
        $page     = max( 1, (int) $args['page'] );
        $offset   = ( $page - 1 ) * $per_page;

        $count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
        $total = (int) $wpdb->get_var( $params ? $wpdb->prepare( $count_sql, $params ) : $count_sql );

        $rows_sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY consent_date DESC LIMIT %d OFFSET %d";
        $rows = $wpdb->get_results( $wpdb->prepare( $rows_sql, array_merge( $params, array( $per_page, $offset ) ) ) );

        return array( 'rows' => $rows, 'total' => $total );
    }

    public static function stats() {
        global $wpdb;
        $table = self::table_name();
        $out = array( 'accept_all' => 0, 'reject_all' => 0, 'custom' => 0, 'total' => 0 );
        $rows = $wpdb->get_results( "SELECT action, COUNT(*) AS c FROM {$table} GROUP BY action" );
        foreach ( (array) $rows as $r ) {
            if ( isset( $out[ $r->action ] ) ) {
                $out[ $r->action ] = (int) $r->c;
            }
            $out['total'] += (int) $r->c;
        }
        return $out;
    }
}
