<?php
/**
 * CSP Report-Only monitoring.
 *
 * Emits a Content-Security-Policy-Report-Only header built from the merchant's
 * OWN confirmed baseline (never an opinionated default list of "trusted"
 * sources), and receives violation reports at a local REST endpoint. Reports are
 * stored on the merchant's own site. The visitor IP and referrer are dropped at
 * ingest. Nothing leaves the site.
 *
 * @package CyberShield_Checkout_Script_Monitor
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class CSCSM_CSP.
 */
class CSCSM_CSP {

	const REST_NS    = 'cybershield-checkout-script-monitor/v1';
	const REST_ROUTE = '/csp-report';
	const MAX_BODY   = 8192; // 8 KB cap on a report body.
	const RATE_MAX   = 300;  // Max reports stored per minute (global).
	const KEEP_ROWS  = 500;  // Prune the violations table to this many rows.

	/**
	 * @var CSCSM_Inventory
	 */
	private $inventory;

	public function __construct( CSCSM_Inventory $inventory ) {
		$this->inventory = $inventory;
	}

	public function hooks() {
		// template_redirect (not send_headers): send_headers fires BEFORE the main
		// query runs, so is_cart()/is_checkout() are still false there and the
		// header would never emit on the payment path. template_redirect runs after
		// the query, before any output, so the conditionals are reliable and
		// header() still works.
		add_action( 'template_redirect', array( $this, 'maybe_send_header' ) );
		add_action( 'rest_api_init', array( $this, 'register_route' ) );
	}

	private function settings() {
		$s = get_option( CSCSM_OPT_SETTINGS, array() );
		return wp_parse_args( is_array( $s ) ? $s : array(), array( 'csp_report_only' => 0 ) );
	}

	public function is_enabled() {
		$s = $this->settings();
		return ! empty( $s['csp_report_only'] );
	}

	/**
	 * The confirmed baseline ( hosts + created_at ), or empty array.
	 *
	 * @return array
	 */
	public function get_baseline() {
		$b = get_option( CSCSM_OPT_BASELINE, array() );
		return is_array( $b ) ? $b : array();
	}

	/**
	 * Merge the latest scan's hosts INTO the confirmed baseline.
	 *
	 * Additive by design: it adds newly-seen hosts and never drops existing ones.
	 * A trusted host must not be silently removed just because a scan missed it
	 * (a temporarily-unavailable script, a partial scan, or a manually-trusted
	 * host that is not a page script). Removal is always an explicit merchant
	 * action via remove_baseline_host().
	 *
	 * @return array
	 */
	public function rebaseline() {
		$existing = $this->get_baseline();
		$hosts    = ( isset( $existing['hosts'] ) && is_array( $existing['hosts'] ) ) ? $existing['hosts'] : array();
		// TODO (v1.1): consider scoping the monitoring baseline to hosts seen on the
		// cart/checkout (the payment path) rather than the whole storefront. Each
		// scanned script already records the pages it appeared on, so a payment-path
		// baseline is possible; deferred because a thin static scan of an empty
		// cart/checkout would raise false positives until the capture is smarter
		// (JS-rendered / authenticated cart).
		foreach ( (array) $this->inventory->snapshot_hosts() as $h ) {
			$h = $this->clean_host( $h );
			if ( '' !== $h && ! in_array( $h, $hosts, true ) ) {
				$hosts[] = $h;
			}
		}
		$baseline = array(
			'hosts'      => array_values( array_unique( $hosts ) ),
			'created_at' => ! empty( $existing['created_at'] ) ? $existing['created_at'] : current_time( 'mysql' ),
		);
		update_option( CSCSM_OPT_BASELINE, $baseline, false );
		return $baseline;
	}

