<?php
/**
 * Uninstall cleanup: remove options and the violations table.
 *
 * @package Checkout_Script_Monitor
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'csm_settings' );
delete_option( 'csm_inventory' );
delete_option( 'csm_baseline' );
delete_option( 'csm_notice_dismissed' );
delete_option( 'csm_db_version' );

global $wpdb;
$table = $wpdb->prefix . 'csm_violations';
$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore WordPress.DB
