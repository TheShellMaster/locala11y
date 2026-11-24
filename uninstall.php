<?php
/**
 * Uninstall routine for LocalA11y.
 *
 * Removes all LocalA11y database tables and options when the plugin is deleted.
 *
 * @package LocalA11y
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$locala11y_scans_table    = $wpdb->prefix . 'locala11y_scans';
$locala11y_findings_table = $wpdb->prefix . 'locala11y_findings';

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $locala11y_findings_table ) );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $locala11y_scans_table ) );

delete_option( 'locala11y_db_version' );
delete_option( 'locala11y_settings' );
delete_option( 'locala11y_statement_settings' );
delete_option( 'locala11y_manual_checklist' );
