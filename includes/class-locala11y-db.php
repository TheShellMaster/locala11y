<?php
/**
 * Database layer for LocalA11y scans and findings.
 *
 * @package LocalA11y
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LocalA11y_DB
 */
class LocalA11y_DB {

	/**
	 * Return scans table name.
	 *
	 * @return string
	 */
	public static function scans_table() {
		global $wpdb;
		return $wpdb->prefix . 'locala11y_scans';
	}

	/**
	 * Return findings table name.
	 *
	 * @return string
	 */
	public static function findings_table() {
		global $wpdb;
		return $wpdb->prefix . 'locala11y_findings';
	}

	/**
	 * Create or update custom tables using dbDelta.
	 *
	 * @return void
	 */
	public static function install() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$scans_table     = self::scans_table();
		$findings_table  = self::findings_table();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql_scans = "CREATE TABLE {$scans_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			wcag_level varchar(32) NOT NULL DEFAULT 'wcag22aa',
			viewport varchar(20) NOT NULL DEFAULT 'desktop',
			status varchar(20) NOT NULL DEFAULT 'running',
			total_pages int(11) unsigned NOT NULL DEFAULT 0,
			scanned_pages int(11) unsigned NOT NULL DEFAULT 0,
			violations_count int(11) unsigned NOT NULL DEFAULT 0,
			incomplete_count int(11) unsigned NOT NULL DEFAULT 0,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			completed_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY created_at (created_at)
		) {$charset_collate};";

		$sql_findings = "CREATE TABLE {$findings_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			scan_id bigint(20) unsigned NOT NULL,
			post_id bigint(20) unsigned NOT NULL DEFAULT 0,
			page_title varchar(255) NOT NULL DEFAULT '',
			page_url text NOT NULL,
			finding_type varchar(20) NOT NULL DEFAULT 'violation',
			rule_id varchar(100) NOT NULL,
			impact varchar(20) NOT NULL DEFAULT 'moderate',
			wcag_tags varchar(255) NOT NULL DEFAULT '',
			help_text text NOT NULL,
			description text NOT NULL,
			help_url text NOT NULL,
			selector text NOT NULL,
			html_snippet text NOT NULL,
			failure_summary text NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'open',
			reviewer_note text NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY scan_id (scan_id),
			KEY impact (impact),
			KEY status (status),
			KEY rule_id (rule_id)
		) {$charset_collate};";

		dbDelta( $sql_scans );
		dbDelta( $sql_findings );

		update_option( 'locala11y_db_version', LOCALA11Y_DB_VERSION );
	}

	/**
	 * Create a new scan run.
	 *
	 * @param array $data Scan parameters.
	 * @return int|false Inserted scan ID or false on failure.
	 */
	public static function create_scan( $data ) {
		global $wpdb;

		$inserted = $wpdb->insert(
			self::scans_table(),
			array(
				'wcag_level'       => sanitize_key( $data['wcag_level'] ?? 'wcag22aa' ),
				'viewport'         => sanitize_key( $data['viewport'] ?? 'desktop' ),
				'status'           => 'running',
				'total_pages'      => absint( $data['total_pages'] ?? 0 ),
				'scanned_pages'    => 0,
				'violations_count' => 0,
				'incomplete_count' => 0,
				'created_by'       => get_current_user_id(),
				'created_at'       => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%s' )
		);

		if ( false === $inserted ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Store findings for one scanned page and update scan totals.
	 *
	 * @param int    $scan_id    Scan ID.
	 * @param int    $post_id    Post ID (0 if custom URL).
	 * @param string $page_title Page title.
	 * @param string $page_url   Page URL.
	 * @param array  $findings   Sanitized findings array.
	 * @return array Counts inserted.
	 */
	public static function add_page_findings( $scan_id, $post_id, $page_title, $page_url, $findings ) {
		global $wpdb;

		$scan_id          = absint( $scan_id );
		$post_id          = absint( $post_id );
		$page_title       = sanitize_text_field( $page_title );
		$page_url         = esc_url_raw( $page_url );
		$now              = current_time( 'mysql', true );
		$violations_added = 0;
		$incomplete_added = 0;

		foreach ( $findings as $item ) {
			$finding_type = ( isset( $item['finding_type'] ) && 'incomplete' === $item['finding_type'] ) ? 'incomplete' : 'violation';
			$impact       = isset( $item['impact'] ) ? sanitize_key( $item['impact'] ) : 'moderate';
			if ( ! in_array( $impact, array( 'critical', 'serious', 'moderate', 'minor' ), true ) ) {
				$impact = 'moderate';
			}

			$wpdb->insert(
				self::findings_table(),
				array(
					'scan_id'         => $scan_id,
					'post_id'         => $post_id,
					'page_title'      => mb_substr( $page_title, 0, 250 ),
					'page_url'        => $page_url,
					'finding_type'    => $finding_type,
					'rule_id'         => mb_substr( sanitize_key( $item['rule_id'] ?? 'unknown' ), 0, 100 ),
					'impact'          => $impact,
					'wcag_tags'       => mb_substr( sanitize_text_field( $item['wcag_tags'] ?? '' ), 0, 250 ),
					'help_text'       => sanitize_text_field( $item['help_text'] ?? '' ),
					'description'     => sanitize_text_field( $item['description'] ?? '' ),
					'help_url'        => esc_url_raw( $item['help_url'] ?? '' ),
					'selector'        => mb_substr( sanitize_text_field( $item['selector'] ?? '' ), 0, 1000 ),
					'html_snippet'    => mb_substr( (string) ( $item['html_snippet'] ?? '' ), 0, 2000 ),
					'failure_summary' => mb_substr( sanitize_text_field( $item['failure_summary'] ?? '' ), 0, 1000 ),
					'status'          => 'open',
					'reviewer_note'   => '',
					'created_at'      => $now,
				),
				array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
			);

			if ( 'incomplete' === $finding_type ) {
				++$incomplete_added;
			} else {
				++$violations_added;
			}
		}

		$scans_table = self::scans_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$scans_table}
				 SET scanned_pages = scanned_pages + 1,
				     violations_count = violations_count + %d,
				     incomplete_count = incomplete_count + %d
				 WHERE id = %d",
				$violations_added,
				$incomplete_added,
				$scan_id
			)
		);

		return array(
			'violations' => $violations_added,
			'incomplete' => $incomplete_added,
		);
	}

	/**
	 * Complete or cancel a scan.
	 *
	 * @param int    $scan_id Scan ID.
	 * @param string $status  New status (completed|cancelled).
	 * @return bool
	 */
	public static function finish_scan( $scan_id, $status = 'completed' ) {
		global $wpdb;

		if ( ! in_array( $status, array( 'completed', 'cancelled' ), true ) ) {
			$status = 'completed';
		}

		$updated = $wpdb->update(
			self::scans_table(),
			array(
				'status'       => $status,
				'completed_at' => current_time( 'mysql', true ),
			),
			array( 'id' => absint( $scan_id ) ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		return false !== $updated;
	}

	/**
	 * Get recent scans.
	 *
	 * @param int $limit Max rows.
	 * @return array
	 */
	public static function get_scans( $limit = 15 ) {
		global $wpdb;
		$scans_table = self::scans_table();
		$limit       = max( 1, min( 100, absint( $limit ) ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$scans_table} ORDER BY id DESC LIMIT %d",
				$limit
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Get dashboard summary stats based on the latest scan.
	 *
	 * @return array
	 */
	public static function get_summary() {
		global $wpdb;
		$scans_table    = self::scans_table();
		$findings_table = self::findings_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$latest_scan = $wpdb->get_row(
			"SELECT * FROM {$scans_table} ORDER BY id DESC LIMIT 1",
			ARRAY_A
		);

		$counts = array(
			'critical' => 0,
			'serious'  => 0,
			'moderate' => 0,
			'minor'    => 0,
		);

		if ( ! empty( $latest_scan['id'] ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$grouped = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT impact, COUNT(*) AS cnt
					 FROM {$findings_table}
					 WHERE scan_id = %d AND finding_type = 'violation' AND status = 'open'
					 GROUP BY impact",
					absint( $latest_scan['id'] )
				),
				ARRAY_A
			);

			if ( is_array( $grouped ) ) {
				foreach ( $grouped as $row ) {
					if ( isset( $counts[ $row['impact'] ] ) ) {
						$counts[ $row['impact'] ] = (int) $row['cnt'];
					}
				}
			}
		}

		return array(
			'latest_scan' => $latest_scan,
			'open_counts' => $counts,
		);
	}

	/**
	 * Query findings with filters.
	 *
	 * @param array $args Filter args.
	 * @return array
	 */
	public static function get_findings( $args = array() ) {
		global $wpdb;
		$findings_table = self::findings_table();

		$where  = array( '1=1' );
		$values = array();

		if ( ! empty( $args['scan_id'] ) ) {
			$where[]  = 'scan_id = %d';
			$values[] = absint( $args['scan_id'] );
		}

		if ( ! empty( $args['impact'] ) && in_array( $args['impact'], array( 'critical', 'serious', 'moderate', 'minor' ), true ) ) {
			$where[]  = 'impact = %s';
			$values[] = $args['impact'];
		}

		if ( ! empty( $args['finding_type'] ) && in_array( $args['finding_type'], array( 'violation', 'incomplete' ), true ) ) {
			$where[]  = 'finding_type = %s';
			$values[] = $args['finding_type'];
		}

		if ( ! empty( $args['status'] ) && in_array( $args['status'], array( 'open', 'reviewed', 'fixed' ), true ) ) {
			$where[]  = 'status = %s';
			$values[] = $args['status'];
		}

		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
			$where[]  = '(page_title LIKE %s OR rule_id LIKE %s OR help_text LIKE %s OR selector LIKE %s)';
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
		}

		$limit  = isset( $args['limit'] ) ? max( 1, min( 500, absint( $args['limit'] ) ) ) : 100;
		$offset = isset( $args['offset'] ) ? absint( $args['offset'] ) : 0;

		$where_sql = implode( ' AND ', $where );
		$sql       = "SELECT * FROM {$findings_table}
			WHERE {$where_sql}
			ORDER BY FIELD(impact, 'critical', 'serious', 'moderate', 'minor'), id DESC
			LIMIT %d OFFSET %d";

		$values[] = $limit;
		$values[] = $offset;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $values ), ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Update status or note on a finding.
	 *
	 * @param int    $finding_id Finding ID.
	 * @param string $status     open|reviewed|fixed.
	 * @param string $note       Optional note.
	 * @return bool
	 */
	public static function update_finding_status( $finding_id, $status, $note = '' ) {
		global $wpdb;

		if ( ! in_array( $status, array( 'open', 'reviewed', 'fixed' ), true ) ) {
			return false;
		}

		$updated = $wpdb->update(
			self::findings_table(),
			array(
				'status'        => $status,
				'reviewer_note' => sanitize_textarea_field( $note ),
			),
			array( 'id' => absint( $finding_id ) ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		return false !== $updated;
	}

	/**
	 * Get top open rule summaries from the latest scan (for EAA statement known limitations).
	 *
	 * @param int $limit Max rule groups.
	 * @return array
	 */
	public static function get_top_open_limitations( $limit = 8 ) {
		global $wpdb;
		$scans_table    = self::scans_table();
		$findings_table = self::findings_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$latest_id = (int) $wpdb->get_var( "SELECT id FROM {$scans_table} ORDER BY id DESC LIMIT 1" );
		if ( ! $latest_id ) {
			return array();
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT rule_id, impact, help_text, COUNT(*) AS occurrences
				 FROM {$findings_table}
				 WHERE scan_id = %d AND finding_type = 'violation' AND status = 'open'
				 GROUP BY rule_id, impact, help_text
				 ORDER BY FIELD(impact, 'critical', 'serious', 'moderate', 'minor'), occurrences DESC
				 LIMIT %d",
				$latest_id,
				absint( $limit )
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Clear all stored scan data.
	 *
	 * @return void
	 */
	public static function clear_all() {
		global $wpdb;
		$findings_table = self::findings_table();
		$scans_table    = self::scans_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( "TRUNCATE TABLE {$findings_table}" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( "TRUNCATE TABLE {$scans_table}" );
	}
}
