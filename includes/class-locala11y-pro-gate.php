<?php
/**
 * Feature capability helper (Freemius-ready).
 *
 * Keeps free limits generous and transparent while providing clean hooks
 * for premium licensing once Freemius SDK is integrated.
 *
 * @package LocalA11y
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LocalA11y_Pro_Gate
 */
class LocalA11y_Pro_Gate {

	/**
	 * Maximum pages scanned per batch in the Free edition.
	 *
	 * @var int
	 */
	const FREE_BATCH_LIMIT = 25;

	/**
	 * Check whether a Pro license is active.
	 *
	 * @return bool
	 */
	public static function is_pro() {
		return (bool) apply_filters( 'locala11y_is_pro', false );
	}

	/**
	 * Return maximum pages allowed per batch scan.
	 *
	 * @return int 0 means unlimited.
	 */
	public static function get_batch_limit() {
		if ( self::is_pro() ) {
			return 0;
		}
		return self::FREE_BATCH_LIMIT;
	}

	/**
	 * Return allowed post types for scanning.
	 *
	 * @return array
	 */
	public static function get_allowed_post_types() {
		if ( self::is_pro() ) {
			$public_types = get_post_types( array( 'public' => true ), 'names' );
			unset( $public_types['attachment'] );
			return array_values( $public_types );
		}

		return array( 'page', 'post' );
	}
}
