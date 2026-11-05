<?php
/**
 * Open Cookie Database integration.
 *
 * Stores the community-maintained Open Cookie Database locally and exposes
 * lookup methods used by the admin UI (autocomplete + detection matching).
 *
 * The upstream JSON is keyed by platform name; refresh() flattens it into
 * the local table so lookups are fast.
 *
 * @see https://github.com/jkwakman/Open-Cookie-Database
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPOG_Cookie_Database {

    const TABLE_SUFFIX      = 'wpog_open_cookie_db';
    const SOURCE_URL        = 'https://raw.githubusercontent.com/jkwakman/Open-Cookie-Database/master/open-cookie-database.json';
    const CRON_HOOK         = 'wpog_monthly_cookie_db_refresh';
    const OPTION_VERSION    = 'wpog_cookie_db_version';
    const OPTION_LAST_FETCH = 'wpog_cookie_db_last_fetch';

    /** @var string|null Cached table name so we only compute it once per request. */
    private static $table = null;

    /* ────────── table helpers ────────── */

    public static function table_name() {
        if ( null === self::$table ) {
            global $wpdb;
            self::$table = $wpdb->prefix . self::TABLE_SUFFIX;
        }
        return self::$table;
    }

    /**
     * Make sure the table exists. Safe to call on every request — the
     * static flag short-circuits after the first verification per process.
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

    public static function install_table() {
        global $wpdb;
        $table           = self::table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            source_id VARCHAR(40) NOT NULL,
            platform VARCHAR(255) DEFAULT '',
            category VARCHAR(64) DEFAULT '',
            cookie_name VARCHAR(255) NOT NULL,
            description TEXT DEFAULT '',
            retention_period VARCHAR(128) DEFAULT '',
            data_controller VARCHAR(255) DEFAULT '',
            privacy_url VARCHAR(512) DEFAULT '',
            wildcard_match TINYINT UNSIGNED DEFAULT 0,
            UNIQUE KEY source_id_idx (source_id),
            KEY cookie_name_idx (cookie_name),
            KEY category_idx (category),
            KEY wildcard_idx (wildcard_match)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        // Track the schema version so maybe_upgrade knows when to re-install.
        update_option( self::OPTION_VERSION, WPOG_Settings::DB_VERSION );
    }

    /* ────────── fetch & store ────────── */

    /**
     * Download the JSON database from GitHub and upsert every record.
     *
     * The upstream JSON is structured as:
     *   { "PlatformName": [ { id, category, cookie, description,
     *       retentionPeriod, dataController, privacyLink, wildcardMatch }, ... ], ... }
     *
     * @return int|WP_Error Number of records stored, or WP_Error on failure.
     */
    public static function refresh() {
        $response = wp_remote_get(
            self::SOURCE_URL,
            array(
                'timeout'   => 45,
                'sslverify' => true,
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( 200 !== $code ) {
            return new WP_Error( 'http_error', sprintf( 'Unexpected HTTP %d', $code ) );
        }

        $body = wp_remote_retrieve_body( $response );
        // Third-party data source: cap the payload size (the upstream file is
        // a few MB) to protect memory if the source is compromised or broken.
        if ( strlen( $body ) > 20 * MB_IN_BYTES ) {
            return new WP_Error( 'too_large', 'Upstream cookie database exceeds 20 MB, refusing to import.' );
        }
        $data = json_decode( $body, true );
        unset( $body );
        if ( ! is_array( $data ) ) {
            return new WP_Error( 'parse_error', 'JSON decode failed or returned non-array.' );
        }

        // Flatten the platform-keyed object into a single list of records,
        // injecting the platform name into each entry.
        $flat = array();
        foreach ( $data as $platform => $cookies ) {
            if ( ! is_array( $cookies ) ) {
                continue;
            }
            foreach ( $cookies as $entry ) {
                if ( ! is_array( $entry ) ) {
                    continue;
                }
                $entry['_platform'] = $platform;
                $flat[]             = $entry;
            }
        }
        return self::upsert_batch( $flat );
    }

    /**
     * Upsert a batch of records. Each entry is a flat array with keys
     * matching the upstream JSON: id, category, cookie, description,
     * retentionPeriod, dataController, privacyLink, wildcardMatch,
     * plus a synthetic _platform injected by refresh().
     *
     * @param array $records
     * @return int Number of rows stored.
     */
    public static function upsert_batch( array $records ) {
        self::ensure_table();
        global $wpdb;
        $table = self::table_name();
        $count = 0;
        if ( function_exists( 'set_time_limit' ) ) {
            @set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
        }

        // Collect all source IDs to delete stale entries afterwards.
        $incoming_ids = array();
        $buffer       = array();
        $flush        = function () use ( &$buffer, $wpdb, $table ) {
            if ( empty( $buffer ) ) {
                return;
            }
            // One multi-row upsert per chunk instead of SELECT + INSERT/UPDATE
            // per record (~5k queries → ~15).
            $placeholders = array();
            $params       = array();
            foreach ( $buffer as $v ) {
                $placeholders[] = '(%s,%s,%s,%s,%s,%s,%s,%s,%d)';
                array_push( $params, $v['source_id'], $v['platform'], $v['category'], $v['cookie_name'], $v['description'], $v['retention_period'], $v['data_controller'], $v['privacy_url'], $v['wildcard_match'] );
            }
            $sql = "INSERT INTO {$table} (source_id, platform, category, cookie_name, description, retention_period, data_controller, privacy_url, wildcard_match) VALUES "
                . implode( ',', $placeholders )
                . ' ON DUPLICATE KEY UPDATE platform=VALUES(platform), category=VALUES(category), cookie_name=VALUES(cookie_name), description=VALUES(description), retention_period=VALUES(retention_period), data_controller=VALUES(data_controller), privacy_url=VALUES(privacy_url), wildcard_match=VALUES(wildcard_match)';
            $wpdb->query( $wpdb->prepare( $sql, $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $buffer = array();
        };

        foreach ( $records as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }
            $source_id = substr( sanitize_text_field( (string) ( $row['id'] ?? '' ) ), 0, 40 );
            if ( '' === $source_id ) {
                continue;
            }
            // Untrusted third-party data: sanitise every field before storing.
            $cookie_name = substr( sanitize_text_field( (string) ( $row['cookie'] ?? '' ) ), 0, 255 );
            if ( '' === $cookie_name ) {
                continue;
            }

            $incoming_ids[] = $source_id;

            $buffer[] = array(
                'source_id'        => $source_id,
                'platform'         => substr( sanitize_text_field( (string) ( $row['_platform'] ?? $row['platform'] ?? '' ) ), 0, 255 ),
                'category'         => self::map_category( sanitize_text_field( (string) ( $row['category'] ?? '' ) ) ),
                'cookie_name'      => $cookie_name,
                'description'      => sanitize_textarea_field( (string) ( $row['description'] ?? '' ) ),
                'retention_period' => substr( sanitize_text_field( (string) ( $row['retentionPeriod'] ?? '' ) ), 0, 128 ),
                'data_controller'  => substr( sanitize_text_field( (string) ( $row['dataController'] ?? '' ) ), 0, 255 ),
                'privacy_url'      => substr( esc_url_raw( trim( (string) ( $row['privacyLink'] ?? '' ) ), array( 'http', 'https' ) ), 0, 512 ),
                'wildcard_match'   => ! empty( $row['wildcardMatch'] ) ? 1 : 0,
            );
            $count++;
            if ( count( $buffer ) >= 200 ) {
                $flush();
            }
        }
        $flush();

        // Remove entries no longer present in the upstream database — but
        // never wipe the local copy because of a truncated/broken upstream file.
        $existing_total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ( ! empty( $incoming_ids ) && count( $incoming_ids ) >= 0.5 * $existing_total ) {
            $placeholders = implode( ',', array_fill( 0, count( $incoming_ids ), '%s' ) );
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$table} WHERE source_id NOT IN ({$placeholders})",
                    $incoming_ids
                )
            );
        }

        // Bump version and record timestamp.
        update_option( self::OPTION_VERSION, WPOG_Settings::DB_VERSION );
        update_option( self::OPTION_LAST_FETCH, time() );

        return $count;
    }

    /**
     * Map Open Cookie Database category strings to the plugin's four-category system.
     *
     * Open DB: Functional | Personalization | Analytics | Marketing | Security
     * Plugin:  necessary  | functional      | analytics | marketing | (→ necessary)
     */
    private static function map_category( $db_category ) {
        $map = array(
            'functional'       => 'necessary',
            'security'         => 'necessary',
            'personalization'  => 'functional',
            'preferences'      => 'functional',
            'analytics'        => 'analytics',
            'performance'      => 'analytics',
            'statistics'       => 'analytics',
            'marketing'        => 'marketing',
            'tracking'         => 'marketing',
            'social media'     => 'marketing',
        );
        $db_category = strtolower( trim( $db_category ) );
        return isset( $map[ $db_category ] ) ? $map[ $db_category ] : 'analytics';
    }

    /* ────────── lookup ────────── */

    /**
     * Search the local database for cookies matching a given name.
     *
     * Handles both exact matches and wildcard-prefix matches when the
     * searched name starts with a known wildcard cookie base.
     *
     * @param string $cookie_name The cookie name to look up.
     * @return object|null Database row object, or null if no match.
     */
    public static function lookup( $cookie_name ) {
        self::ensure_table();
        global $wpdb;
        $table = self::table_name();
        $cookie_name = trim( (string) $cookie_name );
        if ( '' === $cookie_name ) {
            return null;
        }

        // 1. Exact match first.
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE cookie_name = %s AND wildcard_match = 0 LIMIT 1",
            $cookie_name
        ) );
        if ( $row ) {
            return $row;
        }

        // 2. Wildcard: find entries where the DB cookie_name is a prefix of the
        //    searched cookie. We strip trailing digits/alphanumeric IDs from the
        //    searched name and match against wildcard entries that share the base.
        //
        //    Approach: get all wildcard entries, then compare in PHP. The
        //    wildcard dataset is small enough (~hundreds) that a PHP-side
        //    filter is fine.
        // Loaded once per request (lookup() is called for every row of the
        // Detections page — previously one full table scan per row).
        static $wildcards = null;
        if ( null === $wildcards ) {
            $wildcards = (array) $wpdb->get_results(
                "SELECT * FROM {$table} WHERE wildcard_match = 1" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            );
        }
        foreach ( $wildcards as $wc ) {
            $base = preg_replace( '/[^a-z_]/i', '', $wc->cookie_name );
            $test = preg_replace( '/[^a-z_]/i', '', $cookie_name );
            if ( '' !== $base && stripos( $test, $base ) === 0 ) {
                return $wc;
            }
        }

        return null;
    }

    /**
     * Autocomplete search — return rows whose cookie_name contains the search
     * term (exact + wildcard), limited to a reasonable result set.
     *
     * @param string $search Partial name to search for.
     * @param int    $limit  Max results.
     * @return array         Array of row objects.
     */
    public static function autocomplete( $search, $limit = 15 ) {
        self::ensure_table();
        global $wpdb;
        $table  = self::table_name();
        $search = trim( (string) $search );
        if ( '' === $search ) {
            return array();
        }
        $like = '%' . $wpdb->esc_like( $search ) . '%';
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE cookie_name LIKE %s ORDER BY cookie_name ASC LIMIT %d",
            $like,
            $limit
        ) );
    }

    /**
     * Check whether the local database has any records at all.
     *
     * @return bool
     */
    public static function has_data() {
        self::ensure_table();
        global $wpdb;
        $table = self::table_name();
        return (bool) $wpdb->get_var( "SELECT 1 FROM {$table} LIMIT 1" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    /**
     * Get a single record by its internal ID.
     *
     * @param int $id
     * @return object|null
     */
    public static function get_by_id( $id ) {
        global $wpdb;
        $table = self::table_name();
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d LIMIT 1",
            (int) $id
        ) );
    }

    /* ────────── cron ────────── */

    /**
     * Schedule the monthly refresh if not already scheduled.
     */
    public static function schedule_cron() {
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event(
                time() + DAY_IN_SECONDS, // First run: 24 h from activation.
                'monthly',
                self::CRON_HOOK
            );
        }
    }

    /**
     * Clear the scheduled cron (called on deactivation).
     */
    public static function clear_cron() {
        $ts = wp_next_scheduled( self::CRON_HOOK );
        if ( $ts ) {
            wp_unschedule_event( $ts, self::CRON_HOOK );
        }
    }

    /**
     * Cron callback — refresh the database silently.
     */
    public static function cron_refresh() {
        $result = self::refresh();
        if ( is_wp_error( $result ) ) {
            error_log( '[WP OpenGDPR] Cookie DB refresh failed: ' . $result->get_error_message() );
        }
    }
}
