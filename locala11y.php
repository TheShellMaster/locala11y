<?php
/**
 * Plugin Name:       LocalA11y — Local Accessibility & EAA Audit
 * Plugin URI:        https://github.com/TheShellMaster/locala11y
 * Description:       Run honest, 100% local accessibility and European Accessibility Act (EAA) audits inside WordPress using bundled axe-core, track manual checks, and generate an EAA Statement. No external SaaS, no overlays.
 * Version:           1.0.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            TheShellMaster
 * Author URI:        https://github.com/TheShellMaster
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       locala11y
 *
 * @package LocalA11y
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LOCALA11Y_VERSION', '1.0.0' );
define( 'LOCALA11Y_DB_VERSION', '1.0.0' );
define( 'LOCALA11Y_AXE_VERSION', '4.14.1' );
define( 'LOCALA11Y_PLUGIN_FILE', __FILE__ );
define( 'LOCALA11Y_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'LOCALA11Y_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once LOCALA11Y_PLUGIN_DIR . 'includes/class-locala11y-pro-gate.php';
require_once LOCALA11Y_PLUGIN_DIR . 'includes/class-locala11y-db.php';
require_once LOCALA11Y_PLUGIN_DIR . 'includes/class-locala11y-statement.php';
require_once LOCALA11Y_PLUGIN_DIR . 'includes/class-locala11y-rest.php';
require_once LOCALA11Y_PLUGIN_DIR . 'includes/class-locala11y-admin.php';

/**
 * Plugin activation callback.
 *
 * @return void
 */
function locala11y_activate() {
	LocalA11y_DB::install();
	if ( false === get_option( 'locala11y_settings' ) ) {
		add_option(
			'locala11y_settings',
			array(
				'wcag_level'        => 'wcag22aa',
				'default_viewport'  => 'desktop',
				'enable_admin_bar'  => 1,
			)
		);
	}
}
register_activation_hook( __FILE__, 'locala11y_activate' );

/**
 * Initialize plugin components.
 *
 * @return void
 */
function locala11y_init() {
	if ( LOCALA11Y_DB_VERSION !== get_option( 'locala11y_db_version' ) ) {
		LocalA11y_DB::install();
	}
	LocalA11y_Statement::init();
	LocalA11y_REST::init();
	LocalA11y_Admin::init();
}
add_action( 'plugins_loaded', 'locala11y_init' );

/**
 * Add quick links on the Plugins screen.
 *
 * @param array $links Existing action links.
 * @return array
 */
function locala11y_plugin_action_links( $links ) {
	$dashboard_url = admin_url( 'admin.php?page=locala11y' );
	$statement_url = admin_url( 'admin.php?page=locala11y-statement' );

	$custom_links = array(
		'<a href="' . esc_url( $dashboard_url ) . '">' . esc_html__( 'Audit Dashboard', 'locala11y' ) . '</a>',
		'<a href="' . esc_url( $statement_url ) . '">' . esc_html__( 'EAA Statement', 'locala11y' ) . '</a>',
	);

	return array_merge( $custom_links, $links );
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'locala11y_plugin_action_links' );