	/**
	 * Emit the report-only header on the cart/checkout when enabled and a
	 * baseline exists. Report-only never blocks anything.
	 */
	public function maybe_send_header() {
		if ( is_admin() || ! $this->is_enabled() || headers_sent() ) {
			return;
		}

		// Scope to the payment path (Requirement 6.4.3) when WooCommerce is here.
		if ( function_exists( 'is_checkout' ) && function_exists( 'is_cart' ) ) {
			if ( ! ( is_checkout() || is_cart() ) ) {
				return;
			}
		}

		$baseline = $this->get_baseline();
		if ( empty( $baseline['hosts'] ) ) {
			return; // Do not emit an empty policy that flags everything.
		}

		// 'unsafe-inline' is intentional here. This tool watches for off-baseline
		// script LOADS (the 6.4.3 / Magecart drift signal), not the site's own
		// inline <script> blocks — a WooCommerce checkout renders dozens of
		// legitimate ones, and without per-script hashes they can't be told apart
		// from a real threat, so they are pure noise. Allowing inline stops the
		// browser reporting it. Because the header is Report-Only (never enforced),
		// this has NO security effect; it only focuses reports on external hosts.
		$sources = array( "'self'", "'unsafe-inline'" );
		foreach ( $baseline['hosts'] as $host ) {
			$host = preg_replace( '/[^a-z0-9.\-]/i', '', (string) $host );
			if ( '' === $host ) {
				continue;
			}
			$sources[] = $host;
			// Treat an apex domain and its www. subdomain as equivalent: a trusted
			// "example.com" should also cover "www.example.com" and vice versa. CSP
			// host-source matching is exact and never implies www, and the scanner
			// stores the www-stripped host, so without this a script loaded from
			// www.<domain> would be reported as off-baseline (a false positive).
			if ( 0 === strpos( $host, 'www.' ) ) {
				$sources[] = substr( $host, 4 );
			} elseif ( 1 === substr_count( $host, '.' ) ) {
				$sources[] = 'www.' . $host;
			}
		}
		$report_uri = esc_url_raw( rest_url( self::REST_NS . self::REST_ROUTE ) );
		$policy     = 'script-src ' . implode( ' ', array_unique( $sources ) ) . '; report-uri ' . $report_uri;

		header( 'Content-Security-Policy-Report-Only: ' . $policy );
	}

	public function register_route() {
		register_rest_route(
			self::REST_NS,
			self::REST_ROUTE,
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'receive_report' ),
				// Public: browsers post violation reports here without auth.
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Receive a CSP violation report. Drops IP/referrer; stores only metadata.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function receive_report( WP_REST_Request $request ) {
		// Crude global rate limit to blunt floods (this endpoint is public).
		$bucket = 'cscsm_rl_' . gmdate( 'YmdHi' );
		$count  = (int) get_transient( $bucket );
		if ( $count >= self::RATE_MAX ) {
			return new WP_REST_Response( null, 429 );
		}
		set_transient( $bucket, $count + 1, MINUTE_IN_SECONDS );

		$raw = (string) $request->get_body();
		if ( strlen( $raw ) > self::MAX_BODY ) {
			return new WP_REST_Response( null, 413 );
		}

		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			return new WP_REST_Response( null, 204 );
		}

		// report-uri sends { "csp-report": {...} };
		// report-to sends [ { "body": {...} } ] or { "body": {...} }.
		$report = array();
		if ( isset( $data['csp-report'] ) && is_array( $data['csp-report'] ) ) {
			$report = $data['csp-report'];
		} elseif ( isset( $data[0]['body'] ) && is_array( $data[0]['body'] ) ) {
			$report = $data[0]['body'];
		} elseif ( isset( $data['body'] ) && is_array( $data['body'] ) ) {
			$report = $data['body'];
		}

		$blocked   = $this->pick( $report, array( 'blocked-uri', 'blockedURL' ) );
		$directive = $this->pick( $report, array( 'violated-directive', 'effectiveDirective' ) );
		$document  = $this->pick( $report, array( 'document-uri', 'documentURL' ) );
		$sample    = $this->pick( $report, array( 'script-sample', 'sample' ) );

		// Only record script LOADS from a network host — the drift signal. Inline
		// or eval violations are reported as a bare token ("inline", "eval",
		// "data", "blob", ...) with no URL scheme; that is the site's own code and
		// pure noise on a checkout, so drop it. ('unsafe-inline' in the policy
		// already suppresses most of it at the browser; this is the safety net.)
		$is_network_load = ( false !== strpos( $blocked, '://' ) ) || ( '//' === substr( $blocked, 0, 2 ) );
		if ( ! $is_network_load ) {
			return new WP_REST_Response( null, 204 );
		}

		$this->store_violation( $blocked, $directive, $document, $sample );
		return new WP_REST_Response( null, 204 );
	}

	private function pick( $arr, $keys ) {
		foreach ( $keys as $k ) {
			if ( isset( $arr[ $k ] ) && is_string( $arr[ $k ] ) ) {
				return $arr[ $k ];
			}
		}
		return '';
	}

	private function store_violation( $blocked, $directive, $document, $sample ) {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- writing to the plugin's own custom table.
			self::table(),
			array(
				'created_at'   => current_time( 'mysql' ),
				'blocked_uri'  => substr( sanitize_text_field( $blocked ), 0, 500 ),
				'directive'    => substr( sanitize_text_field( $directive ), 0, 190 ),
				'document_uri' => substr( sanitize_text_field( $document ), 0, 500 ),
				'sample'       => substr( sanitize_text_field( $sample ), 0, 500 ),
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);

		if ( 0 === wp_rand( 0, 20 ) ) {
			$this->prune();
		}
	}

