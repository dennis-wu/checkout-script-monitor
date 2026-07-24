<?php
/**
 * Uninstall cleanup: remove options and the violations table.
 *
 * @package CSS_Script_Guard
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'csg_settings' );
delete_option( 'csg_inventory' );
delete_option( 'csg_baseline' );
delete_option( 'csg_notice_dismissed' );
delete_option( 'csg_db_version' );

global $wpdb;
$table = $wpdb->prefix . 'csg_violations';
$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore WordPress.DB
