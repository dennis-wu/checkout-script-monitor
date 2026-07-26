<?php
/**
 * Plugin Name:       Checkout Script Monitor for WooCommerce
 * Plugin URI:        https://cybershieldstudio.com/tools/page-checker
 * Description:       See every script on your checkout and where you stand on PCI DSS 6.4.3, in plain English. A readiness and visibility tool, not a compliance guarantee.
 * Version:           0.3.2
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            CyberShield Studio
 * Author URI:        https://cybershieldstudio.com
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       checkout-script-monitor
 * Domain Path:       /languages
 *
 * Checkout Script Monitor for WooCommerce is free software: you can redistribute it and/or
 * modify it under the terms of the GNU General Public License, version 2, as
 * published by the Free Software Foundation.
 *
 * This plugin is not affiliated with, endorsed by, or certified by the PCI
 * Security Standards Council. It is a readiness and visibility aid only.
 *
 * @package Checkout_Script_Monitor
 */

defined( 'ABSPATH' ) || exit;

define( 'CSM_VERSION', '0.3.2' );
define( 'CSM_FILE', __FILE__ );
define( 'CSM_DIR', plugin_dir_path( __FILE__ ) );
define( 'CSM_URL', plugin_dir_url( __FILE__ ) );

// Option keys.
define( 'CSM_OPT_SETTINGS', 'csm_settings' );
define( 'CSM_OPT_INVENTORY', 'csm_inventory' );
define( 'CSM_OPT_BASELINE', 'csm_baseline' );
define( 'CSM_OPT_NOTICE', 'csm_notice_dismissed' );
define( 'CSM_OPT_DB_VERSION', 'csm_db_version' );
define( 'CSM_DB_VERSION', '1' );

require_once CSM_DIR . 'includes/class-inventory.php';
require_once CSM_DIR . 'includes/class-csp.php';
require_once CSM_DIR . 'includes/class-admin.php';

/**
 * Boot the plugin.
 */
function csm_init() {
	load_plugin_textdomain( 'checkout-script-monitor', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	$inventory = new CSM_Inventory();
	$csp       = new CSM_CSP( $inventory );
	$csp->hooks();

	if ( is_admin() ) {
		$admin = new CSM_Admin( $inventory, $csp );
		$admin->hooks();
	}
}
add_action( 'plugins_loaded', 'csm_init' );

/**
 * Activation: create the violations table and default settings.
 */
function csm_activate() {
	CSM_CSP::install_table();

	if ( false === get_option( CSM_OPT_SETTINGS, false ) ) {
		add_option( CSM_OPT_SETTINGS, array( 'csp_report_only' => 0 ) );
	}
	update_option( CSM_OPT_DB_VERSION, CSM_DB_VERSION );
}
register_activation_hook( __FILE__, 'csm_activate' );
