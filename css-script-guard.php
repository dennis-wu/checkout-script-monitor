<?php
/**
 * Plugin Name:       Script Guard for WooCommerce
 * Plugin URI:        https://cybershieldstudio.com/tools/page-checker
 * Description:       See every script on your checkout and where you stand on PCI DSS 6.4.3, in plain English. A readiness and visibility tool, not a compliance guarantee.
 * Version:           0.1.2
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            CyberShield Studio
 * Author URI:        https://cybershieldstudio.com
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       css-script-guard
 * Domain Path:       /languages
 *
 * Script Guard for WooCommerce is free software: you can redistribute it and/or
 * modify it under the terms of the GNU General Public License, version 2, as
 * published by the Free Software Foundation.
 *
 * This plugin is not affiliated with, endorsed by, or certified by the PCI
 * Security Standards Council. It is a readiness and visibility aid only.
 *
 * @package CSS_Script_Guard
 */

defined( 'ABSPATH' ) || exit;

define( 'CSG_VERSION', '0.1.2' );
define( 'CSG_FILE', __FILE__ );
define( 'CSG_DIR', plugin_dir_path( __FILE__ ) );
define( 'CSG_URL', plugin_dir_url( __FILE__ ) );

// Option keys.
define( 'CSG_OPT_SETTINGS', 'csg_settings' );
define( 'CSG_OPT_INVENTORY', 'csg_inventory' );
define( 'CSG_OPT_BASELINE', 'csg_baseline' );
define( 'CSG_OPT_NOTICE', 'csg_notice_dismissed' );
define( 'CSG_OPT_DB_VERSION', 'csg_db_version' );
define( 'CSG_DB_VERSION', '1' );

require_once CSG_DIR . 'includes/class-inventory.php';
require_once CSG_DIR . 'includes/class-csp.php';
require_once CSG_DIR . 'includes/class-admin.php';

/**
 * Boot the plugin.
 */
function csg_init() {
	load_plugin_textdomain( 'css-script-guard', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	$inventory = new CSG_Inventory();
	$csp       = new CSG_CSP( $inventory );
	$csp->hooks();

	if ( is_admin() ) {
		$admin = new CSG_Admin( $inventory, $csp );
		$admin->hooks();
	}
}
add_action( 'plugins_loaded', 'csg_init' );

/**
 * Activation: create the violations table and default settings.
 */
function csg_activate() {
	CSG_CSP::install_table();

	if ( false === get_option( CSG_OPT_SETTINGS, false ) ) {
		add_option( CSG_OPT_SETTINGS, array( 'csp_report_only' => 0 ) );
	}
	update_option( CSG_OPT_DB_VERSION, CSG_DB_VERSION );
}
register_activation_hook( __FILE__, 'csg_activate' );
