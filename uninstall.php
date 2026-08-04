<?php
/**
 * Uninstall cleanup: remove options and the violations table.
 *
 * @package CyberShield_Checkout_Script_Monitor
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'cscsm_settings' );
delete_option( 'cscsm_inventory' );
delete_option( 'cscsm_baseline' );
delete_option( 'cscsm_notice_dismissed' );
delete_option( 'cscsm_db_version' );

global $wpdb;
$cscsm_table = $wpdb->prefix . 'cscsm_violations';
$wpdb->query( "DROP TABLE IF EXISTS `{$cscsm_table}`" ); // phpcs:ignore WordPress.DB, PluginCheck.Security.DirectDB.UnescapedDBParameter -- dropping the plugin's own table on uninstall; trusted prefix-based identifier.
unset( $cscsm_table );
