<?php
/**
 * REST API controller for LocalA11y.
 *
 * @package LocalA11y
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LocalA11y_REST
 */
class LocalA11y_REST {

	/**
	 * REST API namespace.
	 *
	 * @var string
	 */
	const NAMESPACE = 'locala11y/v1';

	/**
	 * Hook REST route registration.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register REST endpoints.
	 *
	 * @return void
	 */
	public static function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/targets',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_targets' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/scans',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_scans' ),
					'permission_callback' => array( __CLASS__, 'can_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'create_scan' ),
					'permission_callback' => array( __CLASS__, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/scans/(?P<id>\d+)/results',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'store_page_results' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/scans/(?P<id>\d+)/complete',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'complete_scan' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/findings',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_findings' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/findings/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( __CLASS__, 'update_finding' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);
	}

	/**
	 * Permission callback: administrators only.
	 *
	 * @return bool
	 */
	public static function can_manage() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Return published site URLs available to audit.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function get_targets( WP_REST_Request $request ) {
		$post_types  = LocalA11y_Pro_Gate::get_allowed_post_types();
		$batch_limit = LocalA11y_Pro_Gate::get_batch_limit();
		$limit       = $batch_limit > 0 ? $batch_limit : 200;

		$targets = array(
			array(
				'post_id' => 0,
				'title'   => __( 'Homepage', 'locala11y' ) . ' — ' . get_bloginfo( 'name' ),
				'url'     => home_url( '/' ),
				'type'    => 'home',
			),
		);

		$home_url = trailingslashit( home_url( '/' ) );

		$query = new WP_Query(
			array(
				'post_type'      => $post_types,
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			)
		);

		foreach ( $query->posts as $post ) {
			$permalink = get_permalink( $post );
			if ( ! $permalink || trailingslashit( $permalink ) === $home_url ) {
				continue;
			}
			$targets[] = array(
				'post_id' => (int) $post->ID,
				'title'   => wp_strip_all_tags( get_the_title( $post ) ),
				'url'     => $permalink,
				'type'    => $post->post_type,
			);
			if ( $batch_limit > 0 && count( $targets ) >= $batch_limit ) {
				break;
			}
		}

		return rest_ensure_response(
			array(
				'targets'     => $targets,
				'batch_limit' => $batch_limit,
				'is_pro'      => LocalA11y_Pro_Gate::is_pro(),
			)
		);
	}

	/**
	 * List recent scans.
	 *
	 * @return WP_REST_Response
	 */
	public static function get_scans() {
		return rest_ensure_response(
			array(
				'scans'   => LocalA11y_DB::get_scans( 20 ),
				'summary' => LocalA11y_DB::get_summary(),
			)
		);
	}

	/**
	 * Start a new scan.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function create_scan( WP_REST_Request $request ) {
		$wcag_level  = sanitize_key( $request->get_param( 'wcag_level' ) ?? 'wcag22aa' );
		$viewport    = sanitize_key( $request->get_param( 'viewport' ) ?? 'desktop' );
		$total_pages = absint( $request->get_param( 'total_pages' ) ?? 0 );

		$allowed_levels = array( 'wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa' );
		if ( ! in_array( $wcag_level, $allowed_levels, true ) ) {
			$wcag_level = 'wcag22aa';
		}

		if ( ! in_array( $viewport, array( 'desktop', 'mobile' ), true ) ) {
			$viewport = 'desktop';
		}

		$batch_limit = LocalA11y_Pro_Gate::get_batch_limit();
		if ( $batch_limit > 0 && $total_pages > $batch_limit ) {
			$total_pages = $batch_limit;
		}

		$scan_id = LocalA11y_DB::create_scan(
			array(
				'wcag_level'  => $wcag_level,
				'viewport'    => $viewport,
				'total_pages' => $total_pages,
			)
		);

		if ( ! $scan_id ) {
			return new WP_Error( 'locala11y_scan_create_failed', __( 'Could not create scan record.', 'locala11y' ), array( 'status' => 500 ) );
		}

		return rest_ensure_response(
			array(
				'scan_id' => $scan_id,
			)
		);
	}

	/**
	 * Store findings from one scanned page.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function store_page_results( WP_REST_Request $request ) {
		$scan_id    = absint( $request['id'] );
		$post_id    = absint( $request->get_param( 'post_id' ) ?? 0 );
		$page_title = sanitize_text_field( $request->get_param( 'page_title' ) ?? '' );
		$page_url   = esc_url_raw( $request->get_param( 'page_url' ) ?? '' );
		$findings   = $request->get_param( 'findings' );

		if ( ! $scan_id || empty( $page_url ) ) {
			return new WP_Error( 'locala11y_invalid_payload', __( 'Missing scan ID or page URL.', 'locala11y' ), array( 'status' => 400 ) );
		}

		// Ensure URL belongs to the current WordPress site (same host).
		$site_host = wp_parse_url( home_url(), PHP_URL_HOST );
		$url_host  = wp_parse_url( $page_url, PHP_URL_HOST );
		if ( ! $site_host || ! $url_host || strtolower( $site_host ) !== strtolower( $url_host ) ) {
			return new WP_Error( 'locala11y_invalid_origin', __( 'Only same-site URLs can be stored.', 'locala11y' ), array( 'status' => 400 ) );
		}

		if ( ! is_array( $findings ) ) {
			$findings = array();
		}

		// Cap per-page findings to avoid accidental huge payloads.
		$findings = array_slice( $findings, 0, 500 );

		$counts = LocalA11y_DB::add_page_findings( $scan_id, $post_id, $page_title, $page_url, $findings );

		return rest_ensure_response(
			array(
				'saved'  => true,
				'counts' => $counts,
			)
		);
	}

	/**
	 * Mark a scan as completed or cancelled.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function complete_scan( WP_REST_Request $request ) {
		$scan_id = absint( $request['id'] );
		$status  = sanitize_key( $request->get_param( 'status' ) ?? 'completed' );
		$ok      = LocalA11y_DB::finish_scan( $scan_id, $status );

		return rest_ensure_response(
			array(
				'updated' => $ok,
				'summary' => LocalA11y_DB::get_summary(),
			)
		);
	}

	/**
	 * Fetch filtered findings.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function get_findings( WP_REST_Request $request ) {
		$rows = LocalA11y_DB::get_findings(
			array(
				'scan_id'      => $request->get_param( 'scan_id' ),
				'impact'       => $request->get_param( 'impact' ),
				'finding_type' => $request->get_param( 'finding_type' ),
				'status'       => $request->get_param( 'status' ),
				'search'       => $request->get_param( 'search' ),
				'limit'        => $request->get_param( 'limit' ) ?? 100,
				'offset'       => $request->get_param( 'offset' ) ?? 0,
			)
		);

		return rest_ensure_response(
			array(
				'findings' => $rows,
			)
		);
	}

	/**
	 * Update status/note on a finding.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function update_finding( WP_REST_Request $request ) {
		$finding_id = absint( $request['id'] );
		$status     = sanitize_key( $request->get_param( 'status' ) ?? 'open' );
		$note       = (string) ( $request->get_param( 'reviewer_note' ) ?? '' );

		if ( ! in_array( $status, array( 'open', 'reviewed', 'fixed' ), true ) ) {
			return new WP_Error( 'locala11y_invalid_status', __( 'Invalid finding status.', 'locala11y' ), array( 'status' => 400 ) );
		}

		$updated = LocalA11y_DB::update_finding_status( $finding_id, $status, $note );

		return rest_ensure_response(
			array(
				'updated' => $updated,
			)
		);
	}
}
