<?php
/**
 * Tracking — admin-only detector of scripts / cookies / iframes /
 * external assets seen by logged-in administrators while browsing the site.
 *
 * Storage: {prefix}wpog_detections. Each row is one (type, value) pair
 * with the originating domain, first/last-seen timestamps, hit counter
 * and a workflow status.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPOG_Tracking {

    const STATUS_NEW     = 'new';
    const STATUS_IGNORED = 'ignored';
    const STATUS_BLOCKED = 'blocked';
    const STATUS_ALLOWED = 'allowed';

    public static function table_name() {
        global $wpdb;
        return $wpdb->prefix . 'wpog_detections';
    }

    /**
     * Make sure the table exists. Safe to call on every request — the
     * static flag short-circuits after the first verification per process.
     * dbDelta is only invoked when the table is genuinely missing.
     */
    public static function ensure_table() {
        static $checked = false;
        if ( $checked ) {
            return;
        }
        global $wpdb;
        $table = self::table_name();
        // Suppress errors so SHOW TABLES doesn't pollute the log when fine.
        $suppress = $wpdb->suppress_errors( true );
        $exists   = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
        $wpdb->suppress_errors( $suppress );
        if ( $exists !== $table ) {
            self::install_table();
        }
        $checked = true;
    }

    public static function install_table() {
        global $wpdb;
        $table           = self::table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            type VARCHAR(16) NOT NULL,
            value VARCHAR(512) NOT NULL,
            domain VARCHAR(255) DEFAULT '',
            page_url VARCHAR(512) DEFAULT '',
            first_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
            hits INT UNSIGNED DEFAULT 1,
            status VARCHAR(16) DEFAULT 'new',
            category_hint VARCHAR(20) DEFAULT '',
            value_hash CHAR(40) NOT NULL,
            UNIQUE KEY type_value (type, value_hash),
            KEY domain (domain),
            KEY status (status)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /* ---------- ingest ---------- */

    /**
     * Upsert a single detection. Increments hits + last_seen if already present.
     *
     * @param array $item { type, value, domain?, page_url?, category_hint? }
     */
    public static function upsert( array $item ) {
        global $wpdb;

        self::ensure_table();

        $type  = sanitize_key( $item['type'] ?? '' );
        $value = trim( (string) ( $item['value'] ?? '' ) );
        if ( '' === $type || '' === $value ) {
            return false;
        }
        // Cap field sizes.
        $value         = substr( $value, 0, 512 );
        $domain        = substr( sanitize_text_field( $item['domain'] ?? '' ), 0, 255 );
        $page_url_raw  = (string) ( $item['page_url'] ?? '' );
        // esc_url_raw rejects non-http schemes (e.g. moz-extension://) — keep
        // the host portion still useful by falling back to sanitize_text_field.
        $page_url      = esc_url_raw( $page_url_raw );
        if ( '' === $page_url ) {
            $page_url = sanitize_text_field( $page_url_raw );
        }
        $page_url      = substr( $page_url, 0, 512 );
        $category_hint = sanitize_key( $item['category_hint'] ?? '' );
        $hash          = sha1( $type . '|' . $value );
        $table         = self::table_name();
        $now           = current_time( 'mysql' );

        // Try to find by unique key.
        $existing_id = $wpdb->get_var(
            $wpdb->prepare( "SELECT id FROM {$table} WHERE type = %s AND value_hash = %s LIMIT 1", $type, $hash )
        );

        if ( $existing_id ) {
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table} SET hits = hits + 1, last_seen = %s WHERE id = %d",
                    $now, $existing_id
                )
            );
            return (int) $existing_id;
        }

        $ok = $wpdb->insert(
            $table,
            array(
                'type'          => $type,
                'value'         => $value,
                'domain'        => $domain,
                'page_url'      => $page_url,
                'first_seen'    => $now,
                'last_seen'     => $now,
                'hits'          => 1,
                'status'        => self::STATUS_NEW,
                'category_hint' => $category_hint,
                'value_hash'    => $hash,
            )
        );
        if ( false === $ok ) {
            return false;
        }
        // insert_id may be 0 if the row has no AUTO_INCREMENT visible to wpdb,
        // but the insert itself succeeded — return true in that case.
        $id = (int) $wpdb->insert_id;
        return $id > 0 ? $id : true;
    }

    public static function set_status( $id, $status ) {
        global $wpdb;
        if ( ! in_array( $status, array( self::STATUS_NEW, self::STATUS_IGNORED, self::STATUS_BLOCKED, self::STATUS_ALLOWED ), true ) ) {
            return false;
        }
        return (bool) $wpdb->update( self::table_name(), array( 'status' => $status ), array( 'id' => (int) $id ) );
    }

    public static function delete( $id ) {
        global $wpdb;
        return (bool) $wpdb->delete( self::table_name(), array( 'id' => (int) $id ) );
    }

    /* ---------- query ---------- */

    public static function query( $args = array() ) {
        global $wpdb;
        $args = array_merge( array(
            'status'   => '',
            'type'     => '',
            'search'   => '',
            'per_page' => 100,
            'page'     => 1,
            'orderby'  => 'last_seen',
            'order'    => 'DESC',
        ), $args );

        $table = self::table_name();
        $where = array( '1=1' );
        $params = array();

        if ( ! empty( $args['status'] ) ) {
            $where[]  = 'status = %s';
            $params[] = $args['status'];
        }
        if ( ! empty( $args['type'] ) ) {
            $where[]  = 'type = %s';
            $params[] = $args['type'];
        }
        if ( ! empty( $args['search'] ) ) {
            $like = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $where[]  = '(value LIKE %s OR domain LIKE %s)';
            $params[] = $like;
            $params[] = $like;
        }
        $where_sql = implode( ' AND ', $where );

        $orderby = in_array( $args['orderby'], array( 'last_seen', 'first_seen', 'hits', 'domain', 'type', 'status' ), true ) ? $args['orderby'] : 'last_seen';
        $order   = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

        $per_page = max( 1, (int) $args['per_page'] );
        $offset   = max( 0, ( (int) $args['page'] - 1 ) * $per_page );

        $count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
        $total     = (int) $wpdb->get_var( $params ? $wpdb->prepare( $count_sql, $params ) : $count_sql );

        $rows_sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
        $rows = $wpdb->get_results( $wpdb->prepare( $rows_sql, array_merge( $params, array( $per_page, $offset ) ) ) );

        return array( 'rows' => $rows, 'total' => $total );
    }

    public static function get( $id ) {
        global $wpdb;
        $table = self::table_name();
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ) );
    }

    public static function stats() {
        global $wpdb;
        $table = self::table_name();
        $out = array( 'new' => 0, 'ignored' => 0, 'blocked' => 0, 'allowed' => 0, 'total' => 0 );
        $rows = $wpdb->get_results( "SELECT status, COUNT(*) c FROM {$table} GROUP BY status" );
        foreach ( (array) $rows as $r ) {
            if ( isset( $out[ $r->status ] ) ) {
                $out[ $r->status ] = (int) $r->c;
            }
            $out['total'] += (int) $r->c;
        }
        return $out;
    }
}