	private function prune() {
		global $wpdb;
		$table  = self::table();
		$min_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$table}` ORDER BY id DESC LIMIT 1 OFFSET %d", self::KEEP_ROWS ) ); // phpcs:ignore
		if ( $min_id ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM `{$table}` WHERE id <= %d", $min_id ) ); // phpcs:ignore
		}
	}

	/**
	 * Grouped recent violations for the admin screen.
	 *
	 * @param int $limit Max rows.
	 * @return array
	 */
	public function recent_violations( $limit = 100 ) {
		global $wpdb;
		$table = self::table();
		// Table name is a trusted $wpdb->prefix-based identifier (not user input); the value is prepared.
		$sql = "SELECT blocked_uri, directive, COUNT(*) AS hits, MAX(created_at) AS last_seen FROM `{$table}` GROUP BY blocked_uri, directive ORDER BY last_seen DESC LIMIT %d"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a trusted prefix-based identifier.
		return $wpdb->get_results( $wpdb->prepare( $sql, $limit ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- reading the plugin's own custom table via a prepared %d value; the interpolated table name is a trusted prefix-based identifier; caching skipped so the admin sees fresh alerts.
	}

	public function clear_violations() {
		global $wpdb;
		$table = self::table();
		$wpdb->query( "TRUNCATE TABLE `{$table}`" ); // phpcs:ignore
	}

	/**
	 * Normalize a host or URL down to a bare hostname (a-z0-9.-).
	 *
	 * @param string $host Host or full URL.
	 * @return string
	 */
	public function clean_host( $host ) {
		$host = (string) $host;
		if ( false !== strpos( $host, '://' ) || '//' === substr( $host, 0, 2 ) ) {
			$parsed = wp_parse_url( ( '//' === substr( $host, 0, 2 ) ) ? 'https:' . $host : $host );
			$host   = isset( $parsed['host'] ) ? $parsed['host'] : '';
		}
		return (string) preg_replace( '/[^a-z0-9.\-]/i', '', $host );
	}

	/**
	 * Add one host to the confirmed baseline ("trust this script").
	 *
	 * @param string $host Host or full URL to trust.
	 * @return bool True if a host was added.
	 */
	public function add_baseline_host( $host ) {
		$host = $this->clean_host( $host );
		if ( '' === $host ) {
			return false;
		}
		$baseline = $this->get_baseline();
		$hosts    = ( isset( $baseline['hosts'] ) && is_array( $baseline['hosts'] ) ) ? $baseline['hosts'] : array();
		if ( ! in_array( $host, $hosts, true ) ) {
			$hosts[] = $host;
		}
		$baseline['hosts']      = array_values( array_unique( $hosts ) );
		$baseline['created_at'] = ! empty( $baseline['created_at'] ) ? $baseline['created_at'] : current_time( 'mysql' );
		update_option( CSCSM_OPT_BASELINE, $baseline, false );
		return true;
	}

	/**
	 * Remove one host from the confirmed baseline ("untrust"). Explicit only.
	 *
	 * @param string $host Host or full URL to remove.
	 */
	public function remove_baseline_host( $host ) {
		$host = $this->clean_host( $host );
		if ( '' === $host ) {
			return;
		}
		$baseline = $this->get_baseline();
		if ( empty( $baseline['hosts'] ) || ! is_array( $baseline['hosts'] ) ) {
			return;
		}
		$baseline['hosts'] = array_values(
			array_filter(
				$baseline['hosts'],
				static function ( $h ) use ( $host ) {
					return $h !== $host;
				}
			)
		);
		update_option( CSCSM_OPT_BASELINE, $baseline, false );
	}

	/**
	 * Delete stored alerts for a host (used after trusting it, to resolve them).
	 *
	 * @param string $host Host or full URL.
	 */
	public function forget_host( $host ) {
		global $wpdb;
		$host = $this->clean_host( $host );
		if ( '' === $host ) {
			return;
		}
		$table = self::table();
		$like  = '%' . $wpdb->esc_like( $host ) . '%';
		$wpdb->query( $wpdb->prepare( "DELETE FROM `{$table}` WHERE blocked_uri LIKE %s", $like ) ); // phpcs:ignore
	}

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'cscsm_violations';
	}

	public static function install_table() {
		global $wpdb;
		$table           = self::table();
		$charset_collate = $wpdb->get_charset_collate();
		$sql             = "CREATE TABLE `{$table}` (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			created_at DATETIME NOT NULL,
			blocked_uri VARCHAR(500) NOT NULL DEFAULT '',
			directive VARCHAR(190) NOT NULL DEFAULT '',
			document_uri VARCHAR(500) NOT NULL DEFAULT '',
			sample VARCHAR(500) NOT NULL DEFAULT '',
			PRIMARY KEY (id),
			KEY created_at (created_at)
		) {$charset_collate};";
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}
}
